# TeenyTinyCMS

A small, self-hosted CMS built with plain PHP 8.1. No framework, no Composer dependencies, no build pipeline required.

> For a developer who wants to own and understand every line of their CMS, writes content in Markdown, and doesn't need a GUI for editors — similar philosophy to Hugo but staying in PHP so the hosting is trivial and the templating is familiar.

Content lives in Markdown files. Metadata and relationships live in a SQLite or MySQL database. Pages are pre-compiled to cached PHP files so every request is a plain file include — no query, no rendering on the fly.

> **Out of the box:** TeenyTinyCMS comes with a full-featured default theme and example posts and pages. You can use the CMS immediately after install and build on the included content and design.

---

## How it works

The build step is the core of the system. When you run the builder (from the browser or the CLI), it:

1. Prunes the DB and cache of anything whose source file no longer exists, removes orphan tags, and deletes stale media rows
2. Reads every `.md` file in `content/` and syncs slug, title, tags, and dates to the database
3. Scans `content/public/media/` and registers any new media files
4. Renders each file through its template and writes a static PHP file to `cache/`
5. Generates one tag index page per tag/language combination
6. Generates static blog pagination pages (page 2, 3, …) for each language
7. Writes a `search/{lang}/index.json` file per language for the client-side search feature (served as a static file)

The router just looks up the slug in the DB, gets the pre-rendered cache file path, and includes it. There is no template evaluation at request time.

---

## Requirements

- PHP 8.1 or later
- `pdo_sqlite` extension (for SQLite) or `pdo_mysql` (for MySQL/MariaDB)
- Apache with `mod_rewrite` enabled, or Nginx (see configuration note below)
- Write permission on `config/`, `data/`, `cache/`, and `search/`

---

## Installation

### 1. Deploy

Drop the project folder into your web root or point a virtual host at it:

    /var/www/html/mysite/   <- document root

For local development, PHP's built-in server works without any web server configuration:

    php -S localhost:8080

> **Note:** The built-in server does not support `.htaccess` rewrite rules,
> but all core features — including the search index — work correctly since
> static files are served directly from disk.

### 2. Run the installer

Open a browser and navigate to:

    http://your-domain/install.php

Fill in the form:

- **Site title** — shown in the header and `<title>` tag
- **Default language** — two-to-five-letter ISO code, e.g. `en`
- **Theme** — choose the active theme from all available theme folders
- **Database** — SQLite is the zero-configuration option; choose MySQL/MariaDB if you need concurrent writes or are on a shared host that does not allow SQLite
- **Admin credentials** — the username and password you will use to log in at `/admin`
- **Copyright notice** — optional; shown in the footer. Defaults to site title if left blank.

The installer writes `config/config.php`, initialises the database schema, and automatically builds the site cache. Once it completes, you can visit the site immediately. Build stats are shown in the installer success message. Delete or restrict access to `install.php` for security. To reset your installation, simply delete config/config.php and rerun install.php.

### 3. Build the cache

After install, the site cache is already built and ready. If you change content, templates, or config, log in to `/admin` and click **Rebuild site**, or run from the command line:

    php app/builder.php

The builder prints how many files it built, how many DB rows and cache files it pruned, orphan tags, errors, and build time.

> **Important:** Rebuild after any change to content files, templates, or config. Site title, default language, and theme are all baked into the cached files at build time.

---

## Content structure

All editable content lives under `content/`:

    content/
    └── public/
        ├── pages/          <- static pages (about, contact, legal, etc.)
        ├── posts/          <- dated blog posts
        └── media/          <- images, PDFs, video files referenced from content

### Pages vs posts

**Pages** are for timeless, structural content: your homepage, about page, contact page, legal notice. They do not appear in the post archive or tag index. Pages are accessed at `/{lang}/{slug}`, for example `/en/about`.

**Posts** are for dated entries: articles, blog posts, changelogs. They support tags, appear on the homepage "latest posts" list, and are accessed at `/{lang}/blog/{slug}`, for example `/en/blog/my-first-post`.

The only thing that determines whether a file is a page or a post is which directory it lives in.

### Filenames

The filename is entirely up to you. The builder reads only two things from it:

- **Language suffix** — the part before `.md`, e.g. `.en.`, `.de.`, `.fr.`
- **Fallback slug** — derived from the filename if no `slug:` is set in front matter

Some valid filenames:

    about.en.md
    2026-03-11-paris-trip.en.md
    003-getting-started.de.md

