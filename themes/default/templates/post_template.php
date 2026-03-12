<?php /** Post template. Variables: $meta, $html */ ?>
<article class="post">
    <header class="post-hero">
        <?php if (!empty($meta['tags'])): ?>
            <p class="post-hero__eyebrow">
                <?= implode(' &middot; ', array_map('htmlspecialchars', (array) $meta['tags'])) ?>
            </p>
        <?php else: ?>
            <p class="post-hero__eyebrow">Post</p>
        <?php endif ?>

        <h1><?= e($meta['title'] ?? '') ?></h1>

        <p class="post-meta">
            <?php if (!empty($meta['date'])): ?>
                <time datetime="<?= e($meta['date']) ?>"><?= e($meta['date']) ?></time>
            <?php endif ?>
            <?php if (!empty($meta['date']) && !empty($meta['author'])): ?>
                <span class="post-meta__dot">&middot;</span>
            <?php endif ?>
            <?php if (!empty($meta['author'])): ?>
                <span><?= e($meta['author']) ?></span>
            <?php endif ?>
        </p>

        <?php if (!empty($meta['tags'])): ?>
            <?php render_partial('tag_list', ['tags' => $meta['tags'], 'lang' => $meta['lang'] ?? 'en']) ?>
        <?php endif ?>
    </header>

    <div class="post-body">
        <?= $html ?? '' ?>
    </div>
</article>
