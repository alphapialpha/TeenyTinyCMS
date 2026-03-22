<?php
/**
 * TeenyTinyCMS Admin – Dashboard
 *
 * Minimal first version: shows site stats and a rebuild trigger.
 * Future expansion: posts, pages, media, users management screens.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

auth_start();
require_login();

$user = current_user();

// ── Rebuild trigger ──────────────────────────────────────────────────────────
$build_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rebuild') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $build_message = 'error:Invalid CSRF token.';
    } else {
        require_once BASE_PATH . '/app/markdown.php';
        require_once BASE_PATH . '/app/utils.php';
        require_once BASE_PATH . '/app/builder.php';
        try {
            $t0    = microtime(true);
            $stats = build_all();
            $elapsed = round((microtime(true) - $t0) * 1000);
            $build_message = 'success:Built ' . $stats['built'] . ' file(s). Pruned ' . ($stats['pruned_db'] ?? 0) . ' DB row(s), ' . ($stats['tags_pruned'] ?? 0) . ' orphan tag(s). Errors: ' . $stats['errors'] . '. Time: ' . $elapsed . ' ms.';
        } catch (Throwable $e) {
            $build_message = 'error:Build failed: ' . $e->getMessage();
        }
    }
}

// ── Theme switcher ───────────────────────────────────────────────────────────
/**
 * A valid theme name is a non-empty string of lowercase letters, digits,
 * hyphens, and underscores. This prevents path traversal and ensures the
 * name is safe to write into config.php as a single-quoted string value.
 */
function _admin_valid_theme_name(string $name): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $name);
}

