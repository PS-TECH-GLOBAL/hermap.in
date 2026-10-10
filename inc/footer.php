<footer class="site-footer <?= ($isMapPage ?? false) ? 'map-footer' : '' ?>">
  <div class="footer-credits">
    <span>Creator <a href="https://x.com/iamitpkumar" target="_blank" rel="noopener noreferrer">Amit P. Kumar <?= ps_icon('arrow-up-right-from-square') ?></a></span>
    <span>Crafted with <i class="fa-solid fa-heart" aria-hidden="true"></i><span class="sr-only">love</span> by <a href="https://www.pstechglobal.com" target="_blank" rel="noopener noreferrer">PS TECH GLOBAL</a></span>
  </div>
  <a class="footer-support" href="mailto:<?= ps_escape(SUPPORT_EMAIL) ?>"><?= ps_escape(SUPPORT_EMAIL) ?></a>
  <nav class="footer-social" aria-label="HerMap official social accounts">
    <a href="https://x.com/HERMAPOFFICIAL" target="_blank" rel="noopener noreferrer" aria-label="HerMap on X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i><span>X</span></a>
    <a href="https://www.instagram.com/HERMAPOFFICIAL" target="_blank" rel="noopener noreferrer" aria-label="HerMap on Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i><span>Instagram</span></a>
  </nav>
  <nav class="footer-links" aria-label="Footer navigation">
    <a href="<?= ps_escape(ps_url('privacy')) ?>">Privacy</a>
    <a href="<?= ps_escape(ps_url('terms')) ?>">Terms</a>
    <a href="<?= ps_escape(ps_url('contact')) ?>">Contact</a>
    <span>&copy; <?= date('Y') ?> HerMap.in</span>
  </nav>
</footer>
