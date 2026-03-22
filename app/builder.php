<?php
/**
 * TeenyTinyCMS – Build / Cache Generator
 *
 * Scans all Markdown files in /content, parses them, renders templates
 * + layout, writes cached PHP files to /cache, and syncs slug/tag
 * metadata to the DB.
 *
 * Build passes:
 *   Pass 0 – prune stale DB rows (content + media) and cache files
 *   Pass 1 – parse front matter of every .md file and sync to DB
 *             (ensures the homepage sees an up-to-date post list)
 *   Pass 1b – scan content/{public,private}/media/** and upsert local media rows
 *   Pass 2 – render each file using templates and write cache PHP files
 *   Pass 3 – build one static tag-index page per (tag, lang) combination
 *
 * Public API:
 *   build_all(): array           – rebuild everything, returns stats
 *   build_file(string $path): void  – build a single .md file
 *
 * CLI usage:  php app/builder.php
 */

declare(strict_types=1);

// ── Internal helpers ─────────────────────────────────────────────────────────

/**
 * Fill slug and lang from the filename when front matter did not supply them.
 * slug defaults to '' and lang defaults to 'en' in _normalise_meta(), so:
 *   - empty slug  → derive from filename
 *   - lang = 'en' → check filename in case the file is actually a non-English language
 *
 * @param  array<string, mixed> $meta
 * @return array<string, mixed>
 */
function _builder_apply_filename_meta(array $meta, string $md_path): array
{
    if ($meta['slug'] === '') {
        $meta['slug'] = slug_from_filename($md_path);
    }
    if ($meta['lang'] === 'en') {
        $file_lang = lang_from_filename($md_path);
        if ($file_lang !== 'en') {
            $meta['lang'] = $file_lang;
        }
    }
    return $meta;
}

/** Collect all .md files under /content recursively. */
function _builder_collect_files(): array
{
    $content_dir = BASE_PATH . '/content';
    $files       = [];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Collect all media files under /content/public/media recursively.
 *
 * Returns an array of items, each containing:
 *   canonical  – DB path key,  e.g. 'public/my-post/cat.png'
 *   abs_path   – absolute path, e.g. '/srv/tinycms/content/public/media/my-post/cat.png'
 *   mime_type  – resolved MIME string
 *
 * The 'media/' folder itself is NOT included in the canonical path so that
 * a file at content/public/media/cat.png is served at /media/public/cat.png.
 *
 * @return array<int, array{canonical: string, abs_path: string, mime_type: string}>
 */
function _builder_collect_media_files(): array
{
    // Reuse the canonical MIME map from media.php (loaded via bootstrap)
    if (!defined('MEDIA_MIME_MAP')) {
        require_once BASE_PATH . '/app/media.php';
    }

    $results = [];

    $media_dir = BASE_PATH . '/content/public/media';

    if (is_dir($media_dir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($media_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $abs_path  = $file->getPathname();
            $rel       = substr($abs_path, strlen($media_dir) + 1);
            $rel       = str_replace('\\', '/', $rel); // normalise path separators
            $canonical = 'public/' . $rel;
            $ext       = strtolower(pathinfo($abs_path, PATHINFO_EXTENSION));

            $results[] = [
                'canonical' => $canonical,
                'abs_path'  => $abs_path,
                'mime_type' => MEDIA_MIME_MAP[$ext] ?? 'application/octet-stream',
            ];
        }
    }

    return $results;
}

/**
 * Upsert a media row.
 */
function _builder_upsert_media(string $canonical, string $mime_type): void
{
    $driver = config('database', [])['driver'] ?? 'sqlite';
    $params = [
        ':path'      => $canonical,
        ':mime_type' => $mime_type,
    ];

    if ($driver === 'sqlite') {
        db_execute(
            "INSERT OR REPLACE INTO media (path, mime_type)
             VALUES (:path, :mime_type)",
            $params
        );
    } else {
        db_execute(
            "INSERT INTO media (path, mime_type)
             VALUES (:path, :mime_type)
             ON DUPLICATE KEY UPDATE
                mime_type = VALUES(mime_type)",
            $params
        );
    }
}

/**
 * Remove media rows for files that no longer exist on disk.
 *
 * @return int Number of rows deleted
 */
function _builder_prune_media(): int
{
    $rows   = db_fetch_all("SELECT id, path FROM media");
    $pruned = 0;

    foreach ($rows as $row) {
        // Canonical 'public/foo/bar.jpg' lives at content/public/media/foo/bar.jpg
        $parts    = explode('/', (string) $row['path'], 2);
        $vis      = $parts[0];
        $rest     = $parts[1] ?? '';
        $abs_path = BASE_PATH . '/content/' . $vis . '/media/' . $rest;

        if (!is_file($abs_path)) {
            db_execute('DELETE FROM media WHERE id = :id', [':id' => (int) $row['id']]);
            $pruned++;
        }
    }

    return $pruned;
}

/** Create a directory (and parents) if it does not already exist. */
function _builder_ensure_dir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException("Cannot create cache directory: $path");
    }
}

