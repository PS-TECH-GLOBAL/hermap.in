<script src="<?= ps_escape(ps_asset('js/select.js')) ?>" defer></script>
<script src="<?= ps_escape(ps_asset('js/content.js')) ?>" defer></script>
<script src="<?= ps_escape(ps_asset('js/app.js')) ?>" defer></script>
<?php if(($route??'')!=='admin' && ($visitToken??'')!==''): ?>
<div id="visitor-settings" hidden data-api-url="<?= ps_escape(ps_url('api/')) ?>" data-visit-token="<?= ps_escape($visitToken) ?>"></div>
<script src="<?= ps_escape(ps_asset('js/visitors.js')) ?>" defer></script>
<?php endif; ?>
<?php if (($route ?? '')==='admin'): ?><script src="<?= ps_escape(ps_asset('js/admin.js')) ?>" defer></script><?php endif; ?>
<?php if ($isMapPage ?? false): ?>
<script src="<?= ps_escape(ps_asset('js/map.js')) ?>" type="module"></script>
<script src="<?= ps_escape(ps_asset('js/location-picker.js')) ?>" type="module"></script>
<?php endif; ?>
