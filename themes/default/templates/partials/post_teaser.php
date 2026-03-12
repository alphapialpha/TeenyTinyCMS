<?php /** Post teaser partial. Variables: $post (slugs row array) */ ?>
<article class="post-teaser">
    <a class="post-teaser__link" href="<?= url_for('/blog/' . rawurlencode($post['slug']), $post['lang'] ?? 'en') ?>" aria-label="<?= e($post['title']) ?>"></a>
    <?php if (!empty($post['date'])): ?>
        <p class="post-teaser__date">
            <time datetime="<?= e($post['date']) ?>"><?= e($post['date']) ?></time>
        </p>
    <?php endif ?>
    <h3 class="post-teaser__title">
        <a href="<?= url_for('/blog/' . rawurlencode($post['slug']), $post['lang'] ?? 'en') ?>">
            <?= e($post['title']) ?>
        </a>
    </h3>
    <p class="post-teaser__meta">
        <?php if (!empty($post['author'])): ?>
            <?= e($post['author']) ?>
        <?php endif ?>
    </p>
</article>
