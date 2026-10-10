<?php
require_once __DIR__ . '/inc/config.php';
$isMapPage = true;
$pageTitle = BRAND_TAGLINE;
$visitToken=''; try {$visitToken=ps_start_visit();}catch(Throwable $e){error_log('HerMap visit counter unavailable');}
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head><?php require DIR_INC . '/meta.php'; require DIR_INC . '/css.php'; ?></head>
<body class="map-page">
<?php require DIR_INC . '/header.php'; ?>
<main id="main" class="home-main" tabindex="-1">
<section class="map-main" aria-label="Explore the map">
  <div id="map" role="region" aria-label="Explore India and community reporting patterns"></div>
  <div id="map-fallback" class="map-fallback" hidden>
    <?= ps_icon('map') ?><h2>The map couldn’t load</h2>
    <p>You can still search PIN codes, report and read how HerMap works.</p>
    <button class="button button-secondary" type="button" id="retry-map">Retry map</button>
  </div>
  <section class="search-panel panel" aria-label="Find a location">
    <label for="place-search" class="sr-only">Search PIN, post office or district</label>
    <?= ps_icon('magnifying-glass') ?>
    <input id="place-search" type="search" placeholder="Where are you heading?" autocomplete="off" aria-describedby="search-help" aria-controls="search-results">
    <button class="icon-button" id="clear-search" type="button" aria-label="Clear location and reset India view" hidden><?= ps_icon('xmark') ?></button>
    <div class="search-dropdown" id="search-dropdown" hidden>
      <p id="search-help">Search a PIN, post office or district. Positions are approximate.</p>
      <ul id="search-results" aria-label="Locations"></ul>
    </div>
  </section>
  <section class="area-panel panel" aria-labelledby="area-name">
    <div class="eyebrow"><span class="status-dot"></span> A community looking out for you</div>
    <h1><?= ps_campaign() ?></h1>
    <p class="intro-copy">A little awareness. A more informed journey.<br>Built for the women of India, with care.</p>
    <div class="area-divider"></div>
    <div class="area-heading"><span class="area-icon"><?= ps_icon('location-dot') ?></span><div><span class="small-label">You’re exploring</span><h2 id="area-name">India overview</h2></div><button class="icon-button" id="reset-area" type="button" aria-label="Reset India view"><?= ps_icon('arrow-rotate-left') ?></button></div>
    <label class="small-label filter-label" for="time-window">Reporting window</label>
    <select id="time-window"><option value="30">Past 30 days</option><option value="90" selected>Past 90 days</option><option value="365">Past 12 months</option></select>
    <div class="empty-state" role="status" aria-live="polite">
      <strong id="area-total">Loading report summary…</strong>
      <p id="area-summary">No data does not mean an area is safe.</p>
      <dl id="area-patterns" hidden></dl>
      <ul id="area-categories" hidden></ul>
      <div id="area-messages-wrap" class="area-messages-wrap" hidden>
        <span class="small-label" style="display:block; margin:14px 0 8px; font-weight:600; color:var(--brand); text-align:left;"><?= ps_icon('comment-dots') ?> Community experiences (shared with consent)</span>
        <ul id="area-messages" class="area-messages-list"></ul>
      </div>
    </div>
    <a class="text-link" href="<?= ps_escape(ps_url('methodology')) ?>">Understand the map <?= ps_icon('arrow-right') ?></a>
    <div class="care-note"><?= ps_icon('lock') ?><span>No sign-up. No reporting fee.<br>Your privacy comes first.</span></div>
  </section>
  <div class="preview-label panel"><span class="status-dot"></span> Community reports <span class="preview-detail">· Reviewed &amp; grouped</span></div>
  <aside class="map-legend panel" aria-label="Map legend"><span class="legend-scale" aria-hidden="true"></span><div><strong>Lower → higher reporting activity</strong><span>Grey: insufficient information. Colours do not measure safety.</span></div></aside>
  <div class="map-actions">
    <div class="map-controls panel" aria-label="Map controls">
      <button class="icon-button" id="zoom-in" type="button" aria-label="Zoom in"><?= ps_icon('plus') ?></button>
      <button class="icon-button" id="zoom-out" type="button" aria-label="Zoom out"><?= ps_icon('minus') ?></button>
      <button class="icon-button" id="country-view" type="button" aria-label="Show India and island groups"><?= ps_icon('expand') ?></button>
      <button class="icon-button" id="locate-area" type="button" aria-label="Use my approximate location"><?= ps_icon('location-crosshairs') ?></button>
    </div>
    <button class="button button-primary report-button" type="button" data-open-report><?= ps_icon('plus') ?><span>Report an incident<small>Private review · always free</small></span></button>
  </div>
  <div class="map-notice" id="map-notice" role="status" aria-live="polite"></div>
  <div class="basemap-note">Survey of India outline · Approximate PIN centres · Tap India for a broad area summary</div>
  <a class="map-scroll-link" href="#community-info">FAQs &amp; support <?= ps_icon('arrow-down') ?></a>
