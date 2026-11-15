<?php
declare(strict_types=1);

/**
 * Bloomora - Bootstrap (autoload simple services + base config)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

spl_autoload_register(function (string $class): void {
    $base = __DIR__ . '/../app/Services/';
    $file = $base . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

function bloomora_now(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
}