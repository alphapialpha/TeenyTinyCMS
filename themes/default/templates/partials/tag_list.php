<?php /** Tag list partial. Variables: $tags (string[]), $lang */ ?>
<ul class="tag-list">
    <?php foreach ($tags ?? [] as $tag): ?>
        <li class="tag-list__item">
            <a href="<?= url_for('/tag/' . rawurlencode($tag), $lang ?? 'en') ?>"><?= e($tag) ?></a>
        </li>
    <?php endforeach ?>
</ul>