</section>
<section class="public-metrics" aria-label="Community totals">
  <div>
    <div class="metric-value">
      <span class="metric-icon" aria-hidden="true"><?= ps_icon('clock-rotate-left') ?></span>
      <strong id="reports-7d">—</strong>
    </div>
    <span>Reports received · last 7 days</span>
  </div>
  <div>
    <div class="metric-value">
      <span class="metric-icon" aria-hidden="true"><?= ps_icon('shield-halved') ?></span>
      <strong id="total-reports">—</strong>
    </div>
    <span>Reports received · all time</span>
  </div>
  <div>
    <div class="metric-value">
      <span class="metric-icon" aria-hidden="true"><?= ps_icon('users') ?></span>
      <strong id="live-visits">—</strong>
    </div>
    <span><i class="live-dot" aria-hidden="true"></i> Active visitors</span>
  </div>
  <div>
    <div class="metric-value">
      <span class="metric-icon" aria-hidden="true"><?= ps_icon('globe') ?></span>
      <strong id="total-visits">—</strong>
    </div>
    <span>Total visitors since launch</span>
  </div>
  <p id="stats-note">Unique visits tracked by 24-hour session. Received reports include submissions awaiting review.</p>
</section>
<?php require DIR_INC . '/home-community.php'; ?>
</main>
<?php require DIR_INC . '/footer.php'; ?>
<dialog id="location-dialog" class="sheet-dialog" aria-labelledby="location-title">
  <div class="dialog-heading"><span class="eyebrow">Your choice, always</span><button class="icon-button" type="button" data-close-dialog aria-label="Close location request"><?= ps_icon('xmark') ?></button></div>
  <h2 id="location-title">Find your approximate area</h2><p>Your browser will ask for permission. Your exact coordinates stay in this page. Only a broad 0.25° grid cell is sent to HerMap to load grouped statistics; it is not added to your report or stored as visitor history.</p>
  <p class="notice-box">The map is served by HerMap. Your device’s location service has its own privacy practices. You can search a PIN instead.</p>
  <div class="dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Cancel</button><button class="button button-primary" type="button" id="confirm-locate">Allow browser request</button></div>
