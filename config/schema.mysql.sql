-- TeenyTinyCMS MySQL Schema
-- Run once by install.php when MySQL is selected.

-- ────────────────────────────────────────────────────────────
-- users
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username      VARCHAR(100)  NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- slugs  (composite PK enforces one row per slug+language)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slugs (
    slug        VARCHAR(200) NOT NULL,
    lang        VARCHAR(10)  NOT NULL,
    type        ENUM('page','post') NOT NULL,
    md_path     VARCHAR(500) NOT NULL,
    php_path    VARCHAR(500) NOT NULL,
    title       VARCHAR(300) NOT NULL DEFAULT '',
    date        DATE,
    author      VARCHAR(100),
    last_built  DATETIME,
    PRIMARY KEY (slug, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- tags
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tags (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- tag_links  (many-to-many: slugs ↔ tags, language-aware)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tag_links (
    slug   VARCHAR(200) NOT NULL,
    lang   VARCHAR(10)  NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (slug, lang, tag_id),
    CONSTRAINT fk_tag_links_slugs FOREIGN KEY (slug, lang) REFERENCES slugs (slug, lang) ON DELETE CASCADE,
    CONSTRAINT fk_tag_links_tags  FOREIGN KEY (tag_id)     REFERENCES tags  (id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- media  (local assets)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS media (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    path         VARCHAR(500)  NOT NULL,
    mime_type    VARCHAR(100),
    uploaded_by  INT UNSIGNED,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_path (path),
    CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
