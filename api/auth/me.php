<?php
/**
 * API: Kiểm tra phiên đăng nhập hiện tại
 * Endpoint: GET /api/auth/me.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'isLoggedIn' => false,
        'message' => 'Chưa đăng nhập'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT u.id, u.email, u.full_name, u.phone, u.avatar, r.name AS role
                           FROM users u
                           JOIN roles r ON u.role_id = r.id
                           WHERE u.id = :id AND u.status = 'active'
                           LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User không còn tồn tại hoặc bị khóa
        session_destroy();
        echo json_encode([
            'success' => false,
            'isLoggedIn' => false,
            'message' => 'Phiên đăng nhập không hợp lệ'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'isLoggedIn' => true,
        'data' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'role' => $user['role']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kiểm tra phiên'], JSON_UNESCAPED_UNICODE);
}
?>

