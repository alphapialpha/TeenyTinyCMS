<?php
/**
 * TeenyTinyCMS – Template helper functions
 *
 * Available in every template and partial.
 * Loaded by bootstrap.php on every request and at build time.
 */

declare(strict_types=1);

/**
 * Escape a value for safe HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return the name of the currently active theme.
 * Falls back to 'default' if not configured.
 */
function active_theme(): string
{
    $theme = config('active_theme', 'default');
    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $theme)) {
        return 'default';
    }
    return $theme;
}

/**
 * Return the absolute filesystem path to the active theme's templates directory,
 * optionally with a subdirectory appended.
 *
 * theme_templates_path()            → /…/themes/default/templates
 * theme_templates_path('partials')  → /…/themes/default/templates/partials
 */
function theme_templates_path(string $sub = ''): string
{
    $base = BASE_PATH . '/themes/' . active_theme() . '/templates';
    return $sub !== '' ? $base . '/' . $sub : $base;
}

/**
 * Include a partial from the active theme's templates/partials/ directory.
 * Variables in $vars are extracted into the partial's local scope.
 *
 * Usage: render_partial('header', ['lang' => 'en'])
 */
function render_partial(string $name, array $vars = []): void
{
    $file = theme_templates_path('partials') . '/' . $name . '.php';
    if (!is_file($file)) {
        error_log("[TeenyTinyCMS] Partial not found: $name");
        return;
    }
    // Closure gives the partial its own clean scope
    (function (string $_file, array $_vars): void {
        extract($_vars, EXTR_SKIP);
        include $_file;
    })($file, $vars);
}

/**
 * Return the public URL to an asset in the active theme's assets/ directory.
 *
 * asset('css/app.css') → '/themes/default/assets/css/app.css'
 */
function asset(string $path): string
{
    return '/themes/' . active_theme() . '/assets/' . ltrim($path, '/');
}

/**
 * Return a language-aware URL.
 *
 * url_for('/about', 'en')      → '/en/about'
 * url_for('/blog/first', 'de') → '/de/blog/first'
 * url_for('/', 'en')           → '/en/'
 *
 * If $lang is null, config('default_lang') is used.
 */
function url_for(string $path, ?string $lang = null): string
{
    $lang = $lang ?? config('default_lang', 'en');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return '/' . $lang . '/';
    }

    return '/' . $lang . $path;
}
