<?php
/**
 * API: Đăng xuất người dùng & Hủy session
 * Endpoint: POST/GET /api/auth/logout.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => true,
    'message' => 'Đăng xuất thành công!'
], JSON_UNESCAPED_UNICODE);
?>

