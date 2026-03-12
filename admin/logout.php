<?php
/**
 * TeenyTinyCMS Admin – Logout
 * Destroys the current session and redirects to the login page.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

logout();

header('Location: /admin/login.php');
exit;
