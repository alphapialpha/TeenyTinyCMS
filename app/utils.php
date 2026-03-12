<?php
/**
 * TeenyTinyCMS – General utility functions
 *
 * Pure helpers with no side-effects.
 * No DB access, no output.
 */

declare(strict_types=1);

/**
 * Derive the slug from a Markdown filename.
 *   'about.en.md'      → 'about'
 *   'first-post.de.md' → 'first-post'
 */
function slug_from_filename(string $filename): string
{
    $base = basename($filename, '.md');
    return (string) preg_replace('/\.[a-z]{2,10}$/', '', $base);
}

/**
 * Derive the language code from a Markdown filename.
 *   'about.en.md'      → 'en'
 *   'first-post.de.md' → 'de'
 * Falls back to 'en' if no valid suffix is found.
 */
function lang_from_filename(string $filename): string
{
    $base = basename($filename, '.md');
    if (preg_match('/\.([a-z]{2,10})$/', $base, $m)) {
        return $m[1];
    }
    return 'en';
}

/**
 * Determine whether a content path refers to a 'page' or a 'post'.
 */
function type_from_path(string $path): string
{
    return str_contains($path, '/posts/') ? 'post' : 'page';
}

/**
 * Validate a slug string.
 * Allowed: lowercase letters, digits, hyphens, and forward slashes (for hierarchical slugs).
 * Rejects: empty, leading/trailing slashes, double slashes, path traversal (..), null bytes.
 */
function is_valid_slug(string $slug): bool
{
    if ($slug === '') {
        return false;
    }
    // Block path traversal, null bytes, double slashes, leading/trailing slashes
    if (str_contains($slug, '..') || str_contains($slug, "\0")
        || str_contains($slug, '//') || $slug[0] === '/' || $slug[-1] === '/') {
        return false;
    }
    // Each segment must be non-empty and contain only [a-z0-9-], starting with [a-z0-9]
    foreach (explode('/', $slug) as $segment) {
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $segment)) {
            return false;
        }
    }
    return true;
}

/**
 * Compute the cache PHP file path for a given Markdown source path.
 *
 * When $slug is null the slug is derived from the filename (flat slug only).
 * When $slug is provided (from front matter) it may contain '/' for
 * hierarchical URLs, e.g. 'docs/getting-started' →
 *   /cache/public/en/pages/docs/getting-started.php
 *
 * Mapping (flat):
 *   /content/public/pages/about.en.md   → /cache/public/en/pages/about.php
 *   /content/public/posts/trip.de.md    → /cache/public/de/posts/trip.php
 *
 * Mapping (hierarchical):
 *   slug='docs/intro', /content/public/pages/docs-intro.en.md
 *     → /cache/public/en/pages/docs/intro.php
 */
function cache_path_for(string $md_path, ?string $slug = null): string
{
    $content_root = str_replace('\\', '/', BASE_PATH . '/content/');
    $normalised   = str_replace('\\', '/', $md_path);

    if (!str_starts_with($normalised, $content_root)) {
        throw new InvalidArgumentException("Path is not inside /content: $md_path");
    }

    // Relative portion: e.g. "public/pages/about.en.md"
    $relative = substr($normalised, strlen($content_root));
    $parts    = explode('/', $relative);

    if (count($parts) !== 3) {
        throw new InvalidArgumentException(
            "Content path must have exactly 3 segments (visibility/type/file), got: $relative"
        );
    }

    [$visibility, $type_dir, $filename] = $parts;

    $lang = lang_from_filename($filename);
    $resolved_slug = $slug ?? slug_from_filename($filename);

    return BASE_PATH . '/cache/' . $visibility . '/' . $lang . '/' . $type_dir . '/' . $resolved_slug . '.php';
}

/**
 * Fetch a translated UI label for the given key and language.
 * Merges site-wide config/translations.php with themes/{theme}/translations.php;
 * theme values take priority. Falls back to the key itself if not found.
 */
function t(string $key, string $lang): string
{
    static $TRANSLATIONS = null;
    if ($TRANSLATIONS === null) {
        // Core site-wide translations
        $core_file = BASE_PATH . '/config/translations.php';
        $core = is_file($core_file) ? (require $core_file) : [];

        // Theme-specific translations (overrides / additions)
        $theme_file = BASE_PATH . '/themes/' . active_theme() . '/translations.php';
        $theme = is_file($theme_file) ? (require $theme_file) : [];

        // Deep-merge: theme keys win over core keys
        $TRANSLATIONS = $core;
        foreach ($theme as $l => $keys) {
            $TRANSLATIONS[$l] = array_merge($TRANSLATIONS[$l] ?? [], $keys);
        }
    }
    return $TRANSLATIONS[$lang][$key] ?? $key;
}
