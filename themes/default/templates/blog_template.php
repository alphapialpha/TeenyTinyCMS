<?php /** Blog index template. Variables: $meta, $html, $lang, $posts (opt), $current_page (opt), $total_pages (opt) */ ?>
<section class="blog-page">
    <h1 class="blog-page__title"><?= e($meta['title'] ?? '') ?></h1>

    <?php if (!empty($html)): ?>
        <div class="blog-page__intro"><?= $html ?></div>
    <?php endif ?>

    <?php
    // Use pre-fetched $posts from the builder; fall back to self-query for safety
    if (!isset($posts)) {
        $posts = get_latest_posts((int) config('blog_per_page', 9), $meta['lang'] ?? $lang ?? 'en');
    }
    $current_page = $current_page ?? 1;
    $total_pages  = $total_pages  ?? 1;
    ?>

    <?php if (empty($posts)): ?>
        <p class="blog-page__empty"><?= t('no_posts', $lang ?? 'en') ?></p>
    <?php else: ?>
        <div class="post-card-grid">
            <?php foreach ($posts as $post): ?>
                <?php render_partial('post_teaser', ['post' => $post]) ?>
            <?php endforeach ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="blog-page__pagination" aria-label="Pagination">
                <?php
                $base_url   = url_for('/blog', $meta['lang'] ?? $lang ?? 'en');
                $prev_label = t('prev', $lang ?? 'en');
                $next_label = t('next', $lang ?? 'en');
                ?>
                <?php if ($current_page > 1): ?>
                    <a class="blog-page__pagination-link blog-page__pagination-link--prev"
                       href="<?= $current_page === 2 ? e($base_url) : e($base_url . '/page/' . ($current_page - 1)) ?>">
                        &larr; <?= e($prev_label) ?>
                    </a>
                <?php endif ?>

                <span class="blog-page__pagination-info">
                    <?= e($current_page) ?> / <?= e($total_pages) ?>
                </span>

                <?php if ($current_page < $total_pages): ?>
                    <a class="blog-page__pagination-link blog-page__pagination-link--next"
                       href="<?= e($base_url . '/page/' . ($current_page + 1)) ?>">
                        <?= e($next_label) ?> &rarr;
                    </a>
                <?php endif ?>
            </nav>
        <?php endif ?>
    <?php endif ?>
</section>
