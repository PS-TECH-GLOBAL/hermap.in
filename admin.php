<?php
require_once __DIR__.'/inc/config.php';
$route='admin';
ps_session(); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$error='';$notice='';
try {
 if(PROD && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) throw new HerMapError('Admin access requires HTTPS.',503);
 if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)throw new HerMapError('The request is too large.',413);
    foreach($_POST as $value)if(!is_string($value))throw new HerMapError('Invalid form field.');
    ps_require_csrf($_POST['csrf'] ?? null);$action=$_POST['action'] ?? '';
    
    // 1. Admin Login
    if($action==='login') {
        ps_rate_limit('admin-login',10);
        $username=trim(substr((string)($_POST['username'] ?? ''),0,100));
        $password=substr((string)($_POST['password'] ?? ''),0,256);
        $otp=trim((string)($_POST['otp'] ?? ''));
        $authenticatedUser=null;
        $db=ps_db();
        
        // Check database hm_admins first
        $stmt=$db->prepare('SELECT * FROM hm_admins WHERE username=? AND is_active=1 LIMIT 1');
        $stmt->execute([$username]);
        $adminRow=$stmt->fetch();
        if($adminRow) {
            $secret=$adminRow['totp_secret'];
            $totpOK=false;
            if(strlen($secret)>=16 && preg_match('/^\d{6}$/',$otp)) {
                for($offset=-1;$offset<=1;$offset++) $totpOK=$totpOK || hash_equals(ps_totp($secret,(int)floor(time()/30)+$offset),$otp);
            }
            if(password_verify($password,$adminRow['password_hash']) && $totpOK) {
                $authenticatedUser=$adminRow['username'];
                $_SESSION['admin_role']=$adminRow['role'];
                $db->prepare('UPDATE hm_admins SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$adminRow['id']]);
            }
        } else {
            // Fallback to .env admin credentials
            $envUser=getenv('ADMIN_USERNAME') ?: '';
            $secret=getenv('ADMIN_TOTP_SECRET') ?: '';
            $totpOK=false;
            if(strlen($secret)>=16 && preg_match('/^\d{6}$/',$otp)) {
                for($offset=-1;$offset<=1;$offset++) $totpOK=$totpOK || hash_equals(ps_totp($secret,(int)floor(time()/30)+$offset),$otp);
            }
            if($envUser!=='' && hash_equals($envUser,$username) && password_verify($password,getenv('ADMIN_PASSWORD_HASH') ?: '') && $totpOK) {
                $authenticatedUser=$envUser;
                $_SESSION['admin_role']='superadmin';
            }
        }
        
        if(!$authenticatedUser) throw new HerMapError('The username, password or authenticator code was incorrect.',403);
        session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['admin']=$authenticatedUser;$_SESSION['admin_login']=$_SESSION['admin_seen']=time();
        header('Location: '.ps_url('admin'),true,303);exit;
    }
    
    if(!ps_admin_logged_in()) throw new HerMapError('Please sign in again.',403);
    
    // 2. Admin Logout
    if($action==='logout'){$_SESSION=[];session_regenerate_id(true);header('Location: '.ps_url('admin'),true,303);exit;}
    
    // 3. Incident Review Actions (Approve, Reject, Reopen)
    if(in_array($action,['approve','reject','reopen'],true)) {
        if(!preg_match('/^[a-f0-9]{32}$/',(string)($_POST['report_id'] ?? ''))) throw new HerMapError('Invalid review action.');
        $status=['approve'=>'approved','reject'=>'rejected','reopen'=>'pending'][$action];
        $reason=substr(trim((string)($_POST['reason'] ?? '')),0,60);
        $notes=substr(trim((string)($_POST['notes'] ?? '')),0,1000);
        $db=ps_db();$db->beginTransaction();
        try {
            $stmt=$db->prepare('SELECT status FROM hm_incidents WHERE id=? FOR UPDATE');$stmt->execute([$_POST['report_id']]);$previous=$stmt->fetchColumn();
            if(!$previous || $previous===$status) throw new HerMapError('The report has already changed. Refresh the queue.',409);
            $db->prepare('UPDATE hm_incidents SET status=?,approved_at=? WHERE id=?')->execute([$status,$status==='approved'?gmdate('Y-m-d H:i:s'):null,$_POST['report_id']]);
            $db->prepare('INSERT INTO hm_moderation_actions(report_id,moderator,action,reason,notes) VALUES(?,?,?,?,?)')->execute([$_POST['report_id'],$_SESSION['admin'],$action,$reason ?: null,$notes ?: null]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
        header('Location: '.ps_url('admin').'?tab=incidents&status='.urlencode($_GET['status'] ?? 'pending').'&reviewed=1',true,303);exit;
    }
    
    // 4. Contact Inquiries Status & Note Actions
    if($action==='inquiry_status') {
        $inquiryId=filter_var($_POST['inquiry_id'] ?? null,FILTER_VALIDATE_INT);
        $targetStatus=$_POST['target_status'] ?? '';
        if(!$inquiryId || !in_array($targetStatus,['new','in_progress','resolved','spam'],true)) throw new HerMapError('Invalid inquiry action.');
        $db=ps_db();
        $db->prepare('UPDATE hm_contact_messages SET status=?,resolved_at=? WHERE id=?')->execute([$targetStatus,$targetStatus==='resolved'?gmdate('Y-m-d H:i:s'):null,$inquiryId]);
        header('Location: '.ps_url('admin').'?tab=inquiries&inquiry_status='.urlencode($_GET['inquiry_status'] ?? 'all').'&updated=1',true,303);exit;
    }
    if($action==='inquiry_note') {
        $inquiryId=filter_var($_POST['inquiry_id'] ?? null,FILTER_VALIDATE_INT);
        $note=substr(trim((string)($_POST['admin_notes'] ?? '')),0,2000);
        if(!$inquiryId) throw new HerMapError('Invalid inquiry reference.');
        ps_db()->prepare('UPDATE hm_contact_messages SET admin_notes=? WHERE id=?')->execute([$note ?: null,$inquiryId]);
        header('Location: '.ps_url('admin').'?tab=inquiries&inquiry_status='.urlencode($_GET['inquiry_status'] ?? 'all').'&updated=1',true,303);exit;
    }
    
    // 5. System Settings Update
    if($action==='update_settings') {
        $intake=$_POST['report_intake_enabled'] ?? '0';
        $minReports=max(1,min(100,(int)($_POST['public_min_reports'] ?? 5)));
        $delayHours=max(1,min(720,(int)($_POST['publication_delay_hours'] ?? 48)));
        ps_set_setting('report_intake_enabled',$intake==='1'?'1':'0');
        ps_set_setting('public_min_reports',(string)$minReports);
        ps_set_setting('publication_delay_hours',(string)$delayHours);
        header('Location: '.ps_url('admin').'?tab=settings&saved=1',true,303);exit;
    }
    
    throw new HerMapError('Unknown action.');
 }
}catch(HerMapError $e){$error=$e->getMessage();}catch(Throwable $e){$error='The moderation service is temporarily unavailable.';error_log('HerMap admin failure: '.get_class($e));}

$loggedIn=(!PROD || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')) && ps_admin_logged_in();
$pageTitle='Moderation dashboard';
$counts=[];$daily=[];$reports=[];$inquiries=[];$total=0;$inquiryTotal=0;$stats=[];
$tab=$_GET['tab'] ?? 'incidents';
if(!in_array($tab,['incidents','inquiries','settings'],true)) $tab='incidents';

$page=max(1,min(10000,(int)($_GET['page'] ?? 1)));
$filter=$_GET['status'] ?? 'pending';
if(!in_array($filter,['pending','approved','rejected','all'],true))$filter='pending';
$search=trim((string)($_GET['q'] ?? ''));

$inquiryFilter=$_GET['inquiry_status'] ?? 'all';
if(!in_array($inquiryFilter,['new','in_progress','resolved','spam','all'],true))$inquiryFilter='all';
$inquirySearch=trim((string)($_GET['iq'] ?? ''));

if($loggedIn)try{
 $db=ps_db();$stats=ps_site_stats();
 $counts=$db->query('SELECT status,COUNT(*) n FROM hm_incidents GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
 $daily=$db->query('SELECT day,visits,received_reports FROM hm_site_daily ORDER BY day')->fetchAll();
 $inquiryCounts=$db->query('SELECT status,COUNT(*) n FROM hm_contact_messages GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
 
 // Incidents querying with parameterization
 $whereClause=[];$params=[];
 if($filter!=='all') { $whereClause[]='r.status=?'; $params[]=$filter; }
 if($search!=='') {
     $whereClause[]='(p.pincode LIKE ? OR p.office_name LIKE ? OR s.name LIKE ? OR c.name LIKE ? OR r.category LIKE ? OR r.id=?)';
     $like='%'.$search.'%';
     array_push($params,$like,$like,$like,$like,$like,$search);
 }
 $adminState = max(0, (int)($_GET['state_id'] ?? 0));
 $adminCity = max(0, (int)($_GET['city_id'] ?? 0));
 $adminPin = trim((string)($_GET['pincode'] ?? ''));
 if(strlen($adminPin)>10 || preg_match('/[^0-9]/',$adminPin)) $adminPin='';
 $adminCategory = trim((string)($_GET['category'] ?? ''));
 if(!isset(INCIDENT_CATEGORIES[$adminCategory])) $adminCategory='';

 if($adminState > 0) { $whereClause[] = 'r.state_id = ?'; $params[] = $adminState; }
 if($adminCity > 0) { $whereClause[] = 'r.city_id = ?'; $params[] = $adminCity; }
 if($adminPin !== '') { $whereClause[] = 'p.pincode LIKE ?'; $params[] = $adminPin . '%'; }
 if($adminCategory !== '') { $whereClause[] = 'r.category = ?'; $params[] = $adminCategory; }

 $adminStates = $db->query('SELECT id, name FROM hm_states ORDER BY name')->fetchAll();
 $adminCities = [];
 if($adminState > 0) {
     $cStmt = $db->prepare('SELECT id, name FROM hm_cities WHERE state_id=? ORDER BY name');
     $cStmt->execute([$adminState]);
     $adminCities = $cStmt->fetchAll();
 }
 $whereSql=$whereClause ? 'WHERE '.implode(' AND ',$whereClause) : '';
 $countStmt=$db->prepare("SELECT COUNT(*) FROM hm_incidents r JOIN hm_states s ON s.id=r.state_id LEFT JOIN hm_cities c ON c.id=r.city_id JOIN hm_post_offices p ON p.id=r.post_office_id $whereSql");
 $countStmt->execute($params);
 $total=(int)$countStmt->fetchColumn();
 
 $offset=($page-1)*25;
 $reportStmt=$db->prepare("SELECT r.*,s.name state_name,c.name city_name,p.office_name,p.pincode,
 (SELECT reason FROM hm_moderation_actions WHERE report_id=r.id ORDER BY id DESC LIMIT 1) last_reason,
 (SELECT notes FROM hm_moderation_actions WHERE report_id=r.id ORDER BY id DESC LIMIT 1) last_notes
 FROM hm_incidents r JOIN hm_states s ON s.id=r.state_id LEFT JOIN hm_cities c ON c.id=r.city_id JOIN hm_post_offices p ON p.id=r.post_office_id $whereSql ORDER BY r.submitted_at DESC LIMIT 25 OFFSET $offset");
 $reportStmt->execute($params);
 $reports=$reportStmt->fetchAll();
 
 // Inquiries querying with parameterization
 $inqWhere=[];$inqParams=[];
 if($inquiryFilter!=='all') { $inqWhere[]='status=?'; $inqParams[]=$inquiryFilter; }
 if($inquirySearch!=='') {
     $inqWhere[]='(ticket_id LIKE ? OR name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
     $inqLike='%'.$inquirySearch.'%';
     array_push($inqParams,$inqLike,$inqLike,$inqLike,$inqLike,$inqLike);
 }
 $inqWhereSql=$inqWhere ? 'WHERE '.implode(' AND ',$inqWhere) : '';
 $inqCountStmt=$db->prepare("SELECT COUNT(*) FROM hm_contact_messages $inqWhereSql");
 $inqCountStmt->execute($inqParams);
 $inquiryTotal=(int)$inqCountStmt->fetchColumn();
 
 $inqStmt=$db->prepare("SELECT * FROM hm_contact_messages $inqWhereSql ORDER BY created_at DESC LIMIT 25 OFFSET $offset");
 $inqStmt->execute($inqParams);
 $inquiries=$inqStmt->fetchAll();
 
}catch(Throwable $e){$error='The dashboard could not load. Please try again.';error_log('HerMap admin error: '.$e->getMessage());}

function dashboard_count(array $daily,string $from):int{return array_sum(array_map(fn($row)=>$row['day']>=$from?(int)$row['received_reports']:0,$daily));}
$today=new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata'));
require DIR_INC.'/content-start.php';
?>
<div class="admin-shell">
<span class="eyebrow">HerMap · private moderation</span><h1><?= $loggedIn ? 'A community, with care.' : 'Moderator sign in' ?></h1>
<?php if($error): ?><p class="notice-box" role="alert"><?= ps_escape($error) ?></p><?php endif; ?>
<?php if(!$loggedIn): ?>
<p class="lead">Authorised reviewers only. A password and an authenticator code are required.</p>
<form method="post" action="<?= ps_escape(ps_url('admin')) ?>" class="admin-login panel">
 <input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login">
 <label for="admin-username">Username</label><input id="admin-username" name="username" autocomplete="username" maxlength="100" required>
 <label for="admin-password">Password</label><input id="admin-password" type="password" name="password" autocomplete="current-password" maxlength="256" required>
 <label for="admin-otp">Authenticator code</label><input id="admin-otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required>
 <button class="button button-primary" type="submit">Sign in <?= ps_icon('arrow-right') ?></button>
</form>
<?php else: ?>
<div class="admin-toolbar"><p>Private moderation and community intake dashboard.</p><form method="post"><input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>"><button class="button button-secondary" name="action" value="logout">Sign out (<?= ps_escape($_SESSION['admin']) ?>)</button></form></div>
<?php if(isset($_GET['reviewed'])): ?><p class="notice-box" role="status">Review saved. Approved reports wait <?= PUBLICATION_DELAY_HOURS ?> hours and must meet the public grouping threshold.</p><?php endif; ?>
<?php if(isset($_GET['updated'])): ?><p class="notice-box" role="status">Inquiry status/note updated successfully.</p><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><p class="notice-box" role="status">System settings updated successfully.</p><?php endif; ?>

<section class="admin-metrics" aria-label="Dashboard totals">
<?php foreach(['Reports today'=>dashboard_count($daily,$today->format('Y-m-d')),'Reports this week'=>dashboard_count($daily,$today->modify('monday this week')->format('Y-m-d')),'Reports this month'=>dashboard_count($daily,$today->format('Y-m-01')),'Reports all time'=>$stats['total_reports'] ?? 0,'Pending review'=>$counts['pending'] ?? 0,'New inquiries'=>$inquiryCounts['new'] ?? 0,'Total page visits'=>$stats['total_visits'] ?? 0,'Approved reports'=>$counts['approved'] ?? 0] as $label=>$value): ?>
<article class="panel"><span><?= ps_escape($label) ?></span><strong data-admin-metric="<?= ps_escape($label) ?>"><?= number_format((int)$value) ?></strong></article>
<?php endforeach; ?>
</section>

<nav class="panel" style="display:flex; gap:16px; padding:12px 20px; margin-bottom:24px; font-weight:600; font-size:14px;" aria-label="Admin Navigation">
  <a href="<?= ps_escape(ps_url('admin?tab=incidents')) ?>" style="text-decoration:none; padding:6px 12px; border-radius:6px; background:<?= $tab==='incidents'?'var(--brand)':'transparent' ?>; color:<?= $tab==='incidents'?'#fff':'inherit' ?>;">
    <?= ps_icon('shield-halved') ?> Incident review (<?= (int)($counts['pending'] ?? 0) ?> pending)
  </a>
  <a href="<?= ps_escape(ps_url('admin?tab=inquiries')) ?>" style="text-decoration:none; padding:6px 12px; border-radius:6px; background:<?= $tab==='inquiries'?'var(--brand)':'transparent' ?>; color:<?= $tab==='inquiries'?'#fff':'inherit' ?>;">
    <?= ps_icon('envelope') ?> Contact inquiries (<?= (int)($inquiryCounts['new'] ?? 0) ?> new)
  </a>
  <a href="<?= ps_escape(ps_url('admin?tab=settings')) ?>" style="text-decoration:none; padding:6px 12px; border-radius:6px; background:<?= $tab==='settings'?'var(--brand)':'transparent' ?>; color:<?= $tab==='settings'?'#fff':'inherit' ?>;">
    <?= ps_icon('gear') ?> System controls
  </a>
</nav>

<?php if($tab==='incidents'): ?>
<section class="admin-chart panel" aria-labelledby="activity-title"><div class="chart-heading"><div><h2 id="activity-title">Community activity</h2><p>Daily totals in IST. Visits are page loads, not unique people.</p></div><div class="chart-filters"><label for="chart-metric" class="sr-only">Chart metric</label><select id="chart-metric"><option value="received_reports">Received reports</option><option value="visits">Page visits</option></select><label for="chart-period" class="sr-only">Chart period</label><select id="chart-period"><option value="7">Past 7 days</option><option value="30" selected>Past 30 days</option><option value="90">Past 90 days</option><option value="all">All time</option></select><label for="chart-group" class="sr-only">Group chart by</label><select id="chart-group"><option value="day">Daily</option><option value="week">Weekly</option><option value="month">Monthly</option></select></div></div><div id="admin-activity-chart" role="img" aria-label="Daily report activity"></div><div id="admin-chart-data" hidden data-api-url="<?= ps_escape(ps_url('api/admin-stats')) ?>" data-series="<?= ps_escape(json_encode($daily)) ?>" data-today="<?= ps_escape($today->format('Y-m-d')) ?>"></div><table class="sr-only"><caption>Daily totals</caption><thead><tr><th>Date (IST)</th><th>Reports received</th><th>Page visits</th></tr></thead><tbody><?php foreach($daily as $row): ?><tr><td><?= ps_escape($row['day']) ?></td><td><?= (int)$row['received_reports'] ?></td><td><?= (int)$row['visits'] ?></td></tr><?php endforeach; ?></tbody></table><p id="admin-update-state" class="form-hint" role="status">Counts and graphs refresh while this page is visible. Refresh the page to update the review queue.</p></section>

<div class="queue-heading">
  <h2>Incident review</h2>
  <nav aria-label="Review status">
    <?php
    $tabParams = $_GET;
    unset($tabParams['status'], $tabParams['page']);
    $baseTabQuery = http_build_query($tabParams);
    if ($baseTabQuery !== '') $baseTabQuery = '&' . $baseTabQuery;
    ?>
    <?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $key=>$label): ?>
      <a href="<?= ps_escape(ps_url('admin?tab=incidents&status='.$key.$baseTabQuery)) ?>" <?= $key===$filter?'aria-current="page"':'' ?>><?= $label ?> (<?= (int)($counts[$key] ?? ($key==='all'?array_sum($counts):0)) ?>)</a>
    <?php endforeach; ?>
  </nav>
</div>

<form method="get" class="panel admin-filter-bar" style="display:flex; flex-wrap:wrap; gap:10px; padding:14px; margin:16px 0; align-items:center;">
  <input type="hidden" name="tab" value="incidents">
  <input type="hidden" name="status" value="<?= ps_escape($filter) ?>">

  <div style="flex:1; min-width:140px;">
    <select name="state_id" id="admin-state-select" style="width:100%; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:13px;">
      <option value="">All States &amp; UTs</option>
      <?php foreach ($adminStates as $st): ?>
        <option value="<?= (int)$st['id'] ?>" <?= $adminState === (int)$st['id'] ? 'selected' : '' ?>><?= ps_escape($st['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div style="flex:1; min-width:140px;">
    <select name="city_id" id="admin-city-select" <?= $adminState === 0 ? 'disabled' : '' ?> style="width:100%; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:13px;">
      <option value="">All Cities</option>
      <?php foreach ($adminCities as $ct): ?>
        <option value="<?= (int)$ct['id'] ?>" <?= $adminCity === (int)$ct['id'] ? 'selected' : '' ?>><?= ps_escape($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div style="width:110px;">
    <input type="text" name="pincode" value="<?= ps_escape($adminPin) ?>" placeholder="PIN code" maxlength="6" inputmode="numeric" style="width:100%; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:13px;">
  </div>

  <div style="flex:1; min-width:140px;">
    <select name="category" style="width:100%; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:13px;">
      <option value="">All Categories</option>
      <?php foreach (INCIDENT_CATEGORIES as $catKey => $catLabel): ?>
        <option value="<?= ps_escape($catKey) ?>" <?= $adminCategory === $catKey ? 'selected' : '' ?>><?= ps_escape($catLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div style="flex:2; min-width:180px;">
    <input type="search" name="q" value="<?= ps_escape($search) ?>" placeholder="Search keyword, ID or office..." style="width:100%; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 10px; font-size:13px;">
  </div>

  <div style="display:flex; gap:8px;">
    <button class="button button-primary" type="submit" style="min-height:40px; padding:6px 16px;"><?= ps_icon('filter') ?> Filter</button>
    <?php if($search!=='' || $adminState>0 || $adminCity>0 || $adminPin!=='' || $adminCategory!==''): ?>
      <a href="<?= ps_escape(ps_url('admin?tab=incidents&status='.$filter)) ?>" class="button button-secondary" style="min-height:40px; padding:6px 14px;">Clear</a>
    <?php endif; ?>
  </div>
</form>

<p class="form-hint">Check duplicates, abusive targeting, personal identifiers and venue/branch ambiguity. Reject details identifying survivors or children. Optional online account handles are unverified private context. They and venue details stay private after approval; never contact or expose an account based on a report.</p>
<?php if(!$reports): ?><div class="panel queue-empty">No <?= $filter==='all'?'':ps_escape($filter) ?> reports matching your search.</div><?php endif; ?>
<?php foreach($reports as $report): $venue=ps_decrypt_venue($report['venue_encrypted']); $reportMsg=ps_decrypt_string($report['message_encrypted'] ?? null); ?>
<article class="review-card panel">
  <div class="review-heading">
    <h3><?= ps_escape(INCIDENT_CATEGORIES[$report['category']] ?? $report['category']) ?></h3>
    <span class="review-status"><?= ps_escape(ucfirst($report['status'])) ?></span>
  </div>
  <dl>
    <div><dt>Place type</dt><dd><?= ps_escape(PLACE_TYPES[$report['place_type']] ?? $report['place_type']) ?></dd></div>
    <div><dt>Location</dt><dd><?= ps_escape($report['office_name'].' · '.$report['pincode'].' · '.$report['state_name']) ?></dd></div>
    <div><dt>Incident time (IST)</dt><dd><?= ps_escape($report['incident_date'].' '.sprintf('%02d:%02d',$report['incident_hour'],$report['incident_minute'])) ?></dd></div>
    <div><dt>Received (IST)</dt><dd><?= ps_escape((new DateTimeImmutable($report['submitted_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, H:i')) ?></dd></div>
    <?php if($venue['name'] || $venue['website']): ?><div><dt>Private venue details</dt><dd><?= ps_escape($venue['name']) ?><br><?= ps_escape($venue['website']) ?></dd></div><?php endif; ?>
    <?php if(($venue['handle']??'') || ($venue['platform']??'')): ?><div><dt>Private online context · unverified</dt><dd><?= ps_escape($venue['platform']??'') ?><br><?= ps_escape($venue['handle']??'') ?></dd></div><?php endif; ?>
    <?php if(($venue['latitude']??'')!=='' && ($venue['longitude']??'')!==''): ?><div><dt>Optional shared coordinates · private</dt><dd><?= ps_escape($venue['latitude'].' / '.$venue['longitude']) ?></dd></div><?php endif; ?>
    <?php if($reportMsg !== ''): ?>
    <div style="grid-column: 1 / -1; background:#f4f0f8; padding:12px 14px; border-radius:8px; border-left:3px solid var(--brand); margin-top:4px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
        <dt style="font-weight:600; color:var(--brand); margin:0; font-size:12px;">Reporter message (encrypted)</dt>
        <span style="font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600; <?= !empty($report['share_message']) ? 'background:#e6f4ea; color:#137333;' : 'background:#f1f3f4; color:#5f6368;' ?>">
          <?= !empty($report['share_message']) ? '✓ Consented to show publicly if approved' : 'Private only (reporter opted out)' ?>
        </span>
      </div>
      <dd style="white-space:pre-wrap; word-break:break-word; margin:0; line-height:1.5; font-size:13px; color:var(--ink);"><?= ps_escape($reportMsg) ?></dd>
    </div>
    <?php endif; ?>
    <?php if(!empty($report['last_reason']) || !empty($report['last_notes'])): ?>
    <div style="grid-column: 1 / -1; background:#f9f8fa; padding:10px 14px; border-radius:6px; border-left:3px solid var(--brand);">
      <dt style="font-weight:600; color:var(--brand);">Previous Moderation Note</dt>
      <dd><?= ps_escape($report['last_reason'] ?? 'Action taken') ?><?= !empty($report['last_notes']) ? ' — ' . ps_escape($report['last_notes']) : '' ?></dd>
    </div>
    <?php endif; ?>
  </dl>
  <form method="post" class="review-actions" style="display:flex; flex-direction:column; gap:10px;">
    <input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>">
    <input type="hidden" name="report_id" value="<?= ps_escape($report['id']) ?>">
    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
      <?php if($report['status']!=='approved'): ?><button class="button button-primary" name="action" value="approve">Approve for grouped publication</button><?php endif; ?>
      <?php if($report['status']!=='rejected'): ?>
        <select name="reason" style="min-height:44px; border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:13px;">
          <option value="">Choose rejection reason</option>
          <option value="survivor_or_minor">Identifies survivor or minor</option>
          <option value="defamatory_names">Defamatory or personal names included</option>
          <option value="duplicate">Suspected duplicate / spam</option>
          <option value="test_or_irrelevant">Incoherent / test submission</option>
          <option value="out_of_scope">Out of geographic scope</option>
          <option value="other">Other reason</option>
        </select>
        <button class="button button-secondary" name="action" value="reject">Reject</button>
      <?php endif; ?>
      <?php if($report['status']!=='pending'): ?><button class="button button-secondary" name="action" value="reopen">Return to pending</button><?php endif; ?>
    </div>
    <?php if($report['status']!=='approved'): ?>
    <input type="text" name="notes" maxlength="250" placeholder="Optional internal moderator note..." style="width:100%; min-height:36px; border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:12px;">
    <?php endif; ?>
  </form>
</article>
<?php endforeach; ?>
<?php
$adminPageQuery = $_GET;
unset($adminPageQuery['page']);
$adminPrevUrl = ps_url('admin?' . http_build_query(array_merge($adminPageQuery, ['page' => $page - 1])));
$adminNextUrl = ps_url('admin?' . http_build_query(array_merge($adminPageQuery, ['page' => $page + 1])));
?>
<nav class="pagination" aria-label="Queue pages"><?php if($page>1): ?><a href="<?= ps_escape($adminPrevUrl) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> · <?= number_format($total) ?> reports</span><?php if($page*25<$total): ?><a href="<?= ps_escape($adminNextUrl) ?>">Next</a><?php endif; ?></nav>

<?php elseif($tab==='inquiries'): ?>
<div class="queue-heading">
  <h2>Contact Inquiries &amp; Grievances</h2>
  <nav aria-label="Inquiry status">
    <?php foreach(['new'=>'New','in_progress'=>'In progress','resolved'=>'Resolved','spam'=>'Spam','all'=>'All'] as $key=>$label): ?>
      <a href="<?= ps_escape(ps_url('admin?tab=inquiries&inquiry_status='.$key.($inquirySearch!==''?'&iq='.urlencode($inquirySearch):''))) ?>" <?= $key===$inquiryFilter?'aria-current="page"':'' ?>><?= $label ?> (<?= (int)($inquiryCounts[$key] ?? ($key==='all'?array_sum($inquiryCounts):0)) ?>)</a>
    <?php endforeach; ?>
  </nav>
</div>

<form method="get" class="panel" style="display:flex; gap:10px; padding:12px; margin:16px 0; align-items:center;">
  <input type="hidden" name="tab" value="inquiries">
  <input type="hidden" name="inquiry_status" value="<?= ps_escape($inquiryFilter) ?>">
  <input type="search" name="iq" value="<?= ps_escape($inquirySearch) ?>" placeholder="Search ticket ID, name, email or message..." style="flex:1; min-height:40px; border:1px solid var(--line); border-radius:8px; padding:6px 12px;">
  <button class="button button-primary" type="submit" style="min-height:40px; padding:6px 16px;"><?= ps_icon('magnifying-glass') ?> Filter</button>
  <?php if($inquirySearch!==''): ?><a href="<?= ps_escape(ps_url('admin?tab=inquiries&inquiry_status='.$inquiryFilter)) ?>" class="button button-secondary" style="min-height:40px; padding:6px 16px;">Clear</a><?php endif; ?>
</form>

<?php if(!$inquiries): ?><div class="panel queue-empty">No inquiries found matching this view.</div><?php endif; ?>
<?php foreach($inquiries as $inq): ?>
<article class="review-card panel" style="border-left:4px solid <?= $inq['status']==='new'?'#ff6b6b':($inq['status']==='in_progress'?'#80649e':'#3b82f6') ?>;">
  <div class="review-heading">
    <h3>[<?= ps_escape($inq['ticket_id']) ?>] <?= ps_escape($inq['subject']) ?></h3>
    <span class="review-status" style="font-weight:600; text-transform:uppercase;"><?= ps_escape($inq['status']) ?></span>
  </div>
  <dl>
    <div><dt>Sender</dt><dd><?= ps_escape($inq['name']) ?> &lt;<a href="mailto:<?= ps_escape($inq['email']) ?>?subject=Re:%20[<?= ps_escape($inq['ticket_id']) ?>]%20<?= urlencode($inq['subject']) ?>"><?= ps_escape($inq['email']) ?></a>&gt;</dd></div>
    <div><dt>Category</dt><dd><?= ps_escape(ucwords(str_replace('_',' ',$inq['category']))) ?></dd></div>
    <div><dt>Received</dt><dd><?= ps_escape((new DateTimeImmutable($inq['created_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, H:i')) ?> IST</dd></div>
    <?php if($inq['resolved_at']): ?><div><dt>Resolved at</dt><dd><?= ps_escape((new DateTimeImmutable($inq['resolved_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, H:i')) ?> IST</dd></div><?php endif; ?>
    <div style="grid-column: 1 / -1;"><dt>Message</dt><dd style="white-space:pre-wrap; background:#faf9fb; padding:12px; border-radius:6px;"><?= ps_escape($inq['message']) ?></dd></div>
    <?php if(!empty($inq['admin_notes'])): ?><div style="grid-column: 1 / -1;"><dt>Staff internal notes</dt><dd><?= ps_escape($inq['admin_notes']) ?></dd></div><?php endif; ?>
  </dl>
  <div style="display:flex; flex-direction:column; gap:12px; margin-top:14px; border-top:1px solid var(--line); padding-top:14px;">
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap;">
      <input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="inquiry_status">
      <input type="hidden" name="inquiry_id" value="<?= (int)$inq['id'] ?>">
      <?php if($inq['status']!=='in_progress'): ?><button class="button button-primary" name="target_status" value="in_progress">Mark in progress</button><?php endif; ?>
      <?php if($inq['status']!=='resolved'): ?><button class="button button-secondary" name="target_status" value="resolved">Mark resolved</button><?php endif; ?>
      <?php if($inq['status']!=='spam'): ?><button class="button button-secondary" name="target_status" value="spam">Mark spam</button><?php endif; ?>
      <?php if($inq['status']!=='new'): ?><button class="button button-secondary" name="target_status" value="new">Reopen (New)</button><?php endif; ?>
    </form>
    <form method="post" style="display:flex; gap:10px;">
      <input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="inquiry_note">
      <input type="hidden" name="inquiry_id" value="<?= (int)$inq['id'] ?>">
      <input type="text" name="admin_notes" value="<?= ps_escape($inq['admin_notes'] ?? '') ?>" placeholder="Add internal staff note..." style="flex:1; min-height:36px; border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:12px;">
      <button class="button button-secondary" type="submit" style="min-height:36px; font-size:12px; padding:6px 12px;">Save note</button>
    </form>
  </div>
</article>
<?php endforeach; ?>
<nav class="pagination" aria-label="Inquiry pages"><?php if($page>1): ?><a href="<?= ps_escape(ps_url('admin?tab=inquiries&inquiry_status='.$inquiryFilter.'&page='.($page-1).($inquirySearch!==''?'&iq='.urlencode($inquirySearch):''))) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> · <?= number_format($inquiryTotal) ?> inquiries</span><?php if($page*25<$inquiryTotal): ?><a href="<?= ps_escape(ps_url('admin?tab=inquiries&inquiry_status='.$inquiryFilter.'&page='.($page+1).($inquirySearch!==''?'&iq='.urlencode($inquirySearch):''))) ?>">Next</a><?php endif; ?></nav>

<?php elseif($tab==='settings'): ?>
<div class="panel" style="padding:24px;">
  <h2>System controls &amp; runtime parameters</h2>
  <p>Modify core operational flags safely from the admin dashboard without altering server configuration files.</p>
  <form method="post" style="display:flex; flex-direction:column; gap:20px; max-width:600px; margin-top:20px;">
    <input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="update_settings">
    
    <div>
      <label for="setting-intake" style="display:block; font-weight:600; margin-bottom:6px;">Report Intake Status</label>
      <select id="setting-intake" name="report_intake_enabled" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:8px; padding:8px 12px;">
        <option value="1" <?= ps_setting('report_intake_enabled','1')==='1'?'selected':'' ?>>Enabled (Submissions Accepted)</option>
        <option value="0" <?= ps_setting('report_intake_enabled','1')==='0'?'selected':'' ?>>Disabled (Intake Paused / Maintenance)</option>
      </select>
      <p class="form-hint">When disabled, public submission forms will show a maintenance notice and will reject incoming reports.</p>
    </div>

    <div>
      <label for="setting-min-reports" style="display:block; font-weight:600; margin-bottom:6px;">Minimum Reports for Public Grid Cell</label>
      <input id="setting-min-reports" type="number" name="public_min_reports" min="1" max="100" value="<?= ps_escape(ps_setting('public_min_reports','5')) ?>" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:8px; padding:8px 12px;">
      <p class="form-hint">Minimum number of approved reports required before a 0.25° map area displays counts publicly (default: 5).</p>
    </div>

    <div>
      <label for="setting-delay" style="display:block; font-weight:600; margin-bottom:6px;">Publication Delay (Hours)</label>
      <input id="setting-delay" type="number" name="publication_delay_hours" min="1" max="720" value="<?= ps_escape(ps_setting('publication_delay_hours','48')) ?>" style="width:100%; min-height:44px; border:1px solid var(--line); border-radius:8px; padding:8px 12px;">
      <p class="form-hint">Hours to wait after moderator approval before a report contributes to public map aggregates (default: 48 hours).</p>
    </div>

    <button class="button button-primary" type="submit" style="align-self:flex-start;">Save system settings <?= ps_icon('arrow-right') ?></button>
  </form>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
<?php require DIR_INC.'/content-end.php'; ?>
