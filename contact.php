<?php $pageTitle = 'Contact and privacy enquiries'; require __DIR__ . '/inc/content-start.php'; ?>
<span class="eyebrow">We’re here to listen</span><h1>Get in touch.</h1>
<p class="lead">Questions, suggestions or concerns about privacy and content? Reach the HerMap team.</p>
<div class="contact-card panel"><span class="small-label">Support &amp; privacy enquiries</span><p><a class="contact-email" href="mailto:<?= ps_escape(SUPPORT_EMAIL) ?>"><?= ps_escape(SUPPORT_EMAIL) ?></a></p><p>For a privacy or content concern, use “Privacy request” or “Content concern” in the subject. Share only the minimum information needed to explain the issue. Do not send identity documents, survivor names, intimate images or exact incident locations.</p></div>
<h2>Grievance officer and operator</h2>
<?php if (LEGAL_OPERATOR !== '' && GRIEVANCE_OFFICER !== '' && GRIEVANCE_EMAIL !== ''): ?>
<p>Operator: <?= ps_escape(LEGAL_OPERATOR) ?><br>Grievance officer: <?= ps_escape(GRIEVANCE_OFFICER) ?><br>Email: <a href="mailto:<?= ps_escape(GRIEVANCE_EMAIL) ?>"><?= ps_escape(GRIEVANCE_EMAIL) ?></a></p>
<?php else: ?><p>The legal operator and designated grievance officer have not yet been confirmed. These details and the applicable complaint process must be published before report intake opens. The support address above is the current contact; it does not imply that a statutory officer has been appointed.</p><?php endif; ?>
<h2>Need immediate help?</h2><p>In an emergency in India, <a href="tel:112">call 112</a>. Support email is not an emergency service and has no guaranteed response time during this development stage.</p>
<?php require DIR_INC . '/content-end.php'; ?>
