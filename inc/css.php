<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/tailwind.min.css')) ?>">
<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/fontawesome.min.css')) ?>">
<?php if ($isMapPage ?? false): ?><link rel="stylesheet" href="<?= ps_escape(ps_asset('css/maplibre.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/header.css')) ?>">
<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/footer.css')) ?>">
<link rel="stylesheet" href="<?= ps_escape(ps_asset('css/enhancements.css')) ?>">
<?php if ($isMapPage ?? false): ?><link rel="stylesheet" href="<?= ps_escape(ps_asset('css/community.css')) ?>"><?php endif; ?>
<?php if (($route ?? '')==='admin'): ?><link rel="stylesheet" href="<?= ps_escape(ps_asset('css/admin.css')) ?>"><?php endif; ?>