All three are fine. Use whatever naming scheme helps you stay organised.

### Front matter

Every Markdown file begins with a YAML front matter block between `---` delimiters.

Minimal post:

    ---
    slug: my-first-post
    lang: en
    title: My First Post
    date: 2026-03-11
    ---

    Body text here.

Minimal page:

    ---
    slug: about
    lang: en
    title: About
    ---

    Body text here.

All recognised fields:

| Field      | Required for | Description |
|------------|-------------|-------------|
| `slug`     | all         | URL identifier. Lowercase letters, digits, hyphens, and optionally forward slashes for hierarchical URLs. Must be unique per language. |
| `lang`     | all         | Two-to-five-letter language code matching the site's configured languages. |
| `title`    | all         | The page or post title, used in `<title>` and headings. |
| `date`     | optional    | For posts: publication date in `YYYY-MM-DD` format (used for sort order). For pages: optional, used for "last changed" tracking and display if present. |
| `author`   | posts       | Author name, displayed in the post teaser and post template. |
| `tags`     | posts       | YAML list, e.g. `[news, travel, php]`. Creates tag index pages automatically. |
| `template` | optional    | Override which template file is used. Defaults to `post` for posts and `page` for pages. |

The `slug` and `lang` fields are optional if you supply them via the filename instead. If you set `slug: my-post` in front matter and the filename is `003-my-post.en.md`, the front matter value takes precedence. The filename language suffix is only used as a fallback when `lang` is absent from front matter.

### Hierarchical slugs

Slugs can contain `/` to create nested URLs without changing the flat file structure:

    # content/public/pages/docs-intro.en.md
    ---
    slug: docs/getting-started
    lang: en
    title: Getting Started
    ---

This page is served at `/en/docs/getting-started`. The content file stays in `content/public/pages/` — no nested directories required.

Hierarchical slugs are purely a URL convention. No parent page is created automatically — `/en/docs` does not exist unless you create a separate page with `slug: docs`. Use this to group related pages under a shared URL prefix (e.g. `legal/privacy`, `legal/terms`, `docs/setup`, `docs/faq`).

### Multilingual content

Each language variant is a separate Markdown file with its own `lang:` field:

    content/public/pages/about.en.md       slug: about, lang: en
    content/public/pages/about.de.md       slug: about, lang: de

The language switcher in the header links between variants automatically when both exist.

---

## Media

There are two separate places to put files, and they serve different purposes.

### content/public/media/ — content media

Images, PDFs, and other files that are part of your content go here.

    content/public/media/paris-2026/
        arc-de-triomphe.jpg
        map.pdf

These files are served through the CMS router at `/media/public/...` and are tracked in the database. The builder auto-discovers them on every rebuild.

Reference them in Markdown using the `media_url()` helper or plain Markdown image syntax:

    ![Arc de Triomphe](/media/public/paris-2026/arc-de-triomphe.jpg)

Or in a template:

    <img src="<?= media_url('public/paris-2026/arc-de-triomphe.jpg') ?>" alt="Arc de Triomphe">

Use `content/public/media/` for anything attached to a specific piece of content — post images, documents linked from a page, embedded video.

### themes/default/assets/ — theme assets

The active theme's `assets/` directory is for your site's design: stylesheet, JavaScript, fonts, logo, decorative images. The web server serves these files directly — they bypass PHP entirely.

    themes/default/assets/
    ├── css/
    │   └── app.css         <- main stylesheet
    ├── js/
    │   └── app.js          <- loaded on every page
    └── img/
        └── logo.png        <- example theme graphic

Reference assets in templates using the `asset()` helper:

    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <img src="<?= asset('img/logo.png') ?>" alt="Logo">
    <script src="<?= asset('js/gallery.js') ?>"></script>

`asset('img/logo.png')` returns `/themes/default/assets/img/logo.png` (or the equivalent path for whichever theme is active).

Use the theme's `assets/` for anything that belongs to the design rather than the content. A logo is a theme asset. A photo embedded in a blog post is content media.

**In short: if you were changing the site's visual design, it goes in `themes/{name}/assets/`. If you were writing or editing a post or page, it goes in `content/public/media/`.**

---

## Themes

All themes live under `themes/`. Each theme is a folder containing its own `templates/` and `assets/` subdirectories. Theme folder names can include uppercase and lowercase letters, digits, hyphens, and underscores (e.g. "AlphaPiAlpha").

