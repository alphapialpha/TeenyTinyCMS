<?php
/**
 * Footer partial. Variables: $site_title (optional)
 * Note: date() and config() are evaluated at build time.
 */
?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <span>&copy; <?= date('Y') ?> <?= e(config('copyright_notice', config('site_title', 'TeenyTinyCMS'))) ?></span>
        <span class="site-footer__credit">Powered by <a href="https://github.com/AlphaPiAlpha/TeenyTinyCMS">TeenyTinyCMS</a></span>
    </div>
</footer>