</dialog>
<dialog id="report-dialog" class="sheet-dialog report-dialog" aria-labelledby="report-title">
  <header class="report-modal-header">
    <div class="report-header-content">
      <span class="eyebrow"><span class="status-dot"></span> Your experience matters</span>
      <h2 id="report-title">Report with care than help another woman</h2>
      <p class="report-header-subtitle">Help another woman make an informed choice · Reporting is always free.</p>
    </div>
    <button class="icon-button modal-close-btn" type="button" data-close-dialog aria-label="Close reporting form"><?= ps_icon('xmark') ?></button>
  </header>
  <div class="report-modal-body" id="report-dialog-body">
    <div class="notice-box"><strong>Your report is stored privately.</strong>No name, email or phone number is required. Selected location, date, time, optional venue/online details and any exact point you explicitly choose to share are saved for moderation. Only delayed, broad totals may become public.</div>
    <p class="emergency-note">In immediate danger? <a href="tel:112">Call 112</a>. HerMap cannot dispatch help or file a police complaint.</p>
    <form id="report-form">
      <label for="report-category">Type of experience</label>
      <select id="report-category" required><option value="">Choose a category</option><?php foreach(INCIDENT_CATEGORIES as $key=>$label): ?><option value="<?= ps_escape($key) ?>"><?= ps_escape($label) ?></option><?php endforeach; ?></select>
      <label for="report-place-type">Where did it happen?</label>
      <select id="report-place-type" required><option value="">Choose a place type</option><?php foreach(PLACE_TYPES as $key=>$label): ?><option value="<?= ps_escape($key) ?>"><?= ps_escape($label) ?></option><?php endforeach; ?></select>
      <fieldset id="report-venue-fields" class="venue-fields" hidden disabled>
        <legend id="report-venue-heading">Place details · optional</legend>
        <label id="report-venue-name-label" for="report-venue-name">Place name (optional)</label>
        <input id="report-venue-name" type="text" maxlength="120" autocomplete="off" placeholder="Organisation or public venue name">
        <label id="report-venue-website-label" for="report-venue-website">Official website (optional)</label>
        <input id="report-venue-website" type="url" maxlength="300" autocomplete="off" placeholder="https://example.com" aria-describedby="venue-privacy-hint">
        <p id="venue-privacy-hint" class="form-hint">Do not include employee or survivor names, room numbers, private addresses or personal profiles. Venue details are encrypted for private review and are never displayed on this map.</p>
      </fieldset>
      <fieldset id="report-online-fields" class="venue-fields" hidden disabled>
        <legend>Online context · optional, private</legend>
        <label for="report-online-platform">Platform (optional)</label><input id="report-online-platform" maxlength="60" autocomplete="off" placeholder="For example, Instagram or X">
        <label for="report-online-handle">Harasser’s username / handle (optional)</label><input id="report-online-handle" maxlength="100" autocomplete="off" placeholder="@username" aria-describedby="online-privacy-hint">
        <p id="online-privacy-hint" class="form-hint">Only include the account involved in this experience. Do not add your own handle, real names, contact details or a child’s account. These details are encrypted for private review and never appear on the public map. You can leave both fields blank.</p>
      </fieldset>
      <label for="report-country">Country</label><select id="report-country" required><option value="101">India</option><option value="coming-soon" disabled>More Coming Soon</option></select>
      <label for="report-state">State / Union Territory</label><select id="report-state" required><option value="">Loading states…</option></select>
      <label for="report-city">City / district (optional filter)</label><select id="report-city"><option value="">All in selected state</option></select>
      <label for="report-pin-search">PIN, post office or district</label><input id="report-pin-search" type="search" autocomplete="off" maxlength="80" placeholder="Select a state, then enter a PIN or place" required>
      <label for="report-post-office">Choose an approximate location</label><select id="report-post-office" required><option value="">Search above to find a post office</option></select>
      <p class="form-hint">Choose the nearest relevant public post office. This identifies a broad reporting area, not an exact incident point. Directory entries can be outdated.</p>
      <details class="optional-location-details">
        <summary><span><?= ps_icon('location-dot') ?> Share precise point or coordinates (optional)</span><?= ps_icon('chevron-down') ?></summary>
        <div class="optional-location-body">
          <p class="form-hint">Use our local map, enter coordinates, or request your device location. No paid map API is used. Select a nearby PIN first. This overview map has no street-level imagery.</p>
          <button id="open-location-picker" class="button button-secondary" type="button" aria-expanded="false" aria-controls="location-picker-panel" style="margin-bottom:10px;"><?= ps_icon('map-location-dot') ?> Choose a point on map</button>
          <div id="location-picker-panel" hidden>
            <div id="report-location-map" role="region" aria-label="Optional private location picker"></div>
            <div class="picker-controls" aria-label="Location picker controls">
              <button type="button" id="picker-zoom-in" class="icon-button" aria-label="Zoom in on location picker"><?= ps_icon('plus') ?></button>
              <button type="button" id="picker-zoom-out" class="icon-button" aria-label="Zoom out on location picker"><?= ps_icon('minus') ?></button>
            </div>
            <p class="form-hint">Tap the map to choose a point. Local overview geography: <a href="https://surveyofindia.gov.in/pages/outline-maps-of-india" target="_blank" rel="noopener noreferrer">Survey of India</a> / <a href="https://nwdp.nwic.gov.in/hi/dataset/state-boundary" target="_blank" rel="noopener noreferrer">NWIC</a>.</p>
            <div class="dialog-actions">
              <button type="button" id="picker-locate" class="button button-secondary"><?= ps_icon('location-crosshairs') ?> Request device location</button>
              <button type="button" id="clear-shared-location" class="button button-secondary">Clear point</button>
            </div>
          </div>
          <div class="coordinate-fields">
            <div><label for="report-latitude">Latitude (optional)</label><input id="report-latitude" type="number" min="6" max="38" step="any" inputmode="decimal" placeholder="28.613900"></div>
            <div><label for="report-longitude">Longitude (optional)</label><input id="report-longitude" type="number" min="68" max="98" step="any" inputmode="decimal" placeholder="77.209000"></div>
          </div>
          <label class="checkbox-label" style="margin-top:10px;"><input type="checkbox" id="report-share-location"><span>I choose to save this selected point privately. I understand it could identify a sensitive place.</span></label>
          <p class="form-hint" style="margin-top:4px;">Leave this unchecked to send only your PIN-based broad location. A shared point is encrypted for moderators and used to assign a broad map cell. Exact coordinates never appear publicly; avoid homes or places that identify a survivor or child.</p>
          <p id="picker-status" class="form-hint" role="status"></p>
        </div>
      </details>
      <label for="report-date">Incident date</label><input id="report-date" type="date" required max="<?= (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d') ?>">
      <label for="report-time">Approximate time (IST)</label><input id="report-time" type="time" required>
      <p class="form-hint">Date and time stay private. Report only incidents within the past year. For online incidents, select a relevant broad locality without identifying anyone.</p>
      <div class="report-message-group">
        <label for="report-message">What happened? (optional message · up to 1,000 characters)</label>
        <textarea id="report-message" maxlength="1000" rows="3" placeholder="Briefly describe what happened without sharing real names, phone numbers, or private identities." aria-describedby="report-message-hint"></textarea>
        <div class="field-meta" style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
          <p id="report-message-hint" class="form-hint" style="margin:0;">Max 1,000 characters. Encrypted in storage.</p>
          <span id="report-message-count" class="form-hint" style="margin:0; font-variant-numeric:tabular-nums; flex-shrink:0;">0/1000</span>
        </div>
        <label class="checkbox-label" style="margin-top:10px;">
          <input type="checkbox" id="report-share-message" name="share_message">
          <span>Allow this message to be shown publicly so other people can understand the situation better.</span>
        </label>
        <p class="form-hint" style="margin-top:2px;">If checked, moderators may include your message anonymously in the broad area summary after review. Never include real names, phone numbers, or private identities.</p>
      </div>
      <div class="human-check"><label for="report-captcha" id="captcha-question">Loading human check…</label><div><input id="report-captcha" inputmode="numeric" pattern="[0-9]+" maxlength="3" autocomplete="off" required aria-describedby="captcha-help"><button type="button" class="icon-button" id="refresh-captcha" aria-label="Get a new human check"><?= ps_icon('arrow-rotate-left') ?></button></div><p class="form-hint" id="captcha-help">Solve the question. It changes with each attempt and expires after 10 minutes.</p></div>
      <div class="honeypot" aria-hidden="true"><label for="website-check">Leave this empty</label><input id="website-check" tabindex="-1" autocomplete="off"></div>
      <label class="checkbox-label"><input id="report-adult" type="checkbox" required><span>I am 18 or older, and this report does not identify a child or survivor.</span></label>
      <label class="checkbox-label"><input id="report-consent" type="checkbox" required><span>I am reporting honestly and agree to the privacy policy and terms. I understand these details are saved for private review and delayed grouped publication.</span></label>
    </form>
    <section id="saved-receipt" class="notice-box" hidden>
      <h3>Your report is saved</h3>
      <p>Keep this private receipt to check or delete your report. It cannot be recovered if lost. Treat it like a password.</p>
      <label for="new-receipt">Private receipt</label>
      <input id="new-receipt" readonly autocomplete="off">
      <div class="dialog-actions" style="margin-top:14px;">
        <button class="button button-secondary copy-receipt-btn" type="button" id="copy-receipt"><?= ps_icon('copy') ?> <span id="copy-btn-text">Copy receipt</span></button>
        <button class="button button-primary" type="button" data-close-dialog>Close window</button>
      </div>
      <p id="receipt-copy-status" role="status"></p>
    </section>
    <details id="receipt-tools">
      <summary>Check or delete an existing report</summary>
      <form id="receipt-form">
        <label for="existing-receipt">Private receipt</label>
        <input id="existing-receipt" autocomplete="off" maxlength="48" pattern="hermap[0-9]{9}|[a-f0-9]{48}" placeholder="e.g. hermap000000001" required>
        <div class="dialog-actions">
          <button class="button button-secondary" type="submit" name="action" value="status">Check status</button>
          <button class="button button-secondary" type="submit" name="action" value="delete">Delete my report</button>
        </div>
        <p id="receipt-result" role="status" aria-live="polite"></p>
      </form>
    </details>
    <p class="form-hint">Moderation checks privacy, duplication and abuse; it does not verify that an incident happened. Small groups are withheld; eligible reports wait at least <?= PUBLICATION_DELAY_HOURS ?> hours after approval.</p>
    <div class="dialog-policy-links">
      <a href="<?= ps_escape(ps_url('privacy-policy')) ?>">Privacy policy</a>
      <a href="<?= ps_escape(ps_url('terms-and-conditions')) ?>">Terms &amp; conditions</a>
    </div>
  </div>
  <footer class="report-modal-footer" id="report-dialog-footer">
    <button class="button button-primary w-full" id="submit-report" type="submit" form="report-form" <?= INTAKE_ENABLED?'':'disabled' ?>>Save report privately <?= ps_icon('arrow-right') ?></button>
    <p id="report-result" role="status" aria-live="polite"></p>
  </footer>
</dialog>
<div id="map-settings" hidden data-api-url="<?= ps_escape(ps_url('api/')) ?>" data-outline-url="<?= ps_escape(ps_asset('data/india-outline.geojson')) ?>" data-states-url="<?= ps_escape(ps_asset('data/india-states.geojson')) ?>" data-visit-token="<?= ps_escape($visitToken) ?>" data-intake="<?= INTAKE_ENABLED?'1':'0' ?>"></div>
<?php require DIR_INC . '/js.php'; ?>
</body></html>
