<?php
/**
 * TeenyTinyCMS Installer
 *
 * Responsibilities:
 *  - Detect existing installation
 *  - Collect site config, DB credentials, admin account
 *  - Write /config/config.php
 *  - Initialise selected DB from the correct schema file
 *  - Insert the first admin user
 *  - Validate folder writability
 */

declare(strict_types=1);

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

define('BASE_PATH', __DIR__);
define('CONFIG_FILE', BASE_PATH . '/config/config.php');
define('DATA_DIR',   BASE_PATH . '/data');

// ── Guard: already installed ────────────────────────────────────────────────
if (file_exists(CONFIG_FILE)) {
    $cfg = require CONFIG_FILE;
    if (!empty($cfg['installed'])) {
        http_response_code(403);
        exit('TeenyTinyCMS is already installed. Delete /config/config.php to re-run the installer.');
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * HTML-escape helper. Equivalent to e() in template_helpers.php, but defined
 * here standalone because install.php runs before bootstrap is available.
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Scan themes/ directory and return array of theme folder names. */
function available_themes(): array
{
    $dir = BASE_PATH . '/themes';
    if (!is_dir($dir)) {
        return ['default'];
    }
    $themes = [];
    foreach (new DirectoryIterator($dir) as $item) {
        if ($item->isDot() || !$item->isDir()) {
            continue;
        }
        $name = $item->getFilename();
        // A valid theme must contain a templates/ sub-directory
        if (is_dir($dir . '/' . $name . '/templates')) {
            $themes[] = $name;
        }
    }
    sort($themes);
    return $themes ?: ['default'];
}

/** Return list of paths that must be writable. */
function required_writable_paths(): array
{
    return [
        BASE_PATH . '/config',
        BASE_PATH . '/data',
        BASE_PATH . '/cache',
    ];
}

/** Check all required writable paths and return array of failures. */
function check_writability(): array
{
    $failures = [];
    foreach (required_writable_paths() as $path) {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                $failures[] = "$path (could not create)";
            }
        } elseif (!is_writable($path)) {
            $failures[] = "$path (not writable)";
        }
    }
    return $failures;
}

/** Build a PDO DSN string based on submitted form data. */
function build_dsn(array $data): string
{
    if ($data['db_driver'] === 'sqlite') {
        return 'sqlite:' . DATA_DIR . '/database.sqlite';
    }
    $host = $data['db_host'];
    $port = (int) $data['db_port'];
    $name = $data['db_name'];
    return "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
}

/** Open a PDO connection, throwing on failure. */
function open_pdo(array $data): PDO
{
    $dsn      = build_dsn($data);
    $username = $data['db_driver'] === 'sqlite' ? null : ($data['db_username'] ?? null);
    $password = $data['db_driver'] === 'sqlite' ? null : ($data['db_password'] ?? null);

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** Execute schema SQL file against the given PDO connection. */
function run_schema(PDO $pdo, string $driver): void
{
    $file = BASE_PATH . '/config/schema.' . $driver . '.sql';
    if (!file_exists($file)) {
        throw new RuntimeException("Schema file not found: $file");
    }
    $sql = file_get_contents($file);
    // Execute each statement individually (PDO::exec handles one at a time cleanly)
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

/** Insert the first admin user. */
function insert_admin(PDO $pdo, string $username, string $password): void
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash) VALUES (:u, :h)'
    );
    $stmt->execute([':u' => $username, ':h' => $hash]);
}