### Creating a new theme

Copy `themes/default/` to a new folder and work on the copy:

    cp -r themes/default themes/mytheme

Theme folder names must be lowercase letters, digits, hyphens, and underscores only — no spaces. The name must start with a letter or digit. The admin panel lists all valid theme folders automatically.

**Required template files** — at a minimum, your theme must include:

| File | Purpose |
|------|---------|
| `templates/layout.php` | Global HTML wrapper (`<html>`, `<head>`, `<body>`). Every page is rendered inside this. |
| `templates/home_template.php` | Homepage content (the `index` slug). Typically shows a welcome section and latest posts. |
| `templates/blog_template.php` | Blog index page with post list and pagination. |
| `templates/page_template.php` | Default template for static pages. |
| `templates/post_template.php` | Default template for blog posts. |
| `templates/search_template.php` | Search page with client-side search UI. |
| `templates/tag_template.php` | Tag index page listing all posts with a given tag. |

**Recommended partials** in `templates/partials/` (included by the default layout):

| File | Purpose |
|------|---------|
| `header.php` | Site header with logo/title link |
| `footer.php` | Copyright footer |
| `nav.php` | Reserved for backward compatibility — outputs nothing; navigation lives in `header.php` |
| `post_teaser.php` | Post summary card used on the homepage and tag pages |
| `tag_list.php` | Renders a list of tag links for a post |
| `language_switcher.php` | Links to other language versions of the current page |

If a required template file is missing, the builder will throw an error at build time.

To activate the theme, log in to `/admin` and select it from the **Active Theme** dropdown, then click **Apply & rebuild**. This rewrites `active_theme` in `config/config.php` and rebuilds the full cache in one step. The admin dashboard shows detailed build stats after theme change and rebuild: files built, pruned DB rows, orphan tags, errors, and build time.

Alternatively, edit `config/config.php` directly and set:

    'active_theme' => 'mytheme',

then run `php app/builder.php` from the command line.

### Template variables

Different templates receive different variables:

**Content templates** (`page_template.php`, `post_template.php`, `home_template.php`):

| Variable | Contains |
|----------|----------|
| `$meta`  | Front matter array (`slug`, `lang`, `title`, `date`, `author`, `tags`, `template`) |
| `$html`  | The rendered HTML body of the Markdown file |
| `$lang`  | Current language code |

**Blog template** (`blog_template.php`):

| Variable        | Contains |
|-----------------|----------|
| `$meta`         | Front matter array for the blog page |
| `$html`         | Rendered intro HTML (from `blog.{lang}.md` body) |
| `$lang`         | Current language code |
| `$posts`        | Array of post rows for the current page |
| `$current_page` | Current pagination page number (integer, 1-based) |
| `$total_pages`  | Total number of pagination pages |

**Tag template** (`tag_template.php`):

| Variable    | Contains |
|-------------|----------|
| `$meta`     | Synthetic meta array for the tag page |
| `$lang`     | Current language code |
| `$tag_name` | The tag name (e.g. `"travel"`) |
| `$posts`    | Array of post rows matching this tag |

**Layout** (`layout.php`):

| Variable      | Contains |
|---------------|----------|
| `$content`    | The rendered content template output (the inner HTML) |
| `$title`      | Page/post title |
| `$lang`       | Current language code |
| `$slug`       | Current slug (useful for the language switcher) |
| `$type`       | `"page"` or `"post"` |
| `$site_title` | From config |

### Helper functions

Available in all templates and partials:

