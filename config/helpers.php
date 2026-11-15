<?php
declare(strict_types=1);

/**
 * Bloomora - Core helpers (session, auth, json, input)
 */

function bloomora_env(string $key, $default = null) {
    $v = getenv($key);
    return $v !== false ? $v : $default;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
    }
    return !empty($_POST) ? $_POST : [];
}

function init_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function require_admin_auth(array $roles = ['admin', 'staff']): void {
    init_secure_session();
    $role = $_SESSION['role_name'] ?? '';
    if (empty($_SESSION['user_id']) || !in_array($role, $roles, true)) {
        json_response([
            'success' => false,
            'message' => 'Yêu cầu quyền truy cập quản trị. Vui lòng đăng nhập.'
        ], 401);
    }
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitize_string($value, int $maxLen = 255): string {
    $s = trim((string)$value);
    if (mb_strlen($s, 'UTF-8') > $maxLen) {
        $s = mb_substr($s, 0, $maxLen, 'UTF-8');
    }
    return $s;
}

function api_only_method($allowed): void {
    $allowed = is_array($allowed) ? $allowed : [$allowed];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);
    }
}