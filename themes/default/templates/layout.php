<?php
/**
 * Layout – global HTML wrapper.
 * Variables: $title, $content, $lang, $site_title
 */
?>
<!DOCTYPE html>
<html lang="<?= e($lang ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? '') . ' – ' . ($site_title ?? 'TeenyTinyCMS')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
    <?php render_partial('header', ['lang' => $lang, 'site_title' => $site_title]) ?>

    <main>
        <?= $content ?? '' ?>
    </main>

    <?php render_partial('language_switcher', ['lang' => $lang, 'slug' => $slug ?? '', 'type' => $type ?? '']) ?>
    <?php render_partial('footer', ['site_title' => $site_title]) ?>

    <script>var BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
