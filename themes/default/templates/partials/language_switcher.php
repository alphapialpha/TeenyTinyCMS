<?php
/**
 * Language switcher partial.
 * Variables: $lang (current), $slug (current page slug), $type (page|post)
 *
 * Shows one link per language that has a translation of the current content.
 * Falls back to the language homepage (/{lang}/) for languages that don't
 * have this exact slug.
 */
$_current_lang = $lang ?? '';
$_slug         = $slug ?? '';
$_type         = $type ?? '';

// Get languages that have this exact slug translated
$_available = ($_slug !== '' && $_type !== '')
    ? get_slug_languages($_slug, $_type)
    : [];

// If no slug-specific translations found, fall back to all known languages
// but link each to its homepage since the current page may not exist there.
if (empty($_available)) {
    $_rows      = db_fetch_all('SELECT DISTINCT lang FROM slugs ORDER BY lang ASC');
    $_available = array_column($_rows, 'lang');
    $_fallback  = true;
} else {
    $_fallback  = false;
}

// Only show switcher if there is more than one language
if (count($_available) <= 1) { return; }
?>
<div class="language-switcher">
<?php foreach ($_available as $_l): ?>
    <?php
    if ($_fallback) {
        // No per-slug data — always link to the language homepage
        $_href = url_for('/', $_l);
    } elseif ($_type === 'post') {
        $_href = url_for('/blog/' . rawurlencode($_slug), $_l);
    } elseif ($_slug !== '' && $_slug !== 'index') {
        // Encode each slug segment individually so '/' is preserved
        $_href = url_for('/' . implode('/', array_map('rawurlencode', explode('/', $_slug))), $_l);
    } else {
        $_href = url_for('/', $_l);
    }
    $_active = $_l === $_current_lang ? ' lang-link--active' : '';
    ?>
    <a href="<?= e($_href) ?>" class="lang-link<?= $_active ?>"><?= e(strtoupper($_l)) ?></a>
<?php endforeach; ?>
</div>
