<?php
declare(strict_types=1);

/**
 * ProductRecommendationService
 * Bloomora Grounded Recommendation Engine with Multi-Criteria Scoring Formula:
 * Score = Wo*O + Wr*R + Wp*P + Wc*C + Ws*S + Wt*T
 */
class ProductRecommendationService {
    private PDO $pdo;
    private array $weights = [];

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDBConnection();
        $this->loadWeights();
    }

    /**
     * Load dynamic scoring weights from ai_settings table
     */
    private function loadWeights(): void {
        $defaults = [
            'weight_occasion'     => 0.25,
            'weight_price'        => 0.25,
            'weight_recipient'    => 0.20,
            'weight_color_flower' => 0.15,
            'weight_style_form'   => 0.10,
            'weight_stock'        => 0.05,
        ];

        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM ai_settings WHERE setting_key LIKE 'weight_%'");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($defaults as $k => $defVal) {
                $this->weights[$k] = isset($rows[$k]) ? (float)$rows[$k] : $defVal;
            }
        } catch (Throwable $e) {
            $this->weights = $defaults;
        }
    }

    /**
     * Search and score products based on structured criteria extracted by AI
     */
    public function searchProducts(array $criteria): array {
        $limit = isset($criteria['limit']) ? max(1, min((int)$criteria['limit'], 10)) : 4;
        $maxPrice = isset($criteria['max_price']) && is_numeric($criteria['max_price']) ? (float)$criteria['max_price'] : null;
        $minPrice = isset($criteria['min_price']) && is_numeric($criteria['min_price']) ? (float)$criteria['min_price'] : null;

        $targetOccasion = $this->normalizeString($criteria['occasion'] ?? '');
        $targetRecipient = $this->normalizeString($criteria['recipient'] ?? '');
        $targetColors = $this->normalizeArray($criteria['color'] ?? []);
        $targetFlowers = $this->normalizeArray($criteria['flower_type'] ?? []);
        $targetStyles = $this->normalizeArray($criteria['style'] ?? []);
        $targetForms = $this->normalizeArray($criteria['product_form'] ?? []);
        $keyword = trim((string)($criteria['keyword'] ?? ''));

        // Query active products from database
        $sql = "SELECT p.id, p.category_id, p.sku, p.name, p.slug, p.price, p.sale_price,
                       p.stock_quantity, p.stock_status, p.primary_flower, p.color, p.color_name,
                       p.size_dimensions, p.meaning, p.tags, p.occasion_tags, p.recipient_tags,
                       p.flower_type_tags, p.color_tags, p.style_tags, p.product_form,
                       p.thumbnail, p.rating, p.reviews_count,
                       c.name AS category_name, c.slug AS category_slug
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1";

        $stmt = $this->pdo->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scoredProducts = [];

        foreach ($products as $p) {
            $price = (float)$p['price'];
            $stockQty = (int)$p['stock_quantity'];
            $stockStatus = $p['stock_status'] ?? 'in_stock';

            // Filter out completely out of stock items
            if ($stockQty <= 0 || $stockStatus === 'out_of_stock') {
                continue;
            }

            // Price hard filtering if user specified max_price with reasonable tolerance (+10% buffer for near matches)
            if ($maxPrice !== null && $maxPrice > 0) {
                if ($price > ($maxPrice * 1.15)) {
                    continue; // Skip products significantly over budget
                }
            }
            if ($minPrice !== null && $minPrice > 0) {
                if ($price < ($minPrice * 0.85)) {
                    continue;
                }
            }

            $scoreComponents = [
                'occasion'     => 0.0,
                'recipient'    => 0.0,
                'price'        => 0.0,
                'color_flower' => 0.0,
                'style_form'   => 0.0,
                'stock'        => 0.0,
            ];
            $matchReasons = [];

            // 1. O - Occasion matching (25%)
            if (!empty($targetOccasion)) {
                $pOccasions = explode(',', (string)($p['occasion_tags'] ?? ''));
                if ($this->tagMatches($targetOccasion, $pOccasions)) {
                    $scoreComponents['occasion'] = 1.0;
                    $matchReasons[] = "Phù hợp dịp " . $this->friendlyTag($targetOccasion);
                } elseif (!empty($p['category_slug']) && str_contains($p['category_slug'], $targetOccasion)) {
                    $scoreComponents['occasion'] = 0.8;
                }
            } else {
                // If not specified, neutral base score
                $scoreComponents['occasion'] = 0.5;
            }

            // 2. R - Recipient matching (20%)
            if (!empty($targetRecipient)) {
                $pRecipients = explode(',', (string)($p['recipient_tags'] ?? ''));
                if ($this->tagMatches($targetRecipient, $pRecipients)) {
                    $scoreComponents['recipient'] = 1.0;
                    $matchReasons[] = "Rất hợp tặng " . $this->friendlyTag($targetRecipient);
                }
            } else {
                $scoreComponents['recipient'] = 0.5;
            }

            // 3. P - Price matching (25%)
            if ($maxPrice !== null && $maxPrice > 0) {
                if ($price <= $maxPrice) {
                    // Perfect fit under or equal to max budget
                    $ratio = $price / $maxPrice;
                    // Products closer to budget get slightly higher relevance score
                    $scoreComponents['price'] = 0.8 + (0.2 * $ratio);
                    $matchReasons[] = "Đúng ngân sách (" . number_format($price, 0, ',', '.') . "đ)";
                } else {
                    // Exceeds maxPrice but within buffer
                    $overRatio = ($price - $maxPrice) / ($maxPrice * 0.15);
                    $scoreComponents['price'] = max(0.0, 0.6 - (0.5 * $overRatio));
                }
            } else {
                $scoreComponents['price'] = 0.5;
            }

            // 4. C - Color & Flower type matching (15%)
            $colorMatch = 0.0;
            $flowerMatch = 0.0;
            if (!empty($targetColors)) {
                $pColors = explode(',', (string)($p['color_tags'] ?? ''));
                $pColor = (string)($p['color'] ?? '');
                foreach ($targetColors as $c) {
                    if ($this->tagMatches($c, $pColors) || str_contains($pColor, $c)) {
                        $colorMatch = 1.0;
                        $matchReasons[] = "Tone màu " . ($p['color_name'] ?: $c);
                        break;
                    }
                }
            }
            if (!empty($targetFlowers)) {
                $pFlowers = explode(',', (string)($p['flower_type_tags'] ?? ''));
                $pPrimary = mb_strtolower((string)($p['primary_flower'] ?? ''));
                foreach ($targetFlowers as $f) {
                    if ($this->tagMatches($f, $pFlowers) || str_contains($pPrimary, $f)) {
                        $flowerMatch = 1.0;
                        $matchReasons[] = "Hoa chính: " . ($p['primary_flower'] ?: $f);
                        break;
                    }
                }
            }
            if (!empty($targetColors) && !empty($targetFlowers)) {
                $scoreComponents['color_flower'] = ($colorMatch * 0.5) + ($flowerMatch * 0.5);
            } elseif (!empty($targetColors)) {
                $scoreComponents['color_flower'] = $colorMatch;
            } elseif (!empty($targetFlowers)) {
                $scoreComponents['color_flower'] = $flowerMatch;
            } else {
                $scoreComponents['color_flower'] = 0.5;
            }

            // 5. S - Style & Form matching (10%)
            $styleMatch = 0.0;
            $formMatch = 0.0;
            if (!empty($targetStyles)) {
                $pStyles = explode(',', (string)($p['style_tags'] ?? ''));
                foreach ($targetStyles as $s) {
                    if ($this->tagMatches($s, $pStyles)) {
                        $styleMatch = 1.0;
                        $matchReasons[] = "Phong cách " . $s;
                        break;
                    }
                }
            }
            if (!empty($targetForms)) {
                $pForm = (string)($p['product_form'] ?? '');
                foreach ($targetForms as $f) {
                    if ($pForm === $f || str_contains($pForm, $f)) {
                        $formMatch = 1.0;
                        $matchReasons[] = "Hình thức: " . $this->friendlyForm($pForm);
                        break;
                    }
                }
            }
            if (!empty($targetStyles) && !empty($targetForms)) {
                $scoreComponents['style_form'] = ($styleMatch * 0.5) + ($formMatch * 0.5);
            } elseif (!empty($targetStyles)) {
                $scoreComponents['style_form'] = $styleMatch;
            } elseif (!empty($targetForms)) {
                $scoreComponents['style_form'] = $formMatch;
            } else {
                $scoreComponents['style_form'] = 0.5;
            }

            // 6. T - Stock Availability (5%)
            if ($stockStatus === 'in_stock' && $stockQty >= 10) {
                $scoreComponents['stock'] = 1.0;
            } elseif ($stockStatus === 'low_stock' || $stockQty > 0) {
                $scoreComponents['stock'] = 0.6;
            } else {
                $scoreComponents['stock'] = 0.2;
            }

            // Calculate final composite score (0 - 100)
            $totalScore = (
                ($this->weights['weight_occasion']     * $scoreComponents['occasion']) +
                ($this->weights['weight_price']        * $scoreComponents['price']) +
                ($this->weights['weight_recipient']    * $scoreComponents['recipient']) +
                ($this->weights['weight_color_flower'] * $scoreComponents['color_flower']) +
                ($this->weights['weight_style_form']   * $scoreComponents['style_form']) +
                ($this->weights['weight_stock']        * $scoreComponents['stock'])
            ) * 100.0;

            // Extra bonus for keyword matching if keyword is present
            if (!empty($keyword)) {
                $kw = mb_strtolower($keyword);
                if (str_contains(mb_strtolower($p['name']), $kw) || str_contains(mb_strtolower((string)$p['primary_flower']), $kw)) {
                    $totalScore += 10.0;
                }
            }

            $p['score'] = round($totalScore, 1);
            $p['match_reasons'] = array_slice(array_unique($matchReasons), 0, 3);
            $p['formatted_price'] = number_format((float)$p['price'], 0, ',', '.') . 'đ';
            $p['product_url'] = '/index.html#product-' . $p['id'];

            $scoredProducts[] = $p;
        }

        // Sort descending by score
        usort($scoredProducts, function ($a, $b) {
            if ($b['score'] === $a['score']) {
                return (int)$b['reviews_count'] <=> (int)$a['reviews_count'];
            }
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scoredProducts, 0, $limit);
    }

    private function tagMatches(string $target, array $tags): bool {
        $target = trim(mb_strtolower($target));
        if ($target === '') return false;
        foreach ($tags as $t) {
            $t = trim(mb_strtolower($t));
            if ($t === '') continue;
            if ($t === $target || str_contains($t, $target) || str_contains($target, $t)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeString($val): string {
        if (is_array($val)) {
            return !empty($val) ? trim(mb_strtolower((string)$val[0])) : '';
        }
        return trim(mb_strtolower((string)$val));
    }

    private function normalizeArray($val): array {
        if (is_array($val)) {
            return array_values(array_filter(array_map('trim', array_map('mb_strtolower', $val))));
        }
        $str = trim((string)$val);
        if ($str === '') return [];
        return array_values(array_filter(array_map('trim', array_map('mb_strtolower', explode(',', $str)))));
    }

    private function friendlyTag(string $tag): string {
        $map = [
            'sinh-nhat' => 'Sinh nhật',
            'khai-truong' => 'Khai trương',
            'tinh-yeu' => 'Tình yêu',
            'tot-nghiep' => 'Tốt nghiệp',
            'chia-buon' => 'Chia buồn',
            '20-10' => 'Phụ nữ 20/10',
            '8-3' => 'Quốc tế Phụ nữ 8/3',
            '20-11' => 'Tri ân Thầy Cô 20/11',
            'me' => 'Mẹ',
            'bo' => 'Bố',
            'nguoi-yeu' => 'Người yêu',
            'vo' => 'Vợ',
            'ban-be' => 'Bạn bè',
            'doi-tac' => 'Đối tác',
            'sep' => 'Sếp',
        ];
        return $map[$tag] ?? $tag;
    }

    private function friendlyForm(string $form): string {
        $map = [
            'bo' => 'Bó hoa tươi',
            'gio' => 'Giỏ hoa',
            'ke' => 'Kệ hoa sự kiện',
            'hop' => 'Hộp hoa',
            'chau' => 'Chậu lan hồ điệp',
            'lang' => 'Lẵng hoa',
        ];
        return $map[$form] ?? $form;
    }
}
