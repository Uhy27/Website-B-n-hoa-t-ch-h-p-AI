<?php
/**
 * API: Kiểm tra phiên đăng nhập hiện tại
 * Endpoint: GET /api/auth/me.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

init_secure_session();

if (!isset($_SESSION['user_id'])) {
    json_response([
        'success' => false,
        'isLoggedIn' => false,
        'message' => 'Chưa đăng nhập'
    ]);
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT u.id, u.email, u.full_name, u.phone, u.avatar, r.name AS role
                           FROM users u
                           JOIN roles r ON u.role_id = r.id
                           WHERE u.id = :id AND u.status = 'active'
                           LIMIT 1");
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User không còn tồn tại hoặc đã bị khóa/ban
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();

        json_response([
            'success' => false,
            'isLoggedIn' => false,
            'message' => 'Phiên đăng nhập đã hết hạn hoặc tài khoản bị khóa'
        ]);
    }

    json_response([
        'success' => true,
        'isLoggedIn' => true,
        'data' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'role' => $user['role']
        ]
    ]);

} catch (Exception $e) {
    error_log("[Auth Me Error] " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Lỗi kiểm tra phiên'], 500);
}
?>