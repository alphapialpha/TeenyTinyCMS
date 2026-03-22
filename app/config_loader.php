<?php
// Responsibility: Loads /config/config.php and exposes configuration
// via the config() helper and a global $GLOBALS['_teenytinycms_config'] array.

declare(strict_types=1);

(function (): void {
    $file = BASE_PATH . '/config/config.php';

    if (!file_exists($file)) {
        // Not installed yet – redirect to installer unless we already are there.
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_ends_with($script, 'install.php')) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/install.php');
            exit;
        }
        return;
    }

    $cfg = require $file;

    if (!is_array($cfg)) {
        throw new RuntimeException('config.php must return an array.');
    }

    $GLOBALS['_teenytinycms_config'] = $cfg;
})();

/**
 * Retrieve a top-level config value by key, with optional default.
 *
 * Usage:
 *   config('site_title')           → 'My Site'
 *   config('database')             → ['driver' => ..., ...]
 *   config('missing', 'fallback')  → 'fallback'
 */
function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['_teenytinycms_config'][$key] ?? $default;
}
