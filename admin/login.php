<?php
/**
 * TeenyTinyCMS Admin – Login page
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

auth_start();

// Already logged in → go straight to dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($username, $password)) {
            // Regenerate CSRF token after successful login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TeenyTinyCMS Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; margin: 0; padding: 3rem 1rem; }
        .login-box { max-width: 360px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 2rem; }
        h1 { margin-top: 0; font-size: 1.4rem; }
        label { display: block; margin-bottom: .25rem; font-weight: 500; font-size: .9rem; }
        input { width: 100%; padding: .5rem .75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
        input:focus { outline: 2px solid #3b82f6; border-color: #3b82f6; }
        .btn { width: 100%; padding: .6rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #2563eb; }
        .error { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .9rem; color: #991b1b; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>TeenyTinyCMS Admin</h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif ?>

    <form method="post" action="<?= e(BASE_URL) ?>/admin/login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required
               value="<?= e($_POST['username'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <button type="submit" class="btn">Sign in</button>
    </form>
</div>
</body>
</html>
