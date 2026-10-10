<?php $pageTitle = $pageTitle ?? BRAND_TAGLINE; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#240348">
<link rel="icon" type="image/png" sizes="200x200" href="<?= ps_escape(ps_asset('images/site/HerMap.in-favicon.png')) ?>">
<link rel="apple-touch-icon" sizes="200x200" href="<?= ps_escape(ps_asset('images/site/HerMap.in-favicon.png')) ?>">
<meta name="description" content="HerMap.in helps women make informed decisions through community-reported incidents. Free to view and report. Know Before You Go.">
<?php if (!PROD): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<title><?= ps_escape($pageTitle) ?> · HerMap.in</title>
