<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header <?= ($isMapPage ?? false) ? 'map-header' : '' ?>">
  <a class="brand" href="<?= ps_escape(ps_url()) ?>" aria-label="HerMap.in home">
    <?= ps_logo() ?><?php if (!is_file(ROOT_PATH . '/assets/images/site/logo-website.png')): ?><span class="brand-tagline"><?= BRAND_TAGLINE ?></span><?php endif; ?>
  </a>
  <nav aria-label="Main navigation" class="desktop-nav">
    <a href="<?= ps_escape(ps_url('purpose')) ?>">Our purpose</a>
    <a href="<?= ps_escape(ps_url('how-it-works')) ?>">How the map works</a>
    <a href="<?= ps_escape(ps_url('reports')) ?>">Community stories</a>
    <a href="<?= ps_escape(ps_url('#support')) ?>">Find support</a>
    <a class="emergency-link" href="tel:112"><?= ps_icon('phone') ?> Emergency 112</a>
  </nav>
  <button class="icon-button menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu"><?= ps_icon('bars') ?></button>
  <nav id="mobile-menu" class="mobile-menu" aria-label="Mobile navigation" hidden>
    <a href="<?= ps_escape(ps_url('purpose')) ?>">Our purpose</a>
    <a href="<?= ps_escape(ps_url('how-it-works')) ?>">How the map works</a>
    <a href="<?= ps_escape(ps_url('reports')) ?>">Community stories</a>
    <a href="<?= ps_escape(ps_url('privacy')) ?>">Privacy policy</a>
    <a href="<?= ps_escape(ps_url('terms')) ?>">Terms &amp; conditions</a>
    <a href="<?= ps_escape(ps_url('#faqs')) ?>">FAQs</a>
    <a href="<?= ps_escape(ps_url('#support')) ?>">Find support</a>
    <a href="tel:112">Emergency: call 112</a>
  </nav>
</header>