/** Write the config file. */
function write_config(array $data): void
{
    $driver   = $data['db_driver'];
    $dsn      = build_dsn($data);
    $username = $driver === 'sqlite' ? 'null' : var_export($data['db_username'], true);
    $password = $driver === 'sqlite' ? 'null' : var_export($data['db_password'], true);
    $siteLang        = preg_replace('/[^a-z]/', '', strtolower($data['default_lang']));
    $siteTitle       = var_export($data['site_title'], true);
    $copyrightNotice = var_export($data['copyright_notice'] !== '' ? $data['copyright_notice'] : $data['site_title'], true);
    $activeTheme     = var_export($data['active_theme'], true);
    $dsnExport       = var_export($dsn, true);

    $content = <<<PHP
<?php
// TeenyTinyCMS configuration — generated by install.php
// Do NOT edit manually except for 'site_title', 'copyright_notice', 'default_lang', 'active_theme', and 'blog_per_page'.

return [
    'site_title'       => {$siteTitle},
    'copyright_notice' => {$copyrightNotice},
    'default_lang'     => '{$siteLang}',
    'active_theme'     => {$activeTheme},
    'blog_per_page'    => 9,
    'installed'    => true,
    'database' => [
        'driver'   => '{$driver}',
        'dsn'      => {$dsnExport},
        'username' => {$username},
        'password' => {$password},
    ],
];
PHP;

    if (file_put_contents(CONFIG_FILE, $content) === false) {
        throw new RuntimeException('Could not write ' . CONFIG_FILE);
    }
}