/**
 * Render a PHP template file in an isolated closure scope.
 * Variables in $vars are extracted before the include.
 *
 * @param  array<string, mixed> $vars
 */
function _builder_render_template(string $tpl_path, array $vars): string
{
    if (!is_file($tpl_path)) {
        throw new RuntimeException("Template not found: $tpl_path");
    }

    return (function (string $_tpl, array $_vars): string {
        extract($_vars, EXTR_SKIP);
        ob_start();
        include $_tpl;
        return (string) ob_get_clean();
    })($tpl_path, $vars);
}

/**
 * Wrap rendered content in the global layout template.
 *
 * @param  array<string, mixed> $vars  Must supply: title, lang, site_title
 */
function _builder_render_layout(string $content, array $vars): string
{
    $vars['content'] = $content;
    return _builder_render_template(theme_templates_path() . '/layout.php', $vars);
}

/**
 * Resolve the template file path from the front matter template name + type.
 * Applies spec defaults: pages → page_template, posts → post_template.
 */
function _builder_resolve_template(string $template, string $type): string
{
    $name = $template !== '' ? $template : $type;
    $file = theme_templates_path() . '/' . $name . '_template.php';

    if (!is_file($file)) {
        // Fall back to the type default
        $fallback = theme_templates_path() . '/' . $type . '_template.php';
        if (is_file($fallback)) {
            return $fallback;
        }
        throw new RuntimeException("Template not found: $file");
    }

    return $file;
}

/**
 * Upsert a slug row in the DB. Works for both SQLite and MySQL.
 *
 * @param  array<string, mixed> $meta
 */
function _builder_upsert_slug(
    array  $meta,
    string $md_path,
    string $php_path,
    string $type
): void {
    $driver = config('database', [])['driver'] ?? 'sqlite';
    $params = [
        ':slug'    => $meta['slug'],
        ':lang'    => $meta['lang'],
        ':type'    => $type,
        ':md_path' => $md_path,
        ':php_path'=> $php_path,
        ':title'   => $meta['title'],
        ':date'    => $meta['date']   !== '' ? $meta['date']   : null,
        ':author'  => $meta['author'] !== '' ? $meta['author'] : null,
    ];

    // If the slug was renamed in front matter (file unchanged), remove the stale
    // row so the old URL stops resolving. Skip for synthetic rows (md_path = '').
    if ($md_path !== '') {
        db_execute(
            'DELETE FROM slugs WHERE md_path = :md_path AND lang = :lang AND slug != :slug',
            [':md_path' => $md_path, ':lang' => $meta['lang'], ':slug' => $meta['slug']]
        );
    }

    if ($driver === 'sqlite') {
        db_execute(
            "INSERT OR REPLACE INTO slugs
                (slug, lang, type, md_path, php_path, title, date, author, last_built)
             VALUES
                (:slug, :lang, :type, :md_path, :php_path, :title, :date, :author, datetime('now'))",
            $params
        );
    } else {
        db_execute(
            'INSERT INTO slugs
                (slug, lang, type, md_path, php_path, title, date, author, last_built)
             VALUES
                (:slug, :lang, :type, :md_path, :php_path, :title, :date, :author, NOW())
             ON DUPLICATE KEY UPDATE
                type       = VALUES(type),
                md_path    = VALUES(md_path),
                php_path   = VALUES(php_path),
                title      = VALUES(title),
                date       = VALUES(date),
                author     = VALUES(author),
                last_built = VALUES(last_built)',
            $params
        );
    }
}

