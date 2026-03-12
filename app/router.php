<?php
/**
 * TeenyTinyCMS – Front-controller router
 *
 * Supported URL patterns (all non-file requests routed here via .htaccess):
 *
 *   /                          → redirect to /{default_lang}/
 *   /{lang}/                   → homepage for that language (slug=index, type=page)
 *   /{lang}/{slug}             → page
 *   /{lang}/blog/{slug}        → post
 *   /media/public/{file}            → media delivery via media.php
 *
 * Resolution order:
 *   1. Parse URI
 *   2. Determine lang, type, slug from the pattern
 *   3. Look up slug in DB to confirm it exists and get its php_path
 *   4. Include the cached PHP file
 *   5. If nothing matched → 404
 */

declare(strict_types=1);

/**
 * Main entry point – called by index.php.
 */
function route_request(): void
{
    auth_start();

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = rawurldecode((string) $uri);
    $uri = '/' . trim($uri, '/');

    // ── / → redirect to default language ─────────────────────────────────
    if ($uri === '/') {
        $lang = config('default_lang', 'en');
        header('Location: /' . $lang . '/', true, 302);
        exit;
    }

    // ── /media/public/{filename} ─────────────────────────────────────────
    if (preg_match('#^/media/public/(.+)$#', $uri, $m)) {
        resolve_media('public/' . $m[1]);
        return;
    }

    // ── /{lang}/search-index.json ─────────────────────────────────────────
    if (preg_match('#^/([a-z]{2,5})/search-index\.json$#', $uri, $m)) {
        $json_path = BASE_PATH . '/cache/public/' . $m[1] . '/search-index.json';
        if (is_file($json_path)) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            readfile($json_path);
            return;
        }
        _router_not_found();
        return;
    }

    // ── URL segment parsing ───────────────────────────────────────────────
    // Strip leading slash and split
    $segments = explode('/', ltrim($uri, '/'));

    $lang = $segments[0] ?? '';

    // Validate lang  (allow 2–5 lowercase letters to match installer's accepted range)
    if (!preg_match('/^[a-z]{2,5}$/', $lang)) {
        _router_not_found();
        return;
    }

    // ── /{lang}/  or  /{lang}  → homepage ────────────────────────────────
    if (count($segments) === 1 || (count($segments) === 2 && $segments[1] === '')) {
        _router_serve($lang, 'index', 'page');
        return;
    }

    // ── /{lang}/tag/{tag}  → tag index page ──────────────────────────────────
    // Note: tag segment is validated by _router_serve()'s slug regex, so only
    // tags whose names produce [a-z0-9-] slugs are routable.
    if (count($segments) === 3 && $segments[1] === 'tag' && $segments[2] !== '') {
        // Tag pages are stored as slug='tag-{tagslug}', type='page'
        _router_serve($lang, 'tag-' . $segments[2], 'page');
        return;
    }

    // ── /{lang}/blog/page/{n}  → paginated blog index ──────────────────────
    if (count($segments) === 4 && $segments[1] === 'blog' && $segments[2] === 'page'
        && ctype_digit($segments[3]) && (int) $segments[3] >= 2) {
        _router_serve($lang, 'blog-page-' . $segments[3], 'page');
        return;
    }

    // ── /{lang}/blog/{slug}  → post ───────────────────────────────────────
    if (count($segments) === 3 && $segments[1] === 'blog' && $segments[2] !== '') {
        _router_serve($lang, $segments[2], 'post');
        return;
    }

    // ── /{lang}/{slug}  → page (supports hierarchical slugs like /en/docs/intro) ──
    $slug = implode('/', array_slice($segments, 1));
    if ($slug !== '') {
        _router_serve($lang, $slug, 'page');
        return;
    }

    _router_not_found();
}

/**
 * Look up the slug in the DB, check access, then include the cached PHP file.
 */
function _router_serve(string $lang, string $slug, string $type): void
{
    // Validate slug via shared validation function
    if (!is_valid_slug($slug)) {
        _router_not_found();
        return;
    }

    $row = db_fetch_one(
        'SELECT php_path FROM slugs
          WHERE slug = :slug AND lang = :lang AND type = :type',
        [':slug' => $slug, ':lang' => $lang, ':type' => $type]
    );

    if ($row === null) {
        _router_not_found();
        return;
    }

    $php_path = (string) $row['php_path'];

    if (!is_file($php_path)) {
        // Cache file missing – this shouldn't happen in normal operation
        error_log("[TeenyTinyCMS] Cache file missing for slug=$slug lang=$lang: $php_path");
        _router_not_found();
        return;
    }

    include $php_path;
}

/**
 * Emit a 404 response.
 * Tries to serve a custom 404 content page; falls back to a plain message.
 */
function _router_not_found(): void
{
    http_response_code(404);

    $lang     = config('default_lang', 'en');
    $php_path = BASE_PATH . '/cache/public/' . $lang . '/pages/404.php';

    if (is_file($php_path)) {
        include $php_path;
    } else {
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
        echo '<body><h1>404 – Page not found</h1>';
        echo '<p><a href="/">Go home</a></p></body></html>';
    }
    exit;
}
