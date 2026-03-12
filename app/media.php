<?php
/**
 * TeenyTinyCMS – Media abstraction layer
 *
 * Content references media using canonical paths such as:
 *   public/image.jpg      (stored as  /content/public/media/image.jpg)
 *
 * The router calls resolve_media($canonical_path) for any /media/public/* request.
 * Resolution:
 *   1. Look up path in the media DB table
 *   2. Serve file bytes with correct MIME headers
 *
 * Additional helpers:
 *   media_url($canonical_path)  – returns the public /media/… URL for use in
 *                                  templates and cached PHP output
 */

declare(strict_types=1);

// ── MIME type map ─────────────────────────────────────────────────────────────

const MEDIA_MIME_MAP = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg',
    'ogg'  => 'audio/ogg',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain',
];

function _media_mime(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return MEDIA_MIME_MAP[$ext] ?? 'application/octet-stream';
}

// ── Local file delivery ───────────────────────────────────────────────────────

function _media_serve_local(string $file_path, string $mime): void
{
    if (!is_file($file_path) || !is_readable($file_path)) {
        http_response_code(404);
        exit('Media file not found.');
    }

    $size = filesize($file_path);
    if ($size === false) {
        http_response_code(500);
        exit('Cannot determine file size.');
    }

    header('Content-Type: '    . $mime);
    header('Content-Length: '  . $size);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');

    // Support range requests for video/audio
    if (isset($_SERVER['HTTP_RANGE'])) {
        _media_serve_range($file_path, $mime, $size);
        return;
    }

    readfile($file_path);
    exit;
}

function _media_serve_range(string $path, string $mime, int $total): void
{
    [$unit, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2) + ['', ''];
    if (trim($unit) !== 'bytes') {
        http_response_code(416);
        exit;
    }

    [$start_str, $end_str] = explode('-', $range, 2) + ['', ''];

    if ($start_str === '' && $end_str !== '') {
        // Suffix range: -500 means last 500 bytes
        $suffix = (int) $end_str;
        if ($suffix <= 0 || $suffix > $total) {
            http_response_code(416);
            header('Content-Range: bytes */' . $total);
            exit;
        }
        $start = $total - $suffix;
        $end   = $total - 1;
    } else {
        $start = $start_str !== '' ? (int) $start_str : 0;
        $end   = $end_str   !== '' ? (int) $end_str   : $total - 1;
    }

    if ($start < 0 || $start > $end || $end >= $total) {
        http_response_code(416);
        header('Content-Range: bytes */' . $total);
        exit;
    }

    http_response_code(206);
    header('Content-Type: '   . $mime);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $total);
    header('Content-Length: ' . ($end - $start + 1));
    header('Accept-Ranges: bytes');

    $fh = fopen($path, 'rb');
    if ($fh === false) {
        http_response_code(500);
        exit('Cannot open file for range request.');
    }
    fseek($fh, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fh)) {
        $chunk      = min(8192, $remaining);
        $remaining -= $chunk;
        echo fread($fh, $chunk);
    }
    fclose($fh);
    exit;
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Main entry point called by the router for /media/public/{filename}.
 *
 * $canonical_path examples:
 *   'public/image.jpg'
 *   'public/2026-03-01-my-cat/cat.png'
 */
function resolve_media(string $canonical_path): void
{
    // Prevent path traversal
    $canonical_path = ltrim($canonical_path, '/');
    if (str_contains($canonical_path, '..') || str_contains($canonical_path, "\0")) {
        http_response_code(400);
        exit('Invalid media path.');
    }

    // Look up in DB
    $row = db_fetch_one(
        'SELECT path, mime_type FROM media WHERE path = :p',
        [':p' => $canonical_path]
    );

    if ($row === null) {
        http_response_code(404);
        exit('Media not found.');
    }

    $mime = $row['mime_type'] !== '' && $row['mime_type'] !== null
        ? (string) $row['mime_type']
        : _media_mime($canonical_path);

    // Canonical 'public/foo/bar.jpg' lives at content/public/media/foo/bar.jpg
    $parts     = explode('/', $canonical_path, 2);
    $vis       = $parts[0];
    $rest      = $parts[1] ?? '';
    $file_path = BASE_PATH . '/content/' . $vis . '/media/' . $rest;
    _media_serve_local($file_path, $mime);
}

/**
 * Return the public URL for a media asset (for use in templates).
 *
 * media_url('public/image.jpg')   → '/media/public/image.jpg'
 */
function media_url(string $canonical_path): string
{
    return '/media/' . ltrim($canonical_path, '/');
}
