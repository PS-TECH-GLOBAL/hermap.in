<?php
declare(strict_types=1);

$pageTitle = 'Community Stories & Reports';
require_once __DIR__ . '/inc/config.php';

$db = ps_db();

// 1. Process and sanitize filter inputs
$selectedCountry = 101; // India
$selectedState = max(0, (int)($_GET['state_id'] ?? 0));
$selectedCity = max(0, (int)($_GET['city_id'] ?? 0));
$selectedPin = trim((string)($_GET['pincode'] ?? ''));
if (strlen($selectedPin) > 10 || preg_match('/[^0-9]/', $selectedPin)) {
    $selectedPin = '';
}
$selectedCategory = trim((string)($_GET['category'] ?? ''));
if (!isset(INCIDENT_CATEGORIES[$selectedCategory])) {
    $selectedCategory = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// 2. Fetch States for filter dropdown
$states = $db->query('SELECT id, name FROM hm_states ORDER BY name')->fetchAll();

// 3. Fetch Cities for selected state (if state is selected)
$cities = [];
if ($selectedState > 0) {
    $cityStmt = $db->prepare('SELECT id, name FROM hm_cities WHERE state_id=? ORDER BY name');
    $cityStmt->execute([$selectedState]);
    $cities = $cityStmt->fetchAll();
}

// 4. Build query for approved, consented stories past delay guard
$delayCutoff = gmdate('Y-m-d H:i:s', time() - PUBLICATION_DELAY_HOURS * 3600);
$where = [
    "r.status = 'approved'",
    "r.approved_at <= ?",
    "r.share_message = 1",
    "r.message_encrypted IS NOT NULL",
    "r.message_encrypted != ''"
];
$params = [$delayCutoff];

if ($selectedState > 0) {
    $where[] = "r.state_id = ?";
    $params[] = $selectedState;
}
if ($selectedCity > 0) {
    $where[] = "r.city_id = ?";
    $params[] = $selectedCity;
}
if ($selectedPin !== '') {
    $where[] = "p.pincode LIKE ?";
    $params[] = $selectedPin . '%';
}
if ($selectedCategory !== '') {
    $where[] = "r.category = ?";
    $params[] = $selectedCategory;
}

$whereSql = implode(' AND ', $where);

// Count total matching stories
$countStmt = $db->prepare("SELECT COUNT(*) FROM hm_incidents r JOIN hm_states s ON s.id=r.state_id LEFT JOIN hm_cities c ON c.id=r.city_id JOIN hm_post_offices p ON p.id=r.post_office_id WHERE $whereSql");
$countStmt->execute($params);
$totalReports = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalReports / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch paginated stories
$reportStmt = $db->prepare("SELECT r.id, r.category, r.place_type, r.incident_date, r.message_encrypted, r.approved_at,
    s.name AS state_name, c.name AS city_name, p.office_name, p.pincode
    FROM hm_incidents r
    JOIN hm_states s ON s.id=r.state_id
    LEFT JOIN hm_cities c ON c.id=r.city_id
    JOIN hm_post_offices p ON p.id=r.post_office_id
    WHERE $whereSql
    ORDER BY r.incident_date DESC, r.approved_at DESC, r.id DESC
    LIMIT $perPage OFFSET $offset");
$reportStmt->execute($params);
$rawStories = $reportStmt->fetchAll();

$stories = [];
foreach ($rawStories as $row) {
    $msg = ps_decrypt_string($row['message_encrypted']);
    if ($msg !== '' && $msg !== 'Unable to decrypt') {
        $row['message'] = $msg;
        $stories[] = $row;
    }
}

$isFiltered = ($selectedState > 0 || $selectedCity > 0 || $selectedPin !== '' || $selectedCategory !== '');

function reports_url_with_page(int $targetPage): string {
    $query = $_GET;
    $query['page'] = $targetPage;
    return ps_url('reports?' . http_build_query($query));
}

require __DIR__ . '/inc/content-start.php';
?>

<div class="reports-hero">
  <div class="eyebrow"><?= ps_icon('shield-heart') ?> Anonymized Community Awareness</div>
  <h1>Community Experiences &amp; Stories</h1>
  <p class="lead">First-hand accounts shared anonymously by women across India with explicit consent. Read real experiences by location to understand local situations and make informed decisions.</p>
  <div class="hero-actions">
    <a href="<?= ps_escape(ps_url('#report')) ?>" class="button button-primary" data-open-report><?= ps_icon('plus') ?> Share an experience</a>
    <a href="<?= ps_escape(ps_url()) ?>" class="button button-secondary"><?= ps_icon('map-location-dot') ?> View safety map</a>
  </div>
</div>

<section class="reports-filter-panel" aria-label="Stories filter">
  <div class="filter-header">
    <h2><?= ps_icon('sliders') ?> Filter stories by location &amp; category</h2>
    <span class="filter-count"><?= number_format($totalReports) ?> <?= $totalReports === 1 ? 'experience' : 'experiences' ?> shared</span>
  </div>

  <form method="get" action="<?= ps_escape(ps_url('reports')) ?>" class="reports-filter-form">
    <div class="filter-field">
      <label for="filter-country">Country</label>
      <select id="filter-country" name="country">
        <option value="101" selected>India (IN)</option>
      </select>
    </div>

    <div class="filter-field">
      <label for="filter-state">State / UT</label>
      <select id="filter-state" name="state_id">
        <option value="">All States &amp; UTs</option>
        <?php foreach ($states as $st): ?>
          <option value="<?= (int)$st['id'] ?>" <?= $selectedState === (int)$st['id'] ? 'selected' : '' ?>><?= ps_escape($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field">
      <label for="filter-city">City / District</label>
      <select id="filter-city" name="city_id" <?= $selectedState === 0 ? 'disabled' : '' ?>>
        <option value="">All Cities</option>
        <?php foreach ($cities as $ct): ?>
          <option value="<?= (int)$ct['id'] ?>" <?= $selectedCity === (int)$ct['id'] ? 'selected' : '' ?>><?= ps_escape($ct['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field">
      <label for="filter-pincode">PIN code</label>
      <input type="text" id="filter-pincode" name="pincode" value="<?= ps_escape($selectedPin) ?>" placeholder="e.g. 110001" maxlength="6" inputmode="numeric" pattern="[0-9]*">
    </div>

    <div class="filter-field" style="grid-column: span 2;">
      <label for="filter-category">Incident Category</label>
      <select id="filter-category" name="category">
        <option value="">All categories</option>
        <?php foreach (INCIDENT_CATEGORIES as $key => $label): ?>
          <option value="<?= ps_escape($key) ?>" <?= $selectedCategory === $key ? 'selected' : '' ?>><?= ps_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-actions">
      <?php if ($isFiltered): ?>
        <a href="<?= ps_escape(ps_url('reports')) ?>" class="button button-secondary"><?= ps_icon('arrow-rotate-left') ?> Clear filters</a>
      <?php endif; ?>
      <button type="submit" class="button button-primary"><?= ps_icon('filter') ?> Apply filters</button>
    </div>
  </form>
</section>

<?php if (!empty($stories)): ?>
  <div class="stories-list" role="feed" aria-label="Community stories">
    <?php foreach ($stories as $story): ?>
      <article class="story-card">
        <div class="story-header">
          <div class="story-tags">
            <span class="badge-category"><?= ps_escape(INCIDENT_CATEGORIES[$story['category']] ?? $story['category']) ?></span>
            <span class="badge-place"><?= ps_icon('building') ?> <?= ps_escape(PLACE_TYPES[$story['place_type']] ?? $story['place_type']) ?></span>
          </div>
          <time class="story-date" datetime="<?= ps_escape($story['incident_date']) ?>"><?= ps_escape(date('d M Y', strtotime($story['incident_date']))) ?></time>
        </div>

        <div class="story-location">
          <?= ps_icon('location-dot') ?>
          <span><?= ps_escape($story['office_name'] . ($story['city_name'] ? ', ' . $story['city_name'] : '') . ', ' . $story['state_name']) ?> <small>(PIN <?= ps_escape($story['pincode']) ?>)</small></span>
        </div>

        <div class="story-body">
          <p class="story-text"><?= nl2br(ps_escape($story['message'])) ?></p>
        </div>

        <div class="story-footer">
          <span><?= ps_icon('check-double') ?> Verified by moderators · Shared with consent</span>
          <span>Broad area context · Confidential</span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <div class="pagination-info">Showing <?= min($totalReports, ($page - 1) * $perPage + 1) ?>–<?= min($totalReports, $page * $perPage) ?> of <?= number_format($totalReports) ?> stories (Page <?= $page ?> of <?= $totalPages ?>)</div>
      <nav class="pagination-bar" aria-label="Stories pagination">
        <?php if ($page > 1): ?>
          <a href="<?= ps_escape(reports_url_with_page($page - 1)) ?>" rel="prev">&laquo; Previous</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1): ?>
          <a href="<?= ps_escape(reports_url_with_page(1)) ?>">1</a>
          <?php if ($start > 2): ?><span class="page-dots">&hellip;</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="page-current" aria-current="page"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= ps_escape(reports_url_with_page($i)) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><span class="page-dots">&hellip;</span><?php endif; ?>
          <a href="<?= ps_escape(reports_url_with_page($totalPages)) ?>"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= ps_escape(reports_url_with_page($page + 1)) ?>" rel="next">Next &raquo;</a>
        <?php endif; ?>
      </nav>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-stories-box">
    <div class="empty-icon"><?= ps_icon('comments') ?></div>
    <h3>No community stories found</h3>
    <p>We don't have any approved, publicly shared experiences matching these filters yet. Reports are only listed when reporters specifically choose to share their experience publicly to help others.</p>
    <div class="empty-actions">
      <?php if ($isFiltered): ?>
        <a href="<?= ps_escape(ps_url('reports')) ?>" class="button button-secondary">View all stories</a>
      <?php endif; ?>
      <button type="button" class="button button-primary" data-open-report><?= ps_icon('plus') ?> Share an experience</button>
    </div>
  </div>
<?php endif; ?>

<?php require DIR_INC . '/content-end.php'; ?>
