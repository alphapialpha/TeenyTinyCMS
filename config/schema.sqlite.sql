-- TeenyTinyCMS SQLite Schema
-- Run once by install.php when SQLite is selected.

-- ────────────────────────────────────────────────────────────
-- users
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ────────────────────────────────────────────────────────────
-- slugs  (composite PK enforces one row per slug+language)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slugs (
    slug        TEXT    NOT NULL,
    lang        TEXT    NOT NULL,
    type        TEXT    NOT NULL CHECK (type IN ('page', 'post')),
    md_path     TEXT    NOT NULL,
    php_path    TEXT    NOT NULL,
    title       TEXT    NOT NULL DEFAULT '',
    date        TEXT,
    author      TEXT,
    last_built  TEXT,
    PRIMARY KEY (slug, lang)
);

-- ────────────────────────────────────────────────────────────
-- tags
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tags (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT    NOT NULL UNIQUE
);

-- ────────────────────────────────────────────────────────────
-- tag_links  (many-to-many: slugs ↔ tags, language-aware)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tag_links (
    slug   TEXT    NOT NULL,
    lang   TEXT    NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (slug, lang, tag_id),
    FOREIGN KEY (slug, lang) REFERENCES slugs (slug, lang) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)     REFERENCES tags  (id)         ON DELETE CASCADE
);

-- ────────────────────────────────────────────────────────────
-- media  (local assets)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS media (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    path         TEXT    NOT NULL UNIQUE,
    mime_type    TEXT,
    uploaded_by  INTEGER,
    created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
);
