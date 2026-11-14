<?php
/**
 * BLOOMORA - HELPER TIỆN ÍCH HỆ THỐNG
 * Khởi tạo session an toàn, xác thực quyền Admin/Staff, chuẩn hóa JSON response
 */

if (!function_exists('get_json_input')) {
    function get_json_input() {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            // Loại bỏ UTF-8 BOM nếu có
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
        return !empty($_POST) ? $_POST : [];
    }
}

if (!function_exists('init_secure_session')) {
    function init_secure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            // Thiết lập cờ bảo mật cookie session
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            session_set_cookie_params([
                'lifetime' => 86400 * 7, // 7 ngày
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
}

if (!function_exists('require_admin_auth')) {
    function require_admin_auth() {
        init_secure_session();
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['admin', 'staff'])) {
            http_response_code(401);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Yêu cầu quyền Quản trị viên! Vui lòng đăng nhập để tiếp tục.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('json_response')) {
    function json_response($data, $statusCode = 200) {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
