<?php 
$route = 'contact';
$pageTitle = 'Contact and privacy enquiries'; 
require __DIR__ . '/inc/content-start.php'; 
?>
<span class="eyebrow">We’re here to listen</span><h1>Get in touch.</h1>
<p class="lead">Questions, suggestions or concerns about privacy, content or statutory grievances? Reach the HerMap team directly.</p>

<div class="panel contact-form-panel" style="padding: 24px; margin: 24px 0;">
  <h2 style="margin-top:0;">Send a message</h2>
  <p>All inquiries are securely saved in our private database and reviewed by authorized moderators.</p>

  <form id="contact-form" style="display:flex; flex-direction:column; gap:16px;">
    <div>
      <label for="contact-category" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px;">Purpose of message</label>
      <select id="contact-category" required style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:9px; padding:8px 12px;">
        <option value="general">General inquiry / Feedback</option>
        <option value="privacy_request">Privacy request / Data removal</option>
        <option value="content_concern">Content or moderation concern</option>
        <option value="legal_grievance">Statutory grievance (IT Rules / DPDP)</option>
        <option value="bug_report">Technical issue or bug report</option>
      </select>
    </div>

    <div>
      <label for="contact-name" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px;">Your name (optional)</label>
      <input id="contact-name" type="text" maxlength="100" autocomplete="name" placeholder="Name or alias" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:9px; padding:8px 12px;">
    </div>

    <div>
      <label for="contact-email" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px;">Email address (for replies)</label>
      <input id="contact-email" type="email" maxlength="255" autocomplete="email" required placeholder="you@example.com" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:9px; padding:8px 12px;">
    </div>

    <div>
      <label for="contact-subject" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px;">Subject</label>
      <input id="contact-subject" type="text" maxlength="150" required placeholder="Brief summary of your inquiry" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:9px; padding:8px 12px;">
    </div>

    <div>
      <label for="contact-message" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px;">Message</label>
      <textarea id="contact-message" rows="5" maxlength="2500" required placeholder="Explain your inquiry or concern. Do not send identity cards, survivor names, intimate images or exact incident coordinates." style="width:100%; border:1px solid var(--line); border-radius:9px; padding:10px 12px; font-family:inherit;"></textarea>
    </div>

    <div class="human-check">
      <label for="contact-captcha" id="captcha-question">Loading human check…</label>
      <div style="display:flex; gap:10px; align-items:center;">
        <input id="contact-captcha" inputmode="numeric" pattern="[0-9]+" maxlength="3" autocomplete="off" required aria-describedby="captcha-help" style="width:100px; min-height:44px; border:1px solid var(--line); border-radius:9px; padding:8px 12px;">
        <button type="button" class="icon-button" id="refresh-captcha" aria-label="Get a new human check"><?= ps_icon('arrow-rotate-left') ?></button>
      </div>
      <p class="form-hint" id="captcha-help">Solve the question. It changes with each attempt.</p>
    </div>

    <div class="honeypot" aria-hidden="true" style="display:none;"><label for="website-check">Leave this empty</label><input id="website-check" tabindex="-1" autocomplete="off"></div>

    <button class="button button-primary" id="submit-contact" type="submit" style="align-self:flex-start;">Submit message <?= ps_icon('arrow-right') ?></button>
  </form>

  <p id="contact-result" role="status" aria-live="polite" style="margin-top:14px; font-size:14px;"></p>
  <div id="contact-ticket" class="notice-box" hidden style="margin-top:16px;">
    <h3>Message received &amp; logged</h3>
    <p>Your inquiry is saved in our database. Your tracking ticket reference is: <strong id="ticket-number" style="font-family:monospace; font-size:16px; color:var(--brand);"></strong>. Please keep this for reference.</p>
  </div>
</div>

<div class="contact-card panel"><span class="small-label">Direct email</span><p><a class="contact-email" href="mailto:<?= ps_escape(SUPPORT_EMAIL) ?>"><?= ps_escape(SUPPORT_EMAIL) ?></a></p><p>For a direct email inquiry, use “Privacy request” or “Content concern” in the subject. Share only the minimum information needed to explain the issue.</p></div>
<h2>Grievance officer and operator</h2>
<?php if (LEGAL_OPERATOR !== '' && GRIEVANCE_OFFICER !== '' && GRIEVANCE_EMAIL !== ''): ?>
<p>Operator: <?= ps_escape(LEGAL_OPERATOR) ?><br>Grievance officer: <?= ps_escape(GRIEVANCE_OFFICER) ?><br>Email: <a href="mailto:<?= ps_escape(GRIEVANCE_EMAIL) ?>"><?= ps_escape(GRIEVANCE_EMAIL) ?></a></p>
<?php else: ?><p>The legal operator and designated grievance officer have not yet been confirmed. These details and the applicable complaint process must be published before report intake opens. The support address above is the current contact; it does not imply that a statutory officer has been appointed.</p><?php endif; ?>
<h2>Need immediate help?</h2><p>In an emergency in India, <a href="tel:112">call 112</a>. Support contact is not an emergency service and has no guaranteed response time during this development stage.</p>
<?php require DIR_INC . '/content-end.php'; ?>
