<?php /** Homepage template. Variables: $meta, $html, $lang */ ?>
<?php if (!empty($html)): ?>
<div class="home-hero">
    <div class="home-hero__inner">
        <p class="home-hero__eyebrow"><?= e($meta['title'] ?? '') ?></p>
        <?= $html ?>
    </div>
</div>
<?php endif ?>

<section class="home-posts-section">
    <?php
    $posts = get_latest_posts((int) config('blog_per_page', 9), $meta['lang'] ?? $lang ?? 'en');
    ?>
    <h2 class="home-posts-section__heading"><?= t('latest_posts', $lang ?? 'en') ?></h2>
    <?php if (!empty($posts)): ?>
    <div class="post-card-grid">
        <?php foreach ($posts as $post): ?>
            <?php render_partial('post_teaser', ['post' => $post]) ?>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>