/**
 * Sync the tags array for slug+lang:
 *   1. Ensure each tag name exists in the tags table
 *   2. Replace all tag_links for this slug+lang
 *
 * @param string[] $tags
 */
function _builder_sync_tags(string $slug, string $lang, array $tags): void
{
    $driver = config('database', [])['driver'] ?? 'sqlite';
    $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';

    // Remove existing tag links for this content item
    db_execute(
        'DELETE FROM tag_links WHERE slug = :slug AND lang = :lang',
        [':slug' => $slug, ':lang' => $lang]
    );

    // Batch-insert all tag names, then resolve IDs in one query
    $clean_tags = [];
    foreach ($tags as $tag_name) {
        $tag_name = trim((string) $tag_name);
        if ($tag_name !== '') {
            db_execute("INSERT $ignore INTO tags (name) VALUES (:name)", [':name' => $tag_name]);
            $clean_tags[] = $tag_name;
        }
    }

    if (empty($clean_tags)) {
        return;
    }

    // Resolve all tag IDs in a single query
    $placeholders = implode(',', array_fill(0, count($clean_tags), '?'));
    $tag_rows = db_fetch_all(
        "SELECT id, name FROM tags WHERE name IN ($placeholders)",
        array_values($clean_tags)
    );
    $name_to_id = array_column($tag_rows, 'id', 'name');

    foreach ($clean_tags as $tag_name) {
        $tag_id = $name_to_id[$tag_name] ?? null;
        if ($tag_id === null) {
            continue;
        }
        db_execute(
            "INSERT $ignore INTO tag_links (slug, lang, tag_id) VALUES (:slug, :lang, :tag_id)",
            [':slug' => $slug, ':lang' => $lang, ':tag_id' => (int) $tag_id]
        );
    }
}

/**
 * Prune stale DB rows and cache files for content that no longer exists on disk.
 *
 * A row is stale when its md_path is not present in the current file scan.
 * tag_links are cleaned up automatically via the FK CASCADE in the schema.
 *
 * @param  string[]                               $md_files  Absolute paths from current scan
 * @return array{pruned_db: int, pruned_cache: int}
 */
function _builder_prune(array $md_files): array
{
    $known = array_flip($md_files);   // O(1) lookup

    // Only consider rows that came from a real .md file (md_path != '') —
    // synthetic rows like tag pages have no source file and must not be pruned here.
    $rows = db_fetch_all("SELECT slug, lang, md_path, php_path FROM slugs WHERE md_path != ''");

    $pruned_db    = 0;
    $pruned_cache = 0;

    foreach ($rows as $row) {
        if (isset($known[$row['md_path']])) {
            continue;   // Source file still present — nothing to do
        }

        // Remove DB row; tag_links CASCADE handles orphan tag associations
        db_execute(
            'DELETE FROM slugs WHERE slug = :slug AND lang = :lang',
            [':slug' => $row['slug'], ':lang' => $row['lang']]
        );
        $pruned_db++;

        // Remove stale cache file
        if (isset($row['php_path']) && $row['php_path'] !== '' && is_file($row['php_path'])) {
            @unlink($row['php_path']);
            $pruned_cache++;
        }
    }

    return ['pruned_db' => $pruned_db, 'pruned_cache' => $pruned_cache];
}

