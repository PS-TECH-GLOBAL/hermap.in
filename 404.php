<?php $pageTitle = 'Page not found'; require __DIR__ . '/inc/content-start.php'; ?>
<span class="eyebrow">Let’s find your way back</span><h1>That page isn’t here.</h1><p class="lead">Return to the map to explore HerMap.</p><a class="button button-primary" href="<?= ps_escape(ps_url()) ?>">Back to the map <?= ps_icon('arrow-right') ?></a>
<?php require DIR_INC . '/content-end.php'; ?>
