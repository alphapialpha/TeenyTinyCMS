<?php /** Tag index template. Variables: $meta, $lang, $tag_name, $posts */ ?>
<section class="tag-page">
    <h1 class="tag-page__title"><?= t('posts_tagged', $lang ?? 'en') ?> <span class="tag-page__name"><?= e($tag_name ?? '') ?></span></h1>
    <p class="tag-page__subtitle"><?= count($posts ?? []) ?> post<?= count($posts ?? []) !== 1 ? 's' : '' ?></p>

    <?php if (empty($posts)): ?>
        <p class="tag-page__empty"><?= t('no_posts_tagged', $lang ?? 'en') ?></p>
    <?php else: ?>
        <div class="post-card-grid">
            <?php foreach ($posts as $post): ?>
                <?php render_partial('post_teaser', ['post' => $post]) ?>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
