<?php
declare(strict_types=1);

/**
 * AIService
 * Orchestrator for Bloomora Flower Shopping & Consultation Assistant
 * Implements Grounding + Tool Calling Architecture (Gemini LLM + Heuristic Fallback)
 */
class AIService {
    private PDO $pdo;
    private ProductRecommendationService $recommendationService;
    private KnowledgeService $knowledgeService;
    private string $apiKey;
    private string $modelName;
    private string $hotline;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDBConnection();
        $this->recommendationService = new ProductRecommendationService($this->pdo);
        $this->knowledgeService = new KnowledgeService($this->pdo);
        $this->loadSettings();
    }

    private function loadSettings(): void {
        $this->modelName = 'gemini-1.5-flash';
        $this->hotline = '1900 8888';
        $this->apiKey = (string)(bloomora_env('GEMINI_API_KEY') ?: '');

        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM ai_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($rows['gemini_api_key'])) {
                $this->apiKey = trim($rows['gemini_api_key']);
            }
            if (!empty($rows['ai_model'])) {
                $this->modelName = trim($rows['ai_model']);
            }
            if (!empty($rows['shop_hotline'])) {
                $this->hotline = trim($rows['shop_hotline']);
            }
        } catch (Throwable $e) {
            // Keep defaults
        }
    }

    /**
     * Process an incoming chat message with session tracking
     */
    public function handleMessage(string $sessionId, string $message, ?int $userId = null): array {
        $message = trim($message);
        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Vui lòng nhập nội dung tin nhắn.',
                'products' => [],
                'quick_replies' => []
            ];
        }

        $sessionId = $this->ensureSession($sessionId, $userId);

        // 1. Log user message to database
        $this->logMessage($sessionId, 'user', $message);

        // 2. Process query via Tool Calling (Gemini if API Key present, otherwise Heuristic Engine)
        $response = null;
        if (!empty($this->apiKey)) {
            $response = $this->processViaGemini($message, $sessionId);
        }

        // Fallback to local grounded rule engine if Gemini is not configured or failed
        if ($response === null) {
            $response = $this->processViaRuleEngine($message);
        }

        // 3. Log assistant response to database
        $this->logMessage(
            $sessionId,
            'bot',
            $response['message'],
            $response['intent'] ?? null,
            $response['tool_called'] ?? null,
            [
                'products_count' => count($response['products'] ?? []),
                'recommended_ids' => array_column($response['products'] ?? [], 'id')
            ]
        );

        $response['session_id'] = $sessionId;
        $response['success'] = true;
        return $response;
    }

    /**
     * Ensure session exists in chat_sessions table
     */
    private function ensureSession(string $sessionId, ?int $userId): string {
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);
        if (empty($sessionId)) {
            $sessionId = 'sess_' . bin2hex(random_bytes(16));
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM chat_sessions WHERE id = :id");
            $stmt->execute([':id' => $sessionId]);
            if (!$stmt->fetch()) {
                $insert = $this->pdo->prepare("INSERT INTO chat_sessions (id, user_id) VALUES (:id, :uid)");
                $insert->execute([':id' => $sessionId, ':uid' => $userId]);
            }
        } catch (Throwable $e) {
            error_log('[AIService ensureSession Error] ' . $e->getMessage());
        }

        return $sessionId;
    }

    /**
     * Log message to chat_messages table
     */
    private function logMessage(string $sessionId, string $sender, string $text, ?string $intent = null, ?string $tool = null, ?array $meta = null): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO chat_messages (session_id, sender, message, intent, tool_called, metadata) VALUES (:sid, :snd, :msg, :int, :tool, :meta)");
            $stmt->execute([
                ':sid'  => $sessionId,
                ':snd'  => $sender,
                ':msg'  => $text,
                ':int'  => $intent,
                ':tool' => $tool,
                ':meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Throwable $e) {
            error_log('[AIService logMessage Error] ' . $e->getMessage());
        }
    }

    /**
     * High-accuracy Local Grounded Engine (extracts intent, params, queries MySQL & formats grounded reply)
     */
    public function processViaRuleEngine(string $text): array {
        $lower = mb_strtolower($text);

        // 1. Detect Out of Scope (strictly flower & gift consultation)
        if ($this->isOutOfScope($lower)) {
            return [
                'intent' => 'out_of_scope',
                'message' => "Dạ em là trợ lý tư vấn hoa tươi của Bloomora. Em chỉ có thể hỗ trợ quý khách về chọn hoa, dịp tặng, ý nghĩa các loài hoa và chính sách giao hoa thôi ạ! Quý khách có muốn tìm mẫu hoa nào hôm nay không ạ?",
                'products' => [],
                'quick_replies' => ['Hoa sinh nhật', 'Hoa khai trương', 'Hoa tặng người yêu', 'Chính sách giao 60 phút']
            ];
        }

        // 2. Detect Greetings
        if ($this->isGreeting($lower)) {
            return [
                'intent' => 'greeting',
                'message' => "Dạ Bloomora xin chào quý khách! 🌸 Em có thể hỗ trợ bạn tìm hoa cho dịp gì (sinh nhật, khai trương, tỏ tình, kỷ niệm...) hoặc với ngân sách bao nhiêu ạ?",
                'products' => [],
                'quick_replies' => ['Hoa sinh nhật dưới 500k', 'Kệ hoa khai trương VIP', 'Bó hoa tặng người yêu', 'Cách giữ hoa tươi lâu']
            ];
        }

        // 3. Detect Care Guide
        if ($this->isCareGuide($lower)) {
            $flower = $this->extractFlowerType($lower);
            $guide = $this->knowledgeService->getCareGuide($flower);
            $reply = $guide ? "🌸 **" . $guide['title'] . "**\n\n" . $guide['content'] : "Dạ để giữ hoa tươi lâu, bạn nên cắt vát gốc 45 độ, thay nước sạch mỗi ngày và để hoa nơi mát mẻ tránh ánh nắng trực tiếp ạ!";
            return [
                'intent' => 'care_guide',
                'tool_called' => 'get_care_guide',
                'message' => $reply,
                'products' => [],
                'quick_replies' => ['Gợi ý hoa tươi bền lâu', 'Xem hoa lan hồ điệp', 'Chính sách bảo hành']
            ];
        }

        // 4. Detect Flower Meaning
        if ($this->isMeaning($lower)) {
            $flower = $this->extractFlowerType($lower);
            $meaning = $this->knowledgeService->getFlowerMeaning($flower ?: $lower);
            if ($meaning) {
                // Recommend matching flower products
                $prods = $this->recommendationService->searchProducts(['flower_type' => $flower, 'limit' => 3]);
                return [
                    'intent' => 'flower_meaning',
                    'tool_called' => 'get_flower_meaning',
                    'message' => "🌸 **" . $meaning['title'] . "**:\n" . $meaning['content'] . "\n\nBloomora gợi ý bạn các mẫu thiết kế nổi bật với loài hoa này:",
                    'products' => $prods,
                    'quick_replies' => ['Xem thêm mẫu khác', 'Ý nghĩa hoa hướng dương', 'Hoa tặng sinh nhật']
                ];
            }
        }

        // 5. Detect Store Policy & FAQ (Delivery, Shipping, Proof photo, Warranty)
        if ($this->isPolicy($lower)) {
            $policy = $this->knowledgeService->getPolicy($lower);
            $reply = $policy ? "ℹ️ **" . $policy['title'] . "**:\n" . $policy['content'] : "Dạ Bloomora hỗ trợ giao hoa hỏa tốc trong 60 - 90 phút nội thành TP.HCM, miễn phí giao từ 1.000.000đ và 100% chụp ảnh sản phẩm thật cho bạn duyệt trước khi giao ạ!";
            return [
                'intent' => 'policy_faq',
                'tool_called' => 'get_shop_policy',
                'message' => $reply,
                'products' => [],
                'quick_replies' => ['Giao hoa 60 phút', 'Bảo hành hoa dập héo', 'Chọn hoa ngay']
            ];
        }

        // 6. Default to Product Search & Recommendation (Grounded MySQL Tool Call)
        $criteria = $this->extractSearchCriteria($lower);
        $products = $this->recommendationService->searchProducts($criteria);

        if (!empty($products)) {
            $leadSentence = $this->generateLeadSentence($criteria, count($products));
            return [
                'intent' => 'product_search',
                'tool_called' => 'search_products',
                'extracted_filters' => $criteria,
                'message' => $leadSentence,
                'products' => $products,
                'quick_replies' => ['Xem mẫu dưới 500k', 'Tư vấn thiệp chúc mừng', 'Mẫu giao hỏa tốc']
            ];
        }

        // If no products matched tight filters, broaden criteria
        $broadCriteria = $criteria;
        unset($broadCriteria['max_price'], $broadCriteria['color']);
        $broadProducts = $this->recommendationService->searchProducts($broadCriteria);

        return [
            'intent' => 'product_search',
            'tool_called' => 'search_products',
            'extracted_filters' => $criteria,
            'message' => "Dạ với các tiêu chí cụ thể trên hiện tại shop chưa có mẫu khớp 100%, nhưng em xin gợi ý bạn các mẫu hoa bán chạy nhất phù hợp với dịp này của Bloomora ạ:",
            'products' => $broadProducts,
            'quick_replies' => ['Xem hoa dưới 500k', 'Xem hoa hồng lãng mạn', 'Liên hệ hotline ' . $this->hotline]
        ];
    }

    /**
     * Extract structured parameters from Vietnamese query
     */
    private function extractSearchCriteria(string $text): array {
        $criteria = [];

        // 1. Budget extraction
        if (preg_match('/(?:dưới|nhỏ hơn|tối đa|tầm|<|<=)\s*(\d+(?:\.\d+)?)\s*(k|nghìn|ngàn|tr|triệu)?/u', $text, $m)) {
            $val = (float)$m[1];
            $unit = mb_strtolower($m[2] ?? '');
            if ($unit === 'tr' || $unit === 'triệu') {
                $criteria['max_price'] = $val * 1000000;
            } elseif ($unit === 'k' || $unit === 'nghìn' || $unit === 'ngàn') {
                $criteria['max_price'] = $val * 1000;
            } elseif ($val < 50) {
                $criteria['max_price'] = $val * 1000000;
            } elseif ($val <= 2000) {
                $criteria['max_price'] = $val * 1000;
            } else {
                $criteria['max_price'] = $val;
            }
        } elseif (preg_match('/(\d{3,4})\s*k/u', $text, $m)) {
            $criteria['max_price'] = (float)$m[1] * 1000;
        }

        // 2. Occasion extraction
        $occasionMap = [
            'sinh nhật' => 'sinh-nhat',
            'khai trương' => 'khai-truong',
            'tình yêu' => 'tinh-yeu',
            'tỏ tình' => 'tinh-yeu',
            'valentine' => 'tinh-yeu',
            'kỷ niệm' => 'tinh-yeu',
            'cầu hôn' => 'tinh-yeu',
            'tốt nghiệp' => 'tot-nghiep',
            'chia buồn' => 'chia-buon',
            'tang lễ' => 'chia-buon',
            'viếng' => 'chia-buon',
            '20/10' => '20-10',
            '8/3' => '8-3',
            '20/11' => '20-11',
            'thầy cô' => '20-11',
            'tân gia' => 'tan-gia',
            'mừng thọ' => 'mung-tho',
        ];
        foreach ($occasionMap as $kw => $tag) {
            if (str_contains($text, $kw)) {
                $criteria['occasion'] = $tag;
                break;
            }
        }

        // 3. Recipient extraction
        $recipientMap = [
            'mẹ' => 'me',
            'bố' => 'bo',
            'ba' => 'bo',
            'người yêu' => 'nguoi-yeu',
            'bạn gái' => 'nguoi-yeu',
            'vợ' => 'vo',
            'crush' => 'nguoi-yeu',
            'bạn bè' => 'ban-be',
            'bạn thân' => 'ban-be',
            'đồng nghiệp' => 'dong-nghiep',
            'đối tác' => 'doi-tac',
            'sếp' => 'sep',
            'thầy cô' => 'thay-co',
            'em gái' => 'em-gai',
        ];
        foreach ($recipientMap as $kw => $tag) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $text)) {
                $criteria['recipient'] = $tag;
                break;
            }
        }

        // 4. Color extraction
        $colorMap = [
            'trắng' => 'trang',
            'đỏ' => 'do',
            'vàng' => 'vang',
            'hồng' => 'hong',
            'tím' => 'tim',
            'cam' => 'cam',
            'xanh' => 'xanh',
            'pastel' => 'hong',
        ];
        foreach ($colorMap as $kw => $tag) {
            if (str_contains($text, $kw)) {
                $criteria['color'] = $tag;
                break;
            }
        }

        // 5. Flower type extraction
        $flower = $this->extractFlowerType($text);
        if ($flower) {
            $criteria['flower_type'] = $flower;
        }

        // 6. Style extraction
        $styles = [];
        if (str_contains($text, 'hàn quốc') || str_contains($text, 'korean')) $styles[] = 'han-quoc';
        if (str_contains($text, 'nhẹ nhàng') || str_contains($text, 'thanh lịch')) $styles[] = 'nhe-nhang';
        if (str_contains($text, 'sang trọng') || str_contains($text, 'cao cấp') || str_contains($text, 'vip')) $styles[] = 'sang-trong';
        if (str_contains($text, 'vintage') || str_contains($text, 'cổ điển')) $styles[] = 'vintage';
        if (!empty($styles)) {
            $criteria['style'] = $styles;
        }

        // 7. Form extraction
        if (str_contains($text, 'bó hoa') || str_contains($text, 'bó')) $criteria['product_form'] = 'bo';
        elseif (str_contains($text, 'giỏ hoa') || str_contains($text, 'giỏ')) $criteria['product_form'] = 'gio';
        elseif (str_contains($text, 'kệ hoa') || str_contains($text, 'kệ')) $criteria['product_form'] = 'ke';
        elseif (str_contains($text, 'hộp hoa') || str_contains($text, 'hộp')) $criteria['product_form'] = 'hop';
        elseif (str_contains($text, 'chậu lan') || str_contains($text, 'chậu')) $criteria['product_form'] = 'chau';
        elseif (str_contains($text, 'lẵng hoa') || str_contains($text, 'lẵng')) $criteria['product_form'] = 'lang';

        return $criteria;
    }

    private function generateLeadSentence(array $c, int $count): string {
        $desc = [];
        if (!empty($c['color'])) {
            $colorName = ['trang' => 'tone trắng', 'do' => 'tone đỏ', 'vang' => 'tone vàng', 'hong' => 'tone hồng', 'tim' => 'tone tím'][$c['color']] ?? $c['color'];
            $desc[] = $colorName;
        }
        if (!empty($c['flower_type'])) {
            $desc[] = "hoa " . $c['flower_type'];
        }
        if (!empty($c['max_price'])) {
            $desc[] = "ngân sách dưới " . number_format($c['max_price'], 0, ',', '.') . "đ";
        }
        if (!empty($c['recipient'])) {
            $desc[] = "tặng " . (['me' => 'Mẹ', 'nguoi-yeu' => 'người yêu', 'vo' => 'bà xã', 'sep' => 'Sếp', 'ban-be' => 'bạn bè'][$c['recipient']] ?? $c['recipient']);
        }

        $detail = !empty($desc) ? " (" . implode(', ', $desc) . ")" : "";
        return "Dạ Bloomora xin gợi ý cho bạn {$count} mẫu hoa tươi đẹp nhất{$detail} phù hợp với yêu cầu của bạn ạ:";
    }

    private function extractFlowerType(string $text): ?string {
        $map = [
            'lan hồ điệp' => 'lan-ho-diep',
            'hồ điệp' => 'lan-ho-diep',
            'hướng dương' => 'huong-duong',
            'cúc họa mi' => 'cuc',
            'họa mi' => 'cuc',
            'hoa hồng' => 'hong',
            'hồng đỏ' => 'hong',
            'hồng ecuador' => 'hong',
            'baby' => 'baby',
            'cẩm chướng' => 'cam-chuong',
            'cát tường' => 'cat-tuong',
            'hoa ly' => 'ly',
        ];
        foreach ($map as $k => $v) {
            if (str_contains($text, $k)) {
                return $v;
            }
        }
        return null;
    }

    private function isGreeting(string $text): bool {
        return (bool)preg_match('/^(xin chào|chào|chào shop|hello|hi|alo|hé lô|chào bạn)\b/u', $text);
    }

    private function isCareGuide(string $text): bool {
        return (bool)preg_match('/(chăm sóc|giữ tươi|tươi lâu|thay nước|cắt cành|héo|tưới nước)/u', $text);
    }

    private function isMeaning(string $text): bool {
        return (bool)preg_match('/(ý nghĩa|tượng trưng|biểu tượng|thông điệp|loài hoa nào)/u', $text);
    }

    private function isPolicy(string $text): bool {
        return (bool)preg_match('/(giao hàng|phí ship|mấy tiếng|60 phút|hỏa tốc|chụp ảnh|xem ảnh|đổi trả|bảo hành|hoàn tiền|thanh toán)/u', $text);
    }

    private function isOutOfScope(string $text): bool {
        return (bool)preg_match('/(thời tiết|chứng khoán|crypto|viết code|lập trình|đá bóng|chính trị|tổng thống)/u', $text);
    }

    /**
     * Gemini Function Calling integration (Grounding with Tool Declarations)
     */
    private function processViaGemini(string $text, string $sessionId): ?array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName}:generateContent?key={$this->apiKey}";

        $systemInstruction = [
            "parts" => [
                ["text" => "Bạn là Bloomora Flower Consultation & Shopping Assistant.
Nhiệm vụ: Tư vấn hoa tươi, tìm kiếm sản phẩm và giải đáp kiến thức hoa/chính sách Bloomora.
NGUYÊN TẮC BẮT BUỘC:
1. Không bao giờ tự bịa đặt tên sản phẩm, giá bán hoặc tồn kho.
2. Khi khách hàng muốn tìm, chọn, mua hoa hoặc hỏi ngân sách/dịp: BẮT BUỘC gọi tool 'search_products'.
3. Chỉ đề xuất và nói về những sản phẩm có trong kết quả tool trả về.
4. Trả lời bằng tiếng Việt thân thiện, trang nhã, lễ phép."]
            ]
        ];

        $tools = [
            [
                "function_declarations" => [
                    [
                        "name" => "search_products",
                        "description" => "Tìm kiếm sản phẩm hoa từ cơ sở dữ liệu thật của Bloomora với bộ lọc dịp, người nhận, ngân sách, màu sắc, phong cách",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "occasion" => ["type" => "STRING", "description" => "Dịp tặng: sinh-nhat, khai-truong, tinh-yeu, tot-nghiep, chia-buon, 20-10, 8-3, 20-11"],
                                "recipient" => ["type" => "STRING", "description" => "Người nhận: me, bo, nguoi-yeu, vo, ban-be, dong-nghiep, doi-tac, sep, thay-co"],
                                "max_price" => ["type" => "NUMBER", "description" => "Ngân sách tối đa (VND, ví dụ 500000)"],
                                "color" => ["type" => "STRING", "description" => "Màu sắc: trang, do, vang, hong, tim, cam, xanh"],
                                "flower_type" => ["type" => "STRING", "description" => "Loại hoa: hong, huong-duong, lan-ho-diep, cuc, baby, cam-chuong, ly"],
                                "product_form" => ["type" => "STRING", "description" => "Hình thức: bo, gio, ke, hop, chau, lang"],
                                "keyword" => ["type" => "STRING", "description" => "Từ khóa tìm kiếm"]
                            ]
                        ]
                    ],
                    [
                        "name" => "get_flower_meaning",
                        "description" => "Tra cứu ý nghĩa loài hoa từ cơ sở kiến thức",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "flower_name" => ["type" => "STRING", "description" => "Tên loài hoa (ví dụ: hoa hồng đỏ, hướng dương, lan hồ điệp)"]
                            ],
                            "required" => ["flower_name"]
                        ]
                    ]
                ]
            ]
        ];

        $payload = [
            "system_instruction" => $systemInstruction,
            "tools" => $tools,
            "contents" => [
                ["role" => "user", "parts" => [["text" => $text]]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($res)) {
            return null; // Fallback to rule engine
        }

        $geminiData = json_decode($res, true);
        $candidate = $geminiData['candidates'][0]['content']['parts'][0] ?? null;

        if (!$candidate) return null;

        // Check if Gemini invoked a function call
        if (isset($candidate['functionCall'])) {
            $funcName = $candidate['functionCall']['name'];
            $args = $candidate['functionCall']['args'] ?? [];

            if ($funcName === 'search_products') {
                $products = $this->recommendationService->searchProducts($args);
                $leadSentence = $this->generateLeadSentence($args, count($products));
                return [
                    'intent' => 'product_search',
                    'tool_called' => 'search_products',
                    'extracted_filters' => $args,
                    'message' => $leadSentence,
                    'products' => $products,
                    'quick_replies' => ['Xem mẫu dưới 500k', 'Tư vấn thiệp chúc mừng', 'Mẫu giao 60 phút']
                ];
            } elseif ($funcName === 'get_flower_meaning') {
                $flowerName = $args['flower_name'] ?? '';
                $meaning = $this->knowledgeService->getFlowerMeaning($flowerName);
                if ($meaning) {
                    $prods = $this->recommendationService->searchProducts(['flower_type' => $this->extractFlowerType($flowerName), 'limit' => 3]);
                    return [
                        'intent' => 'flower_meaning',
                        'tool_called' => 'get_flower_meaning',
                        'message' => "🌸 **" . $meaning['title'] . "**:\n" . $meaning['content'],
                        'products' => $prods,
                        'quick_replies' => ['Xem thêm mẫu khác', 'Cách giữ hoa tươi lâu']
                    ];
                }
            }
        }

        if (isset($candidate['text'])) {
            return [
                'intent' => 'general_chat',
                'message' => $candidate['text'],
                'products' => [],
                'quick_replies' => ['Hoa sinh nhật', 'Hoa khai trương', 'Hotline tư vấn']
            ];
        }

        return null;
    }
}
