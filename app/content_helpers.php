<?php
/**
 * TeenyTinyCMS – Content query helpers
 *
 * Query the slugs/tags tables. Used by templates at build time
 * and optionally at runtime for dynamic sections.
 */

declare(strict_types=1);

/**
 * Return the $limit most recent public posts for a given language,
 * ordered by date descending.
 *
 * @return array<int, array<string, mixed>>
 */
function get_latest_posts(int $limit, string $lang): array
{
    return db_fetch_all(
        'SELECT slug, lang, title, date, author, php_path
           FROM slugs
          WHERE type = :type
            AND lang = :lang
          ORDER BY date DESC, slug ASC
          LIMIT :limit',
        [':type' => 'post', ':lang' => $lang, ':limit' => $limit]
    );
}

/**
 * Return all public posts carrying a given tag in the specified language.
 *
 * @return array<int, array<string, mixed>>
 */
function get_posts_by_tag(string $tag, string $lang): array
{
    return db_fetch_all(
        'SELECT s.slug, s.lang, s.title, s.date, s.author, s.php_path
           FROM slugs s
           JOIN tag_links tl ON tl.slug = s.slug AND tl.lang = s.lang
           JOIN tags t       ON t.id    = tl.tag_id
          WHERE s.type = :type
            AND s.lang = :lang
            AND t.name = :tag
          ORDER BY s.date DESC',
        [':type' => 'post', ':lang' => $lang, ':tag' => $tag]
    );
}

/**
 * Return metadata for a single page or post by slug + language.
 * Returns null if not found.
 *
 * @return array<string, mixed>|null
 */
function get_page_meta(string $slug, string $lang): ?array
{
    return db_fetch_one(
        'SELECT * FROM slugs WHERE slug = :slug AND lang = :lang',
        [':slug' => $slug, ':lang' => $lang]
    );
}

/**
 * Return the total number of published posts for a given language.
 */
function count_posts(string $lang): int
{
    $row = db_fetch_one(
        'SELECT COUNT(*) AS cnt FROM slugs WHERE type = :type AND lang = :lang',
        [':type' => 'post', ':lang' => $lang]
    );
    return (int) ($row['cnt'] ?? 0);
}

/**
 * Return a page of posts for a given language, ordered by date descending.
 *
 * @return array<int, array<string, mixed>>
 */
function get_posts_paginated(int $per_page, string $lang, int $page = 1): array
{
    $offset = ($page - 1) * $per_page;
    return db_fetch_all(
        'SELECT slug, lang, title, date, author, php_path
           FROM slugs
          WHERE type = :type
            AND lang = :lang
          ORDER BY date DESC, slug ASC
          LIMIT :limit OFFSET :offset',
        [':type' => 'post', ':lang' => $lang, ':limit' => $per_page, ':offset' => $offset]
    );
}

/**
 * Return all language codes that have a published version of a given slug+type.
 * Used by the language switcher to show only languages that actually exist.
 *
 * @return string[]  e.g. ['de', 'en', 'fr']
 */
function get_slug_languages(string $slug, string $type): array
{
    $rows = db_fetch_all(
        'SELECT lang FROM slugs WHERE slug = :slug AND type = :type ORDER BY lang ASC',
        [':slug' => $slug, ':type' => $type]
    );
    return array_column($rows, 'lang');
}
