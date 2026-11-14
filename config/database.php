<?php
/**
 * BLOOMORA - KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL / MARIADB
 * Chuẩn PDO bảo mật cao, chống SQL Injection & che giấu thông tin nhạy cảm
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'bloomora_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Tắt emulate để bảo vệ chống SQLi thực chất
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Ghi log chi tiết vào error log của hệ thống thay vì phơi bày ra client
        error_log("[Bloomora DB Error] " . $e->getMessage());

        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi kết nối cơ sở dữ liệu. Vui lòng kiểm tra dịch vụ MySQL!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>

