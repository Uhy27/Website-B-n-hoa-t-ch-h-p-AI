<?php
/**
 * API: Đăng xuất người dùng & Hủy session an toàn
 * Endpoint: POST/GET /api/auth/logout.php
 */4

require_once __DIR__ . '/../../config/helpers.php';

init_secure_session();

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

json_response([
    'success' => true,
    'message' => 'Đăng xuất thành công!'
]);
?>