// ── Process POST ─────────────────────────────────────────────────────────────
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $errors[] = 'Invalid CSRF token. Please reload the page and try again.';
    }

    if (empty($errors)) {
        $data = [
            'site_title'       => trim($_POST['site_title']       ?? ''),
            'copyright_notice' => trim($_POST['copyright_notice'] ?? ''),
            'default_lang'     => trim($_POST['default_lang']     ?? 'en'),
            'active_theme'     => trim($_POST['active_theme']     ?? 'default'),
            'db_driver'    => trim($_POST['db_driver']    ?? 'sqlite'),
            'db_host'      => trim($_POST['db_host']      ?? '127.0.0.1'),
            'db_port'      => trim($_POST['db_port']      ?? '3306'),
            'db_name'      => trim($_POST['db_name']      ?? ''),
            'db_username'  => trim($_POST['db_username']  ?? ''),
            'db_password'  => $_POST['db_password']       ?? '',
            'admin_user'   => trim($_POST['admin_user']   ?? ''),
            'admin_pass'   => $_POST['admin_pass']        ?? '',
            'admin_pass2'  => $_POST['admin_pass2']       ?? '',
        ];

        // Sanitise: strip control characters (null bytes, newlines, tabs, etc.)
        $data['site_title']       = preg_replace('/[\x00-\x1F\x7F]/u', '', $data['site_title']);
        $data['copyright_notice'] = preg_replace('/[\x00-\x1F\x7F]/u', '', $data['copyright_notice']);

        // Validate
        if ($data['site_title'] === '') {
            $errors[] = 'Site title is required.';
        }
        if (mb_strlen($data['site_title']) > 100) {
            $errors[] = 'Site title must be 100 characters or fewer.';
        }
        if (mb_strlen($data['copyright_notice']) > 150) {
            $errors[] = 'Copyright notice must be 150 characters or fewer.';
        }
        if (!preg_match('/^[a-z]{2,5}$/', $data['default_lang'])) {
            $errors[] = 'Default language must be a valid language code (e.g. en, de, fr, es, pt-br — lowercase letters only, 2–5 characters).';
        }
        if (!in_array($data['active_theme'], available_themes(), true)) {
            $errors[] = 'Invalid theme selected.';
        }
        if (!in_array($data['db_driver'], ['sqlite', 'mysql'], true)) {
            $errors[] = 'Invalid database driver.';
        }
        if ($data['db_driver'] === 'mysql') {
            if ($data['db_host'] === '')     $errors[] = 'MySQL host is required.';
            if ($data['db_name'] === '')     $errors[] = 'MySQL database name is required.';
            if ($data['db_username'] === '') $errors[] = 'MySQL username is required.';
        }
        if ($data['admin_user'] === '') {
            $errors[] = 'Admin username is required.';
        }
        if (strlen($data['admin_pass']) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }
        if ($data['admin_pass'] !== $data['admin_pass2']) {
            $errors[] = 'Admin passwords do not match.';
        }

        if (empty($errors)) {
            // Check writability
            $writeErrors = check_writability();
            if (!empty($writeErrors)) {
                foreach ($writeErrors as $e) {
                    $errors[] = "Directory not writable: $e";
                }
            }
        }

        if (empty($errors)) {
            try {
                // For SQLite: wipe the existing database file so reinstall starts clean
                if ($data['db_driver'] === 'sqlite') {
                    $sqlite_file = DATA_DIR . '/database.sqlite';
                    if (file_exists($sqlite_file)) {
                        unlink($sqlite_file);
                    }
                }
                $pdo = open_pdo($data);

            // For MySQL: require an empty database — existing tables mean wrong DB
            if ($data['db_driver'] === 'mysql') {
                $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($existing)) {
                    throw new RuntimeException(
                        'The MySQL database "' . h($data['db_name']) . '" already contains ' .
                        count($existing) . ' table(s). ' .
                        'TeenyTinyCMS requires a completely empty database. ' .
                        'Please create a new empty database or drop all existing tables first.'
                    );
                }
            }

            run_schema($pdo, $data['db_driver']);
            insert_admin($pdo, $data['admin_user'], $data['admin_pass']);
            write_config($data);
            $success = true;

            // Auto-build the site cache so the frontend works immediately
            try {
                require_once BASE_PATH . '/app/bootstrap.php';
                require_once BASE_PATH . '/app/markdown.php';
                require_once BASE_PATH . '/app/utils.php';
                require_once BASE_PATH . '/app/builder.php';
                $t0         = microtime(true);
                $build_stats = build_all();
                $build_time  = round((microtime(true) - $t0) * 1000);
            } catch (Throwable $be) {
                // Build failure is non-fatal — user can rebuild from admin
                $build_stats = null;
                $build_error = $be->getMessage();
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
    } // end CSRF guard
}

// ── Restore safe defaults for form repopulation ──────────────────────────────
$data = $data ?? [
    'site_title'       => '',
    'copyright_notice' => '',
    'default_lang'     => 'en',
    'active_theme'     => 'default',
    'db_driver'   => 'sqlite',
    'db_host'     => '127.0.0.1',
    'db_port'     => '3306',
    'db_name'     => '',
    'db_username' => '',
    'admin_user'  => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeenyTinyCMS – Installer</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #222; margin: 0; padding: 2rem 1rem; }
        .installer { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 2rem; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        h2 { font-size: 1rem; margin: 1.5rem 0 .5rem; color: #555; text-transform: uppercase; letter-spacing: .05em; }
        label { display: block; margin-bottom: .25rem; font-weight: 500; font-size: .9rem; }
        input, select { width: 100%; padding: .5rem .75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
        input:focus, select:focus { outline: 2px solid #3b82f6; border-color: #3b82f6; }
        .btn { display: inline-block; padding: .6rem 1.4rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #2563eb; }
        .errors { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .errors ul { margin: 0; padding-left: 1.2rem; }
        .errors li { margin-bottom: .25rem; font-size: .9rem; color: #991b1b; }
        .success { background: #dcfce7; border: 1px solid #86efac; border-radius: 4px; padding: 1.25rem; }
        .success h2 { color: #166534; margin-top: 0; }
        .success a { color: #15803d; font-weight: 600; }
        .mysql-fields { display: none; }
        .mysql-fields.visible { display: block; }
        .hint { font-size: .8rem; color: #777; margin-top: -.75rem; margin-bottom: .75rem; }
    </style>
</head>
<body>
<div class="installer">
    <h1>TeenyTinyCMS Installer</h1>

    <?php if ($success): ?>
    <div class="success">
        <h2>Installation complete!</h2>
        <p>Your config has been written and the database has been initialised.</p>
        <?php if (!empty($build_stats)): ?>
            <p style="font-size:.9rem;color:#166534">
                Site cache built: <?= (int) $build_stats['built'] ?> file(s). Pruned <?= (int) ($build_stats['pruned_db'] ?? 0) ?> DB row(s), <?= (int) ($build_stats['tags_pruned'] ?? 0) ?> orphan tag(s). Errors: <?= (int) $build_stats['errors'] ?>. Time: <?= (int) $build_time ?> ms.
            </p>
        <?php elseif (!empty($build_error)): ?>
            <p style="font-size:.9rem;color:#991b1b">
                Auto-build failed: <?= h($build_error) ?><br>You can rebuild manually from the admin dashboard.
            </p>
        <?php endif ?>
        <p>
            <a href="<?= e(defined('BASE_URL') ? BASE_URL : '') ?>/admin/login.php">Go to admin login &rarr;</a>
        </p>
        <p style="font-size:.85rem;color:#555;">
            <strong>Security note:</strong> You may delete or restrict access to <code>install.php</code> now that setup is complete. To reset your installation, simply delete <code>config/config.php</code> and rerun <code>install.php</code>.
        </p>
    </div>

    <?php else: ?>

    <?php if (!empty($errors)): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
    <?php endif ?>

    <form method="post" action="install.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <h2>Site</h2>
        <label for="site_title">Site title</label>
        <input type="text" id="site_title" name="site_title"
               value="<?= h($data['site_title']) ?>" maxlength="100" required>

        <label for="copyright_notice">Copyright notice <small>(optional — defaults to site title)</small></label>
        <input type="text" id="copyright_notice" name="copyright_notice"
               value="<?= h($data['copyright_notice']) ?>"
               placeholder="e.g. My Company Ltd." maxlength="150">

        <label for="default_lang">Default language <small>(ISO code, e.g. en, de, fr, es)</small></label>
        <input type="text" id="default_lang" name="default_lang"
               value="<?= h($data['default_lang']) ?>"
               placeholder="en" maxlength="5" pattern="[a-z]{2,5}" required>

        <label for="active_theme">Theme</label>
        <select id="active_theme" name="active_theme">
            <?php foreach (available_themes() as $theme): ?>
                <option value="<?= h($theme) ?>" <?= ($data['active_theme'] ?? 'default') === $theme ? 'selected' : '' ?>><?= h($theme) ?></option>
            <?php endforeach ?>
        </select>

        <h2>Database</h2>
        <label for="db_driver">Database engine</label>
        <select id="db_driver" name="db_driver">
            <option value="sqlite" <?= $data['db_driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite (recommended for small sites)</option>
            <option value="mysql"  <?= $data['db_driver'] === 'mysql'  ? 'selected' : '' ?>>MySQL / MariaDB</option>
        </select>

        <div class="mysql-fields" id="mysql-fields">
            <label for="db_host">Host</label>
            <input type="text" id="db_host" name="db_host" value="<?= h($data['db_host']) ?>">

            <label for="db_port">Port</label>
            <input type="number" id="db_port" name="db_port" value="<?= h($data['db_port']) ?>">
            <p class="hint">Default MySQL port is 3306.</p>

            <label for="db_name">Database name</label>
            <input type="text" id="db_name" name="db_name" value="<?= h($data['db_name']) ?>">

            <label for="db_username">Username</label>
            <input type="text" id="db_username" name="db_username" value="<?= h($data['db_username']) ?>" autocomplete="off">

            <label for="db_password">Password</label>
            <input type="password" id="db_password" name="db_password" autocomplete="new-password">
        </div>

        <h2>Admin account</h2>
        <label for="admin_user">Username</label>
        <input type="text" id="admin_user" name="admin_user"
               value="<?= h($data['admin_user']) ?>" autocomplete="off" required>

        <label for="admin_pass">Password</label>
        <input type="password" id="admin_pass" name="admin_pass"
               autocomplete="new-password" required>
        <p class="hint">Minimum 8 characters.</p>

        <label for="admin_pass2">Confirm password</label>
        <input type="password" id="admin_pass2" name="admin_pass2"
               autocomplete="new-password" required>

        <button type="submit" class="btn">Install TeenyTinyCMS</button>
    </form>

    <script>
    (function () {
        var driver = document.getElementById('db_driver');
        var mysql  = document.getElementById('mysql-fields');
        function toggle() {
            mysql.classList.toggle('visible', driver.value === 'mysql');
        }
        driver.addEventListener('change', toggle);
        toggle();
    }());
    </script>

    <?php endif ?>
</div>
</body>
</html>