/**
 * Remove tags that have no tag_links remaining.
 *
 * This covers two cases:
 *   - A post was deleted: its tag_links were CASCADE-removed with the slug row.
 *   - A tag was removed from a post's front matter: _builder_sync_tags deleted
 *     the tag_link, but the tags row is left behind until this runs.
 *
 * Must be called after Pass 1 so all tag syncs for the current build are done.
 *
 * @return int Number of orphan tag rows deleted
 */
function _builder_prune_orphan_tags(): int
{
    return db_execute(
        'DELETE FROM tags WHERE id NOT IN (SELECT DISTINCT tag_id FROM tag_links)'
    );
}

/**
 * Convert a tag name to a URL-safe slug.
 *   'News'   -> 'news'
 *   'My Tag' -> 'my-tag'
 *   'C+++'   -> 'c'
 * Returns empty string for tags that contain no alphanumeric characters.
 */
function _builder_tag_slug(string $name): string
{
    $s = strtolower($name);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-');
}

/**
 * Pass 3 - build one static tag-index page per (tag, lang) combination.
 *
 * Cache path: cache/public/{lang}/pages/tag-{tagslug}.php
 * DB row:     slug='tag-{tagslug}', md_path='' (marks it synthetic so prune skips it)
 *
 * Stale tag pages whose tags no longer exist are removed before writing.
 *
 * @return array{built: int, pruned: int}
 */
function _builder_build_tag_pages(): array
{
    $rows = db_fetch_all(
        "SELECT DISTINCT t.name AS tag_name, s.lang
           FROM tags t
           JOIN tag_links tl ON tl.tag_id = t.id
           JOIN slugs     s  ON s.slug = tl.slug AND s.lang = tl.lang
          WHERE s.md_path != ''
          ORDER BY s.lang, t.name"
    );

    // Build lookup of all slugs we are about to write, detecting collisions
    $will_generate = [];
    $slug_to_tag   = []; // track which tag name owns each slug per lang
    foreach ($rows as $row) {
        $tag_slug = _builder_tag_slug($row['tag_name']);
        if ($tag_slug === '') {
            continue; // skip tags with no alphanumeric characters
        }
        $slug = 'tag-' . $tag_slug;
        $key  = $slug . '::' . $row['lang'];
        if (isset($will_generate[$key]) && $slug_to_tag[$key] !== $row['tag_name']) {
            error_log('[TeenyTinyCMS Builder] Tag slug collision: "' . $slug_to_tag[$key] . '" and "' . $row['tag_name'] . '" both map to slug "' . $slug . '" for lang=' . $row['lang']);
        }
        $will_generate[$key] = true;
        $slug_to_tag[$key]   = $row['tag_name'];
    }

    // Remove stale tag pages not in the new set
    $existing = db_fetch_all(
        "SELECT slug, lang, php_path FROM slugs WHERE md_path = '' AND slug LIKE 'tag-%'"
    );
    $pruned = 0;
    foreach ($existing as $ex) {
        if (!isset($will_generate[$ex['slug'] . '::' . $ex['lang']])) {
            db_execute(
                'DELETE FROM slugs WHERE slug = :slug AND lang = :lang',
                [':slug' => $ex['slug'], ':lang' => $ex['lang']]
            );
            if ($ex['php_path'] !== '' && is_file($ex['php_path'])) {
                @unlink($ex['php_path']);
            }
            $pruned++;
        }
    }

    // Generate each tag page
    $tpl_path = theme_templates_path() . '/tag_template.php';
    $built    = 0;

    foreach ($rows as $row) {
        $tag_name = $row['tag_name'];
        $lang     = $row['lang'];
        $tag_slug = _builder_tag_slug($tag_name);
        if ($tag_slug === '') {
            continue; // skip tags with no alphanumeric characters
        }
        $slug     = 'tag-' . $tag_slug;
        $php_path = BASE_PATH . '/cache/public/' . $lang . '/pages/' . $slug . '.php';

        $posts = get_posts_by_tag($tag_name, $lang);

        $meta = [
            'slug'     => $slug,
            'lang'     => $lang,
            'title'    => 'Posts tagged: ' . $tag_name,
            'template' => '',
            'tags'     => [],
            'date'     => '',
            'author'   => '',
        ];

        $content = _builder_render_template($tpl_path, [
            'meta'     => $meta,
            'lang'     => $lang,
            'tag_name' => $tag_name,
            'posts'    => $posts,
        ]);

        $full_html = _builder_render_layout($content, [
            'title'      => $meta['title'],
            'lang'       => $lang,
            'slug'       => $slug,
            'type'       => 'page',
            'site_title' => config('site_title', 'TeenyTinyCMS'),
        ]);

        _builder_ensure_dir(dirname($php_path));
        if (file_put_contents($php_path, $full_html) === false) {
            throw new RuntimeException("Failed to write tag cache: $php_path");
        }

        _builder_upsert_slug($meta, '', $php_path, 'page');
        $built++;
    }

    return ['built' => $built, 'pruned' => $pruned];
}

