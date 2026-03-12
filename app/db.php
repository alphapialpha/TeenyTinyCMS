<?php
/**
 * TeenyTinyCMS – Database connector
 *
 * Provides a single shared PDO instance for the entire request.
 * Supports SQLite and MySQL. All queries must use prepared statements.
 */

declare(strict_types=1);

/** Return the shared PDO instance, creating it on first call. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $db_cfg   = config('database');
    $driver   = $db_cfg['driver']   ?? 'sqlite';
    $dsn      = $db_cfg['dsn']      ?? '';
    $username = $db_cfg['username'] ?? null;
    $password = $db_cfg['password'] ?? null;

    if ($dsn === '') {
        throw new RuntimeException('Database DSN is not configured.');
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // SQLite-specific: enforce foreign key constraints
    $pdo = new PDO($dsn, $username, $password, $options);

    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    }

    return $pdo;
}

/**
 * Execute a prepared query and return the PDOStatement.
 *
 * Usage:
 *   $stmt = db_query('SELECT * FROM slugs WHERE slug = :s AND lang = :l',
 *                    [':s' => $slug, ':l' => $lang]);
 *   $rows = $stmt->fetchAll();
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows for a query.
 *
 * @return array<int, array<string, mixed>>
 */
function db_fetch_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Fetch a single row or null if not found.
 *
 * @return array<string, mixed>|null
 */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row !== false ? $row : null;
}

/**
 * Execute an INSERT/UPDATE/DELETE and return the number of affected rows.
 */
function db_execute(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Return the last inserted row ID.
 */
function db_last_insert_id(): string
{
    return db()->lastInsertId();
}
