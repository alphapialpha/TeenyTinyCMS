<?php /** Search template. Variables: $meta, $html, $lang */ ?>
<section class="search-page">
    <h1 class="search-page__title"><?= e($meta['title'] ?? 'Search') ?></h1>

    <form class="search-form" role="search" action="javascript:void(0)">
        <input
            type="search"
            id="search-input"
            class="search-form__input"
            placeholder="<?= t('search_placeholder', $lang ?? 'en') ?>"
            autocomplete="off"
            autofocus
        >
    </form>

    <div id="search-results" class="search-results" data-lang="<?= e($lang) ?>">
        <p class="search-results__hint">
            <?= t('search_hint', $lang ?? 'en') ?>
        </p>
    </div>
</section>