/**
 * Pass 4 – build static pagination pages for the blog index.
 *
 * Page 1 is the normal blog.{lang}.md cache file (already built in Pass 2).
 * This function generates page 2, 3, … as:
 *   cache/public/{lang}/pages/blog-page-{n}.php
 * with synthetic DB rows (md_path='') so the router can resolve them.
 *
 * Stale pagination pages (e.g. when post count decreases) are pruned.
 *
 * @return array{built: int, pruned: int}
 */
function _builder_build_blog_pagination(): array
{
    $per_page = (int) config('blog_per_page', 9);
    if ($per_page < 1) {
        $per_page = 9;
    }

    // Discover which languages have a blog page
    $blog_rows = db_fetch_all(
        "SELECT lang FROM slugs WHERE slug = 'blog' AND type = 'page' AND md_path != ''"
    );

    $will_generate = [];
    $built  = 0;
    $pruned = 0;

    foreach ($blog_rows as $blog_row) {
        $lang        = $blog_row['lang'];
        $total_posts = count_posts($lang);
        $total_pages = (int) max(1, ceil($total_posts / $per_page));

        // Fetch the blog page meta from DB for title / template reference
        $blog_meta = db_fetch_one(
            "SELECT title FROM slugs WHERE slug = 'blog' AND lang = :lang AND type = 'page'",
            [':lang' => $lang]
        );
        $blog_title = $blog_meta['title'] ?? 'Blog';

        // Generate pages 2 … N (page 1 is the original blog cache file)
        for ($page = 2; $page <= $total_pages; $page++) {
            $slug     = 'blog-page-' . $page;
            $php_path = BASE_PATH . '/cache/public/' . $lang . '/pages/' . $slug . '.php';

            $posts = get_posts_paginated($per_page, $lang, $page);

            $meta = [
                'slug'     => $slug,
                'lang'     => $lang,
                'title'    => $blog_title . ' – Page ' . $page,
                'template' => 'blog',
                'tags'     => [],
                'date'     => '',
                'author'   => '',
            ];

            $tpl_path = _builder_resolve_template('blog', 'page');

            $content = _builder_render_template($tpl_path, [
                'meta'         => $meta,
                'html'         => '',
                'lang'         => $lang,
                'posts'        => $posts,
                'current_page' => $page,
                'total_pages'  => $total_pages,
            ]);

            $full_html = _builder_render_layout($content, [
                'title'      => $meta['title'],
                'lang'       => $lang,
                'slug'       => 'blog',
                'type'       => 'page',
                'site_title' => config('site_title', 'TeenyTinyCMS'),
            ]);

            _builder_ensure_dir(dirname($php_path));
            if (file_put_contents($php_path, $full_html) === false) {
                throw new RuntimeException("Failed to write blog pagination cache: $php_path");
            }

            _builder_upsert_slug($meta, '', $php_path, 'page');
            $will_generate[$slug . '::' . $lang] = true;
            $built++;
        }
    }

    // Prune stale blog pagination pages that are no longer needed
    $existing = db_fetch_all(
        "SELECT slug, lang, php_path FROM slugs WHERE md_path = '' AND slug LIKE 'blog-page-%'"
    );
    foreach ($existing as $ex) {
        if (!isset($will_generate[$ex['slug'] . '::' . $ex['lang']])) {
            db_execute(
                'DELETE FROM slugs WHERE slug = :slug AND lang = :lang',
                [':slug' => $ex['slug'], ':lang' => $ex['lang']]
            );
            if ($ex['php_path'] !== '' && is_file($ex['php_path'])) {
                @unlink($ex['php_path']);
            }
            $pruned++;
        }
    }

    return ['built' => $built, 'pruned' => $pruned];
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Pass 5: build a search-index JSON file per language.
 *
 * Clears the search/ directory, then reads every post's markdown source,
 * strips HTML, and writes search/{lang}/index.json with slug, title,
 * date, tags, and a plain-text excerpt (~300 chars) per entry.
 *
 * @return array{built: int}
 */
function _builder_build_search_index(): array
{
    // Clear previous search index files
    $search_dir = BASE_PATH . '/search';
    if (is_dir($search_dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($search_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->getFilename() === '.gitkeep') continue;
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }

    $langs = db_fetch_all("SELECT DISTINCT lang FROM slugs WHERE type = 'post' ORDER BY lang ASC");
    $built = 0;

    foreach ($langs as $row) {
        $lang  = $row['lang'];
        $posts = db_fetch_all(
            'SELECT slug, lang, title, date, author, md_path
               FROM slugs
              WHERE type = :type AND lang = :lang
              ORDER BY date DESC, slug ASC',
            [':type' => 'post', ':lang' => $lang]
        );

        $entries = [];
        foreach ($posts as $post) {
            // Read and strip the markdown HTML for a plain-text excerpt
            $excerpt = '';
            if ($post['md_path'] !== '' && is_file($post['md_path'])) {
                $parsed  = parse_markdown_file($post['md_path']);
                $plain   = trim(preg_replace('/\s+/', ' ', strip_tags($parsed['html'])));
                $excerpt = mb_strlen($plain) > 300
                    ? mb_substr($plain, 0, 300) . '…'
                    : $plain;
            }

            // Fetch tags for this post
            $tag_rows = db_fetch_all(
                'SELECT t.name AS tag FROM tag_links tl
                   JOIN tags t ON t.id = tl.tag_id
                  WHERE tl.slug = :slug AND tl.lang = :lang',
                [':slug' => $post['slug'], ':lang' => $lang]
            );
            $tags = array_column($tag_rows, 'tag');

            $entries[] = [
                'slug'    => $post['slug'],
                'title'   => $post['title'],
                'date'    => $post['date'] ?? '',
                'author'  => $post['author'] ?? '',
                'tags'    => $tags,
                'excerpt' => $excerpt,
            ];
        }

        $json_path = BASE_PATH . '/search/' . $lang . '/index.json';
        _builder_ensure_dir(dirname($json_path));

        $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (file_put_contents($json_path, $json) === false) {
            throw new RuntimeException("Failed to write search index: $json_path");
        }
        $built++;
    }

    return ['built' => $built];
}

/**
 * Build all content files found under /content.
 *
 * Pass 0: prune DB rows and cache files for deleted/renamed sources.
 * Pass 1: sync every file's metadata to the DB.
 * Pass 2: render every file and write its cache PHP file.
 * Pass 3: build one static tag-index page per (tag, lang) combination.
 * Pass 4: build static blog pagination pages (page 2, 3, …).
 * Pass 5: build search-index JSON per language.
 *
 * @return array{built: int, errors: int, pruned_db: int, pruned_cache: int, media_synced: int, media_pruned: int, tags_pruned: int, files: string[]}
 */
function build_all(): array
{
    $md_files = _builder_collect_files();
    $stats    = ['built' => 0, 'errors' => 0, 'pruned_db' => 0, 'pruned_cache' => 0, 'media_synced' => 0, 'media_pruned' => 0, 'tags_pruned' => 0, 'files' => []];

    // ── Pass 0: prune stale rows + cache files ───────────────────────────────
    try {
        $pruned = _builder_prune($md_files);
        $stats['pruned_db']    = $pruned['pruned_db'];
        $stats['pruned_cache'] = $pruned['pruned_cache'];
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 0] prune failed: ' . $e->getMessage());
    }

    try {
        $stats['media_pruned'] = _builder_prune_media();
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 0] media prune failed: ' . $e->getMessage());
    }

    // ── Pass 1: metadata sync ────────────────────────────────────────────────
    // Store parsed results to avoid re-parsing in Pass 2
    $parsed_cache = [];
    foreach ($md_files as $md_path) {
        try {
            $parsed   = parse_markdown_file($md_path);
            $meta     = $parsed['meta'];
            $type     = type_from_path($md_path);

            $meta = _builder_apply_filename_meta($meta, $md_path);

            if (!is_valid_slug($meta['slug'])) {
                throw new RuntimeException("Invalid slug '{$meta['slug']}' — only lowercase letters, digits, hyphens, and forward slashes are allowed.");
            }

            $php_path = cache_path_for($md_path, $meta['slug']);

            $parsed_cache[$md_path] = ['meta' => $meta, 'html' => $parsed['html'], 'type' => $type, 'php_path' => $php_path];

            _builder_upsert_slug($meta, $md_path, $php_path, $type);
            if ($type === 'post') {
                _builder_sync_tags($meta['slug'], $meta['lang'], $meta['tags']);
            }
        } catch (Throwable $e) {
            $stats['errors']++;
            error_log('[TeenyTinyCMS Builder][Pass 1] ' . $md_path . ': ' . $e->getMessage());
        }
    }

    // ── Pass 1b: sync local media files ────────────────────────────────────────
    foreach (_builder_collect_media_files() as $mf) {
        try {
            _builder_upsert_media($mf['canonical'], $mf['mime_type']);
            $stats['media_synced']++;
        } catch (Throwable $e) {
            $stats['errors']++;
            error_log('[TeenyTinyCMS Builder][Pass 1b] ' . $mf['canonical'] . ': ' . $e->getMessage());
        }
    }

    // ── Prune orphan tags (after all syncs, before tag pages are built) ─────────
    try {
        $stats['tags_pruned'] = _builder_prune_orphan_tags();
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 1] orphan tag prune failed: ' . $e->getMessage());
    }

    // ── Pass 2: render + write cache ─────────────────────────────────────────
    foreach ($md_files as $md_path) {
        try {
            if (isset($parsed_cache[$md_path])) {
                $c = $parsed_cache[$md_path];
                _builder_write_cache($c['meta'], $c['html'], $c['type'], $c['php_path']);
            } else {
                build_file($md_path);
            }
            $stats['built']++;
            $stats['files'][] = $md_path;
        } catch (Throwable $e) {
            $stats['errors']++;
            error_log('[TeenyTinyCMS Builder][Pass 2] ' . $md_path . ': ' . $e->getMessage());
        }
    }
    unset($parsed_cache);

    // ── Pass 3: tag index pages ──────────────────────────────────────────────
    try {
        $tag_stats = _builder_build_tag_pages();
        $stats['built']        += $tag_stats['built'];
        $stats['pruned_db']    += $tag_stats['pruned'];
        $stats['pruned_cache'] += $tag_stats['pruned'];
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 3] tag pages failed: ' . $e->getMessage());
    }

    // ── Pass 4: blog pagination pages ────────────────────────────────────────
    try {
        $pag_stats = _builder_build_blog_pagination();
        $stats['built']        += $pag_stats['built'];
        $stats['pruned_db']    += $pag_stats['pruned'];
        $stats['pruned_cache'] += $pag_stats['pruned'];
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 4] blog pagination failed: ' . $e->getMessage());
    }

    // ── Pass 5: build search index JSON per language ─────────────────────────
    try {
        $search_stats = _builder_build_search_index();
        $stats['built'] += $search_stats['built'];
    } catch (Throwable $e) {
        $stats['errors']++;
        error_log('[TeenyTinyCMS Builder][Pass 5] search index failed: ' . $e->getMessage());
    }

    return $stats;
}

