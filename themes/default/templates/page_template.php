<?php /** Page template. Variables: $meta, $html */ ?>
<article class="page">
    <h1 class="page-title"><?= e($meta['title'] ?? '') ?></h1>
    <?php if (!empty($meta['date'])): ?>
        <p class="page-meta">
            <time datetime="<?= e($meta['date']) ?>"><?= e($meta['date']) ?></time>
        </p>
    <?php endif ?>
    <hr class="page-divider">
    <div class="page-body">
        <?= $html ?? '' ?>
    </div>
</article>
