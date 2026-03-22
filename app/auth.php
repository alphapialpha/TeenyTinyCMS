<?php
/**
 * TeenyTinyCMS – Session-based authentication
 *
 * Public API:
 *   auth_start()              – start session (call once per request)
 *   is_logged_in(): bool      – true if a valid user session exists
 *   current_user(): ?array    – returns the user row or null
 *   login(string, string): bool – validate credentials, populate session
 *   logout(): void            – destroy session
 *   require_login(): void     – abort with 403 unless logged in
 */

declare(strict_types=1);

/** Start the session if not already started. */
function auth_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/** Returns true if the current request has a valid authenticated session. */
function is_logged_in(): bool
{
    auth_start();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

/**
 * Returns the currently logged-in user row from the DB, or null.
 *
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    static $cached = null;
    static $cached_id = null;

    if (!is_logged_in()) {
        return null;
    }

    $id = (int) $_SESSION['user_id'];
    if ($cached !== null && $cached_id === $id) {
        return $cached;
    }

    $cached_id = $id;
    $cached = db_fetch_one(
        'SELECT id, username, created_at FROM users WHERE id = :id',
        [':id' => $id]
    );
    return $cached;
}

/**
 * Attempt to log in with the given credentials.
 * Populates the session on success.
 *
 * @return bool  true on success, false on invalid credentials
 */
function login(string $username, string $password): bool
{
    // Normalise to prevent timing attacks on the username lookup
    $username = trim($username);

    if ($username === '' || $password === '') {
        return false;
    }

    $user = db_fetch_one(
        'SELECT id, username, password_hash FROM users WHERE username = :u',
        [':u' => $username]
    );

    if ($user === null) {
        // Run a dummy verify to prevent username-enumeration via timing
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345');
        return false;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    // Regenerate session ID to prevent session fixation
    auth_start();
    session_regenerate_id(true);

    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    return true;
}

/** Destroy the current session completely. */
function logout(): void
{
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

/**
 * Abort the request with a 403 unless the user is logged in.
 * Used by the router for private content and by admin pages.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        http_response_code(403);
        exit('403 – Access denied. Please <a href="' . BASE_URL . '/admin/login.php">log in</a>.');
    }
}