/**
 * Render pre-parsed content and write to cache.
 * Used by build_all() to avoid re-parsing files already processed in Pass 1.
 */
function _builder_write_cache(array $meta, string $html, string $type, string $php_path): void
{
    $tpl_path = _builder_resolve_template($meta['template'], $type);

    $tpl_vars = [
        'meta' => $meta,
        'html' => $html,
        'lang' => $meta['lang'],
    ];

    // Inject pagination data for blog page 1
    if ($meta['template'] === 'blog') {
        $per_page    = (int) config('blog_per_page', 9);
        if ($per_page < 1) { $per_page = 9; }
        $total_posts = count_posts($meta['lang']);
        $total_pages = (int) max(1, ceil($total_posts / $per_page));

        $tpl_vars['posts']        = get_posts_paginated($per_page, $meta['lang'], 1);
        $tpl_vars['current_page'] = 1;
        $tpl_vars['total_pages']  = $total_pages;
    }

    $content = _builder_render_template($tpl_path, $tpl_vars);

    $full_html = _builder_render_layout($content, [
        'title'      => $meta['title'],
        'lang'       => $meta['lang'],
        'slug'       => $meta['slug'],
        'type'       => $type,
        'site_title' => config('site_title', 'TeenyTinyCMS'),
    ]);

    _builder_ensure_dir(dirname($php_path));
    if (file_put_contents($php_path, $full_html) === false) {
        throw new RuntimeException("Failed to write cache file: $php_path");
    }
}

