<?php
declare(strict_types=1);

/**
 * Bloomora - Database connection (PDO, UTF-8, secure defaults)
 */

require_once __DIR__ . '/helpers.php';

date_default_timezone_set(bloomora_env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

define('DB_HOST', (string)bloomora_env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string)bloomora_env('DB_PORT', '3306'));
define('DB_NAME', (string)bloomora_env('DB_NAME', 'bloomora_db'));
define('DB_USER', (string)bloomora_env('DB_USER', 'root'));
define('DB_PASS', (string)bloomora_env('DB_PASS', ''));

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[Bloomora DB Error] ' . $e->getMessage());
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra dịch vụ MySQL.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}