/** Return all valid theme names found as subdirectories of /themes/. */
function _admin_list_themes(): array
{
    $themes_dir = BASE_PATH . '/themes';
    if (!is_dir($themes_dir)) {
        return [];
    }
    $themes = [];
    foreach (scandir($themes_dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($themes_dir . '/' . $entry) && _admin_valid_theme_name($entry)) {
            $themes[] = $entry;
        }
    }
    sort($themes);
    return $themes;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_theme') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $build_message = 'error:Invalid CSRF token.';
    } else {
        $new_theme = trim($_POST['theme'] ?? '');
        if (!_admin_valid_theme_name($new_theme)) {
            $build_message = 'error:Invalid theme name.';
        } elseif (!is_dir(BASE_PATH . '/themes/' . $new_theme)) {
            $build_message = 'error:Theme folder not found.';
        } else {
            // Rewrite active_theme in config.php
            $cfg_file = BASE_PATH . '/config/config.php';
            $cfg_src  = file_get_contents($cfg_file);
            $updated  = preg_replace(
                "/('active_theme'\s*=>\s*)'[^']*'/",
                "'active_theme' => '" . $new_theme . "'",
                $cfg_src,
                1
            );
            if ($updated === null || $updated === $cfg_src && config('active_theme', 'default') !== $new_theme) {
                $build_message = 'error:Could not update config.php — is active_theme key present?';
            } elseif (file_put_contents($cfg_file, $updated) === false) {
                $build_message = 'error:Could not write config.php.';
            } else {
                // Reload config in memory so the rebuild uses the new theme
                $GLOBALS['_teenytinycms_config']['active_theme'] = $new_theme;
                require_once BASE_PATH . '/app/markdown.php';
                require_once BASE_PATH . '/app/utils.php';
                require_once BASE_PATH . '/app/builder.php';
                try {
                    $t0    = microtime(true);
                    $stats = build_all();
                    $elapsed = round((microtime(true) - $t0) * 1000);
                    $build_message = 'success:Theme switched to "' . $new_theme . '".<br>Built ' . $stats['built'] . ' file(s). Pruned ' . ($stats['pruned_db'] ?? 0) . ' DB row(s), ' . ($stats['tags_pruned'] ?? 0) . ' orphan tag(s). Errors: ' . $stats['errors'] . '. Time: ' . $elapsed . ' ms.';
                } catch (Throwable $e) {
                    $build_message = 'error:Theme saved but rebuild failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$available_themes = _admin_list_themes();
$current_theme    = config('active_theme', 'default');

// ── Stats ────────────────────────────────────────────────────────────────────
$total_pages = db_fetch_one('SELECT COUNT(*) AS n FROM slugs WHERE type = :t AND md_path != :empty', [':t' => 'page', ':empty' => ''])['n'] ?? 0;
$total_posts = db_fetch_one('SELECT COUNT(*) AS n FROM slugs WHERE type = :t', [':t' => 'post'])['n'] ?? 0;
$total_tags  = db_fetch_one('SELECT COUNT(*) AS n FROM tags')['n'] ?? 0;
$total_media = db_fetch_one('SELECT COUNT(*) AS n FROM media')['n'] ?? 0;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Parse build message
[$msg_type, $msg_text] = $build_message !== '' ? explode(':', $build_message, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – TeenyTinyCMS Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; margin: 0; }
        .topbar { background: #1e293b; color: #e2e8f0; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #94a3b8; text-decoration: none; font-size: .875rem; }
        .topbar a:hover { color: #fff; }
        .content { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.5rem; margin-top: 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; text-align: center; }
        .stat__num { font-size: 2rem; font-weight: 700; color: #3b82f6; }
        .stat__label { font-size: .8rem; color: #64748b; margin-top: .25rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h2 { margin-top: 0; font-size: 1.1rem; }
        .btn { padding: .55rem 1.25rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-size: .9rem; cursor: pointer; }
        .btn:hover { background: #2563eb; }
        .btn--secondary { background: #64748b; }
        .btn--secondary:hover { background: #475569; }
        .alert { padding: .75rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: .9rem; }
        .alert--success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .alert--error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        select { padding: .45rem .6rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: .9rem; margin-right: .5rem; background: #fff; }
    </style>
</head>
<body>
<div class="topbar">
    <strong style="color:#fff">TeenyTinyCMS Admin</strong>
    <span>
        Logged in as <strong style="color:#fff"><?= e($user['username'] ?? '') ?></strong>
        &nbsp;·&nbsp;
        <a href="<?= e(BASE_URL) ?>/admin/logout.php">Sign out</a>
        &nbsp;·&nbsp;
        <a href="<?= e(BASE_URL) ?>/">View site &rarr;</a>
    </span>
</div>
<div class="content">
    <h1>Dashboard</h1>

    <?php if ($msg_text !== ''): ?>
        <div class="alert alert--<?= $msg_type === 'success' ? 'success' : 'error' ?>">
            <?= nl2br(e(str_replace('<br>', "\n", $msg_text))) ?>
        </div>
    <?php endif ?>

    <div class="stats">
        <div class="stat"><div class="stat__num"><?= (int) $total_pages ?></div><div class="stat__label">Pages</div></div>
        <div class="stat"><div class="stat__num"><?= (int) $total_posts ?></div><div class="stat__label">Posts</div></div>
        <div class="stat"><div class="stat__num"><?= (int) $total_tags ?></div><div class="stat__label">Tags</div></div>
        <div class="stat"><div class="stat__num"><?= (int) $total_media ?></div><div class="stat__label">Media</div></div>
    </div>

    <div class="card">
        <h2>Active Theme</h2>
        <p style="color:#64748b;font-size:.9rem;margin-top:0">Themes are folders inside <code>/themes/</code>. Switching rebuilds the cache automatically.</p>
        <?php if (count($available_themes) <= 1): ?>
            <p style="font-size:.9rem;color:#64748b">Only one theme installed (<strong><?= e($current_theme) ?></strong>). Drop a folder into <code>/themes/</code> to add more.</p>
        <?php else: ?>
        <form method="post" action="<?= e(BASE_URL) ?>/admin/index.php" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="set_theme">
            <select name="theme">
                <?php foreach ($available_themes as $theme): ?>
                    <option value="<?= e($theme) ?>"<?= $theme === $current_theme ? ' selected' : '' ?>>
                        <?= e($theme) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn--secondary">Apply &amp; rebuild</button>
        </form>
        <?php endif ?>
    </div>

    <div class="card">
        <h2>Rebuild Site Cache</h2>
        <p style="color:#64748b;font-size:.9rem;margin-top:0">Re-parses all Markdown files in <code>/content</code>, regenerates cached PHP files in <code>/cache</code>, and syncs DB metadata.</p>
        <form method="post" action="<?= e(BASE_URL) ?>/admin/index.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="rebuild">
            <button type="submit" class="btn">Rebuild now</button>
        </form>
    </div>
</div>
</body>
</html>