| Function | Returns |
|----------|---------|
| `e($value)` | HTML-escaped string, safe for output |
| `asset($path)` | `/themes/{active_theme}/assets/{path}` |
| `url_for($path, $lang)` | Language-prefixed URL |
| `media_url($canonical)` | `/media/{canonical}` |
| `render_partial($name, $vars)` | Includes a partial from the active theme's `templates/partials/` |
| `config($key, $default)` | Read a value from `config/config.php` |
| `get_latest_posts($limit, $lang)` | Returns recent post rows from the DB |
| `get_posts_by_tag($tag, $lang)` | Returns posts with a given tag |
| `get_page_meta($slug, $lang)` | Returns metadata for a single slug, or null |
| `get_slug_languages($slug, $type)` | Returns language codes that have this slug |
| `t($key, $lang)` | Returns a translated UI label (see [Translations](#translations)) |

### Custom templates

To use a custom template for a specific post or page, set `template:` in front matter and create a file with the `_template.php` suffix in your theme's `templates/` directory:

    # in front matter
    template: landing

    # corresponding template file (note the _template.php suffix)
    themes/mytheme/templates/landing_template.php

The builder always appends `_template.php` to the template name. If the custom template file is not found, it falls back to the type default (`page_template.php` or `post_template.php`).

---

## Translations

UI labels (navigation, buttons, hints, pagination text) are fully translatable and can be extended by themes.

### How it works

Two files are merged at runtime:

| File | Purpose |
|------|---------|
| `config/translations.php` | Site-wide baseline — ships with TeenyTinyCMS, covers all built-in labels |
| `themes/{name}/translations.php` | Theme-specific additions or overrides — optional, loaded on top of the baseline |

Theme values win over baseline values for any key that appears in both. If a theme file does not exist, the baseline is used as-is.

### Using translations in templates

Call `t($key, $lang)` anywhere in a template or partial. If the key is missing from both files, the key name itself is returned as a plain-text fallback.

```php
// Navigation link
<a href="<?= url_for('/blog', $lang) ?>"><?= t('blog', $lang) ?></a>

// Button label
<span><?= t('next', $lang) ?> &rarr;</span>

// ARIA label
<button aria-label="<?= t('toggle_menu', $lang) ?>">
```

### Built-in keys

| Key | Default (en) |
|-----|--------------|
| `home` | Home |
| `blog` | Blog |
| `about` | About |
| `search` | Search |
| `search_placeholder` | Search… |
| `open_search` | Open search |
| `toggle_menu` | Toggle menu |
| `search_hint` | Type to search. |
| `next` | Next |
| `prev` | Previous |
| `read_more` | Read more |
| `latest_posts` | Latest Posts |
| `no_posts` | No posts yet. |
| `posts_tagged` | Posts tagged: |
| `no_posts_tagged` | No posts found for this tag. |
| `404_title` | Page not found |

### Adding a new language

Open `config/translations.php` and add a new entry at the bottom of the array:

```php
'fr' => [
    'home'               => 'Accueil',
    'blog'               => 'Blog',
    'about'              => 'À propos',
    'next'               => 'Suivant',
    'prev'               => 'Précédent',
    'search'             => 'Recherche',
    'search_placeholder' => 'Rechercher…',
    'open_search'        => 'Ouvrir la recherche',
    'toggle_menu'        => 'Ouvrir le menu',
    'search_hint'        => 'Commencez à saisir.',
    'read_more'          => 'Lire la suite',
    'latest_posts'       => 'Derniers articles',
    'no_posts'           => "Aucun article pour l'instant.",
    'posts_tagged'       => 'Articles avec le tag :',
    'no_posts_tagged'    => 'Aucun article pour ce tag.',
    '404_title'          => 'Page introuvable',
],
```

Then rebuild the site.

### Adding labels in a custom theme

If your theme introduces UI elements that need translatable labels, add them in `themes/{name}/translations.php`:

```php
// themes/mytheme/translations.php
return [
    'en' => [
        'gallery_caption' => 'Photo gallery',
        'buy_now'         => 'Buy now',
    ],
    'de' => [
        'gallery_caption' => 'Fotogalerie',
        'buy_now'         => 'Jetzt kaufen',
    ],
];
```

These keys are available instantly via `t('gallery_caption', $lang)` in any of the theme's templates. You can also override any baseline key the same way — for example to rename "About" to "Story" without touching `config/translations.php`.

---

## Admin panel

    http://your-domain/admin

The dashboard shows a count of pages, posts, tags, and media files. It has two action cards:

- **Active Theme** — dropdown listing all theme folders found in `themes/`. Select a theme and click **Apply & rebuild** to switch immediately. Switching themes triggers a full rebuild and shows detailed build stats: files built, pruned DB rows, orphan tags, errors, and build time.
- **Rebuild Site Cache** — re-parses all Markdown, regenerates all cache files, prunes deleted content, and shows stats including how long it took.

Both actions trigger the same full build that `php app/builder.php` runs from the command line.

Rebuild after any change to content files, templates, or config.

**Note:** The CLI builder must be run from the project root directory:

    cd /path/to/your/site
    php app/builder.php

---

## Nginx configuration

The included `.htaccess` protects sensitive directories automatically on Apache. On Nginx, `.htaccess` is ignored. Add the following to your server block:

    server {
        listen 80;
        server_name your-domain.com;
        root /var/www/mysite;
        index index.php;

        # Block direct access to sensitive paths
        location ~* ^/(app|config|data|cache|content)(/|$) {
            deny all;
            return 403;
        }

        # Block theme template directories
        location ~* ^/themes/[^/]+/templates(/|$) {
            deny all;
            return 403;
        }

        location ~* /\. {
            deny all;
        }

        location ~* \.(md|sql|sh|yaml|yml|lock|json)$ {
            deny all;
        }

        # Front controller
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }

---

## Directory reference

| Path | Purpose |
|------|---------|
| `content/public/pages/` | Source Markdown for static pages |
| `content/public/posts/` | Source Markdown for blog posts |
| `content/public/media/` | Media files served via the CMS at `/media/public/...` |
| `themes/` | All themes. Each subfolder is one theme. |
| `themes/default/assets/` | CSS, JS, images for the default theme — served directly by the web server |
| `themes/default/templates/` | Layout and content templates for the default theme |
| `cache/` | Pre-rendered PHP files — auto-generated, do not edit |
| `search/` | Generated search index JSON files — auto-generated by the builder |
| `data/` | SQLite database file and `error.log` (keep out of public access) |
| `config/config.php` | Site configuration, written by the installer. `active_theme` controls which theme is used. |
| `config/translations.php` | Site-wide baseline UI translations, editable plain PHP array |
| `themes/default/translations.php` | Theme-specific translation overrides (optional, one per theme) |
| `app/` | Application source — router, builder, DB, auth, helpers |

---

## Troubleshooting

**404 on every page** — `mod_rewrite` is not enabled or `.htaccess` is not being read. On Apache run `a2enmod rewrite` and make sure `AllowOverride All` is set for your document root. On Nginx, use the server block from the Nginx section above.

**Blank page after editing content** — The cache is stale. Run a rebuild from `/admin` or via `php app/builder.php`.

**"Template not found" error during build** — Your active theme is missing a required template file. Check that `layout.php`, `page_template.php`, `post_template.php`, `home_template.php`, and `tag_template.php` all exist in `themes/{your-theme}/templates/`.

**Where are the error logs?** — All application errors, PHP warnings/notices, and fatal errors (including parse errors and uncaught crashes) are written to `data/error.log`. Non-fatal errors are captured by the error handler; fatal errors are caught at shutdown. If `data/error.log` does not exist yet, PHP creates it on the first error.

**Changes to `config.php` are not reflected** — The cache must be rebuilt after changing any config value. Site title, default language, and theme are all baked into the cached files at build time.

---

## Dependencies

TeenyTinyCMS has no Composer dependencies and requires no package manager. One library is bundled directly in `app/parsers/`:

| Library | Version | Purpose |
|---------|---------|---------|
| [Parsedown](https://github.com/erusev/parsedown) | 1.8.0 | Markdown-to-HTML parsing |
| [ParsedownExtra](https://github.com/erusev/parsedown-extra) | 0.9.0 | Extends Parsedown with tables, footnotes, fenced code blocks |

Both files are self-contained and require no installation.

### Updating Parsedown

If a newer version of Parsedown is released and you want to update:

1. Download `Parsedown.php` from [github.com/erusev/parsedown](https://github.com/erusev/parsedown/releases) and replace `app/parsers/Parsedown.php`
2. If you also use ParsedownExtra, download `ParsedownExtra.php` from [github.com/erusev/parsedown-extra](https://github.com/erusev/parsedown-extra/releases) and replace `app/parsers/ParsedownExtra.php`
3. Check the release notes for any breaking changes to the `Parsedown` class API
4. Run a full rebuild (`php app/builder.php`) and verify the output looks correct

**Note:** TeenyTinyCMS calls `setSafeMode(false)` on the parser, which means raw HTML in Markdown content is passed through as-is. Only use this with content you trust — do not allow untrusted users to submit raw Markdown.

---

## License

MIT License — © 2026 André P. Appel (AlphaPiAlpha)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies, subject to the condition that the copyright notice and license text are included in all copies or substantial portions of the software. See the [LICENSE](LICENSE) file for the full text.

### Third-party notices

Includes [Parsedown](https://github.com/erusev/parsedown) and [ParsedownExtra](https://github.com/erusev/parsedown-extra) by Emanuil Rusev, MIT licensed.
