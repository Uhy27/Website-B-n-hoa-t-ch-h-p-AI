<?php
declare(strict_types=1);

/**
 * POST /api/chat.php - Bloomora AI Chatbot Assistant Endpoint
 */

require_once __DIR__ . '/../core/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Health check or session history
    $action = trim((string)($_GET['action'] ?? 'status'));
    if ($action === 'history') {
        $sessionId = sanitize_string($_GET['session_id'] ?? '', 64);
        if (empty($sessionId)) {
            json_response(['success' => false, 'message' => 'Thiếu session_id.'], 400);
        }
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, sender, message, intent, metadata, created_at FROM chat_messages WHERE session_id = :sid ORDER BY id ASC LIMIT 50");
        $stmt->execute([':sid' => $sessionId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as &$m) {
            if (!empty($m['metadata'])) {
                $m['metadata'] = json_decode((string)$m['metadata'], true);
            }
        }
        json_response(['success' => true, 'session_id' => $sessionId, 'messages' => $messages]);
    }

    json_response([
        'success' => true,
        'service' => 'Bloomora AI Chatbot Assistant',
        'status' => 'active',
        'phase' => 'Phase 1 - Grounded Shopping & Consultation',
        'timestamp' => bloomora_now()
    ]);
}

api_only_method('POST');

try {
    $input = get_json_input();
    $message = sanitize_string($input['message'] ?? '', 1000);
    $sessionId = sanitize_string($input['session_id'] ?? '', 64);
    $userId = !empty($input['user_id']) ? (int)$input['user_id'] : null;

    if ($message === '') {
        json_response(['success' => false, 'message' => 'Nội dung tin nhắn không được để trống.'], 400);
    }

    $aiService = new AIService();
    $result = $aiService->handleMessage($sessionId, $message, $userId);

    json_response($result);
} catch (Throwable $e) {
    error_log('[Chat API Error] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Hệ thống trợ lý AI đang bận. Quý khách vui lòng liên hệ hotline 1900 8888 để được hỗ trợ nhanh nhất.'
    ], 500);
}
