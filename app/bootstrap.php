<?php
/**
 * TeenyTinyCMS – Application bootstrap
 * Loaded by index.php on every request.

 * Order matters: config → DB helpers → auth → template/content helpers.
 * The DB connection itself is lazy (opened on first db() call).
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . '/..');
}

// 1. Config (may redirect to install.php if not installed)
require_once BASE_PATH . '/app/config_loader.php';

// 2. Database abstraction (lazy PDO, no connection yet)
require_once BASE_PATH . '/app/db.php';

// 3. Authentication helpers
require_once BASE_PATH . '/app/auth.php';

// 4. Template + content helpers
require_once BASE_PATH . '/app/template_helpers.php';
require_once BASE_PATH . '/app/content_helpers.php';

// 5. Media abstraction
require_once BASE_PATH . '/app/media.php';

// 6. General utilities
require_once BASE_PATH . '/app/utils.php';

// ── Error reporting ──────────────────────────────────────────────────────────
// In production the installer sets 'installed' => true; errors should be logged
// but not displayed. During development you may temporarily enable display.
if (config('installed', false)) {
    $log_dir = BASE_PATH . '/data';
    if (is_dir($log_dir) && is_writable($log_dir)) {
        ini_set('error_log', $log_dir . '/error.log');
    }
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
        error_log("[TeenyTinyCMS] [$errno] $errstr in $errfile on line $errline");
        return false; // let PHP's built-in handler run for serious errors
    });
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log("[TeenyTinyCMS] [FATAL] {$err['message']} in {$err['file']} on line {$err['line']}");
        }
    });
} else {
    // Config missing – installer not yet run; suppress all output.
    ini_set('display_errors', '0');
}