/**
 * Build a single Markdown file:
 *   parse → render content template → wrap with layout → write cache PHP file
 */
function build_file(string $md_path): void
{
    $parsed   = parse_markdown_file($md_path);
    $meta     = $parsed['meta'];
    $html     = $parsed['html'];
    $type     = type_from_path($md_path);

    $meta = _builder_apply_filename_meta($meta, $md_path);

    if (!is_valid_slug($meta['slug'])) {
        throw new RuntimeException("Invalid slug '{$meta['slug']}' — only lowercase letters, digits, hyphens, and forward slashes are allowed.");
    }

    $php_path = cache_path_for($md_path, $meta['slug']);
    _builder_write_cache($meta, $html, $type, $php_path);
}

// ── CLI entry point ───────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', dirname(__DIR__));
    }
    require_once BASE_PATH . '/app/config_loader.php';
    require_once BASE_PATH . '/app/db.php';
    require_once BASE_PATH . '/app/utils.php';
    require_once BASE_PATH . '/app/markdown.php';
    require_once BASE_PATH . '/app/template_helpers.php';
    require_once BASE_PATH . '/app/content_helpers.php';

    echo 'TeenyTinyCMS Builder – starting...' . PHP_EOL;
    $start = microtime(true);
    $stats = build_all();
    $elapsed = round(microtime(true) - $start, 3);

    echo "Built:   {$stats['built']} file(s)" . PHP_EOL;
    echo "Pruned:  {$stats['pruned_db']} DB row(s), {$stats['pruned_cache']} cache file(s), {$stats['tags_pruned']} orphan tag(s)" . PHP_EOL;
    echo "Media:   {$stats['media_synced']} synced, {$stats['media_pruned']} pruned" . PHP_EOL;
    echo "Errors:  {$stats['errors']}" . PHP_EOL;
    echo "Time:    {$elapsed}s" . PHP_EOL;

    if (!empty($stats['files'])) {
        echo PHP_EOL . 'Files built:' . PHP_EOL;
        foreach ($stats['files'] as $f) {
            echo '  ' . str_replace(BASE_PATH . '/', '', $f) . PHP_EOL;
        }
    }
}
