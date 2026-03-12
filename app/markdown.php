<?php
/**
 * TeenyTinyCMS – Markdown parsing layer
 *
 * Public API:
 *   parse_markdown_file(string $path): array   – read + parse a .md file
 *   parse_markdown_string(string $raw): array  – parse raw string (for testing)
 *
 * Both return:
 *   [
 *     'meta' => [            // front matter fields
 *       'slug'     => string,
 *       'lang'     => string,
 *       'title'    => string,
 *       'template' => string,
 *       'tags'     => string[],
 *       'date'     => string,
 *       'author'   => string,
 *     ],
 *     'html' => string,      // parsed HTML body
 *   ]
 */

declare(strict_types=1);

require_once BASE_PATH . '/app/parsers/Parsedown.php';
require_once BASE_PATH . '/app/parsers/ParsedownExtra.php';

// ── Parsedown singleton ───────────────────────────────────────────────────────

function _parsedown(): ParsedownExtra
{
    static $pd = null;
    if ($pd === null) {
        $pd = new ParsedownExtra();
        // SECURITY: safe mode is OFF because content is authored by trusted editors.
        // If you ever accept user-submitted Markdown, you MUST call $pd->setSafeMode(true)
        // to prevent XSS via raw HTML/script injection.
        $pd->setSafeMode(false);
    }
    return $pd;
}

// ── Front matter parser ───────────────────────────────────────────────────────

/**
 * Split raw file content into front matter string + body string.
 *
 * Expects the file to begin with "---\n" and end the block with "---\n".
 * Returns ['front' => string, 'body' => string].
 * If no front matter is found, 'front' is '' and 'body' is the whole content.
 *
 * @return array{front: string, body: string}
 */
function _split_front_matter(string $raw): array
{
    // Normalise line endings
    $raw = str_replace("\r\n", "\n", $raw);

    if (!str_starts_with($raw, "---\n")) {
        return ['front' => '', 'body' => $raw];
    }

    // Find closing ---
    $closing = strpos($raw, "\n---\n", 4);
    if ($closing === false) {
        // Try end-of-file ---
        if (str_ends_with(rtrim($raw), "\n---")) {
            $closing = strrpos($raw, "\n---");
            $front   = substr($raw, 4, $closing - 4);
            $body    = '';
            return ['front' => $front, 'body' => $body];
        }
        return ['front' => '', 'body' => $raw];
    }

    $front = substr($raw, 4, $closing - 4);
    $body  = substr($raw, $closing + 5); // skip "\n---\n"
    return ['front' => $front, 'body' => ltrim($body, "\n")];
}

/**
 * Parse a simple YAML-subset front matter string into an associative array.
 *
 * Supported types:
 *   scalar:  key: value
 *   array:   tags: [foo, bar, baz]
 *   array:   tags:
 *              - foo
 *              - bar
 *
 * Does NOT use a full YAML parser intentionally – only the spec-defined
 * front matter fields are needed and the format is controlled.
 *
 * @return array<string, mixed>
 */
function _parse_front_matter_string(string $front): array
{
    $meta  = [];
    $lines = explode("\n", $front);
    $i     = 0;
    $count = count($lines);

    while ($i < $count) {
        $line = $lines[$i];

        // Skip blank lines
        if (trim($line) === '') {
            $i++;
            continue;
        }

        // key: value  or  key: [inline array]
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/', $line, $m)) {
            $key   = strtolower(trim($m[1]));
            $value = trim($m[2]);

            if ($value === '') {
                // Possible block sequence on next lines
                $items = [];
                while (isset($lines[$i + 1]) && preg_match('/^\s+-\s+(.+)$/', $lines[$i + 1], $sm)) {
                    $items[] = trim($sm[1]);
                    $i++;
                }
                $meta[$key] = $items;
            } elseif (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                // Inline array: [foo, bar]
                $inner      = substr($value, 1, -1);
                $meta[$key] = array_values(
                    array_filter(
                        array_map('trim', explode(',', $inner)),
                        fn(string $s) => $s !== ''
                    )
                );
            } else {
                // Scalar – strip optional surrounding quotes
                $meta[$key] = trim($value, '"\'');
            }
        }

        $i++;
    }

    return $meta;
}

/**
 * Merge parsed front matter with safe defaults for all known fields.
 *
 * @param  array<string, mixed> $raw
 * @return array<string, mixed>
 */
function _normalise_meta(array $raw): array
{
    return [
        'slug'     => (string) ($raw['slug']     ?? ''),
        'lang'     => (string) ($raw['lang']     ?? 'en'),
        'title'    => (string) ($raw['title']    ?? ''),
        'template' => (string) ($raw['template'] ?? ''),
        'tags'     => (array)  ($raw['tags']     ?? []),
        'date'     => (string) ($raw['date']     ?? ''),
        'author'   => (string) ($raw['author']   ?? ''),
    ];
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Parse a Markdown file on disk.
 *
 * @return array{meta: array<string, mixed>, html: string}
 * @throws RuntimeException if the file cannot be read
 */
function parse_markdown_file(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("Cannot read Markdown file: $path");
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Failed to read file: $path");
    }

    return parse_markdown_string($raw);
}

/**
 * Parse a raw Markdown string (useful for testing or in-memory content).
 *
 * @return array{meta: array<string, mixed>, html: string}
 */
function parse_markdown_string(string $raw): array
{
    ['front' => $front, 'body' => $body] = _split_front_matter($raw);

    $rawMeta = $front !== '' ? _parse_front_matter_string($front) : [];
    $meta    = _normalise_meta($rawMeta);
    $html    = _parsedown()->text($body);

    return ['meta' => $meta, 'html' => $html];
}
