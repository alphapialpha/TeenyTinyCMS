<?php /** Header partial. Variables: $lang, $site_title */ ?>
<nav class="site-navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-inner">
        <a href="<?= url_for('/', $lang ?? 'en') ?>" class="navbar-brand">
            <?php
            $logo_path = BASE_PATH . '/themes/' . active_theme() . '/assets/img/logo.webp';
            if (is_file($logo_path)):
            ?>
                <img src="<?= asset('img/logo.webp') ?>" alt="<?= e($site_title ?? 'TeenyTinyCMS') ?>" class="navbar-logo">
            <?php else: ?>
                <span class="navbar-logo-fallback"><?= e($site_title ?? 'TeenyTinyCMS') ?></span>
            <?php endif ?>
        </a>

        <ul class="navbar-links" role="list">
            <li><a href="<?= url_for('/', $lang ?? 'en') ?>"><?= t('home', $lang ?? 'en') ?></a></li>
            <li><a href="<?= url_for('/blog', $lang ?? 'en') ?>"><?= t('blog', $lang ?? 'en') ?></a></li>
            <li><a href="<?= url_for('/about', $lang ?? 'en') ?>"><?= t('about', $lang ?? 'en') ?></a></li>
        </ul>

        <div class="navbar-search">
            <button class="navbar-search__toggle" type="button" aria-label="<?= t('open_search', $lang ?? 'en') ?>" aria-expanded="false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
            <form class="navbar-search__form" action="<?= url_for('/search', $lang ?? 'en') ?>" role="search">
                <input type="search" name="q" class="navbar-search__input" placeholder="<?= t('search_placeholder', $lang ?? 'en') ?>" autocomplete="off">
            </form>
        </div>

        <button class="navbar-toggle" aria-expanded="false" aria-controls="navbar-drawer" aria-label="<?= t('toggle_menu', $lang ?? 'en') ?>">
            <span class="hamburger-icon" aria-hidden="true">
                <span></span><span></span><span></span>
            </span>
        </button>
    </div>

    <div class="navbar-drawer" id="navbar-drawer">
        <ul class="navbar-links" role="list">
            <li><a href="<?= url_for('/', $lang ?? 'en') ?>"><?= t('home', $lang ?? 'en') ?></a></li>
            <li><a href="<?= url_for('/blog', $lang ?? 'en') ?>"><?= t('blog', $lang ?? 'en') ?></a></li>
            <li><a href="<?= url_for('/about', $lang ?? 'en') ?>"><?= t('about', $lang ?? 'en') ?></a></li>
            <li><a href="<?= url_for('/search', $lang ?? 'en') ?>"><?= t('search', $lang ?? 'en') ?></a></li>
        </ul>
    </div>
</nav>
