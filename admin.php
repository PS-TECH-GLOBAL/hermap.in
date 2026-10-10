<?php
require_once __DIR__.'/inc/config.php';
$route='admin';
ps_session(); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$error='';$notice='';
try {
 if(PROD && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) throw new HerMapError('Admin access requires HTTPS.',503);
 if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)throw new HerMapError('The request is too large.',413);
    foreach($_POST as $value)if(!is_string($value))throw new HerMapError('Invalid form field.');
    ps_require_csrf($_POST['csrf'] ?? null);$action=$_POST['action'] ?? '';
    if($action==='login') {
        ps_rate_limit('admin-login',10);
        $username=substr((string)($_POST['username'] ?? ''),0,100);$password=substr((string)($_POST['password'] ?? ''),0,256);$otp=trim((string)($_POST['otp'] ?? ''));
        $totpOK=false;$secret=getenv('ADMIN_TOTP_SECRET') ?: '';
        if(strlen($secret)>=16 && preg_match('/^\d{6}$/',$otp)) for($offset=-1;$offset<=1;$offset++) $totpOK=$totpOK || hash_equals(ps_totp($secret,(int)floor(time()/30)+$offset),$otp);
        if(!hash_equals(getenv('ADMIN_USERNAME') ?: '',$username) || !password_verify($password,getenv('ADMIN_PASSWORD_HASH') ?: '') || !$totpOK) throw new HerMapError('The username, password or authenticator code was incorrect.',403);
        session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['admin']=$username;$_SESSION['admin_login']=$_SESSION['admin_seen']=time();
        header('Location: '.ps_url('admin'),true,303);exit;
    }
    if(!ps_admin_logged_in()) throw new HerMapError('Please sign in again.',403);
    if($action==='logout'){$_SESSION=[];session_regenerate_id(true);header('Location: '.ps_url('admin'),true,303);exit;}
    if(!in_array($action,['approve','reject','reopen'],true) || !preg_match('/^[a-f0-9]{32}$/',(string)($_POST['report_id'] ?? ''))) throw new HerMapError('Invalid review action.');
    $status=['approve'=>'approved','reject'=>'rejected','reopen'=>'pending'][$action];$db=ps_db();$db->beginTransaction();
    try {
        $stmt=$db->prepare('SELECT status FROM hm_incidents WHERE id=? FOR UPDATE');$stmt->execute([$_POST['report_id']]);$previous=$stmt->fetchColumn();
        if(!$previous || $previous===$status) throw new HerMapError('The report has already changed. Refresh the queue.',409);
        $db->prepare('UPDATE hm_incidents SET status=?,approved_at=? WHERE id=?')->execute([$status,$status==='approved'?gmdate('Y-m-d H:i:s'):null,$_POST['report_id']]);
        $db->prepare('INSERT INTO hm_moderation_actions(report_id,moderator,action) VALUES(?,?,?)')->execute([$_POST['report_id'],$_SESSION['admin'],$action]);$db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
    header('Location: '.ps_url('admin').'?status='.urlencode($_GET['status'] ?? 'pending').'&reviewed=1',true,303);exit;
 }
}catch(HerMapError $e){$error=$e->getMessage();}catch(Throwable $e){$error='The moderation service is temporarily unavailable.';error_log('HerMap admin failure: '.get_class($e));}
$loggedIn=(!PROD || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')) && ps_admin_logged_in();$pageTitle='Moderation dashboard';
$counts=[];$daily=[];$reports=[];$total=0;$stats=[];$page=max(1,min(10000,(int)($_GET['page'] ?? 1)));$filter=$_GET['status'] ?? 'pending';
if(!in_array($filter,['pending','approved','rejected','all'],true))$filter='pending';
if($loggedIn)try{
 $db=ps_db();$stats=ps_site_stats();
 $counts=$db->query('SELECT status,COUNT(*) n FROM hm_incidents GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
 $daily=$db->query('SELECT day,visits,received_reports FROM hm_site_daily ORDER BY day')->fetchAll();
 $where=$filter==='all'?'1=1':'r.status='.$db->quote($filter);
 $total=(int)$db->query("SELECT COUNT(*) FROM hm_incidents r WHERE $where")->fetchColumn();
 $reports=$db->query("SELECT r.*,s.name state_name,c.name city_name,p.office_name,p.pincode FROM hm_incidents r JOIN hm_states s ON s.id=r.state_id LEFT JOIN hm_cities c ON c.id=r.city_id JOIN hm_post_offices p ON p.id=r.post_office_id WHERE $where ORDER BY r.submitted_at DESC LIMIT 25 OFFSET ".(($page-1)*25))->fetchAll();
}catch(Throwable $e){$error='The dashboard could not load. Please try again.';}
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
<div class="admin-toolbar"><p>Private review queue. Approval does not verify an allegation.</p><form method="post"><input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>"><button class="button button-secondary" name="action" value="logout">Sign out</button></form></div>
<?php if(isset($_GET['reviewed'])): ?><p class="notice-box" role="status">Review saved. Approved reports wait <?= PUBLICATION_DELAY_HOURS ?> hours and must meet the public grouping threshold.</p><?php endif; ?>
<section class="admin-metrics" aria-label="Dashboard totals">
<?php foreach(['Reports today'=>dashboard_count($daily,$today->format('Y-m-d')),'Reports this week'=>dashboard_count($daily,$today->modify('monday this week')->format('Y-m-d')),'Reports this month'=>dashboard_count($daily,$today->format('Y-m-01')),'Reports all time'=>$stats['total_reports'] ?? 0,'Pending review'=>$counts['pending'] ?? 0,'Total page visits'=>$stats['total_visits'] ?? 0,'Active visits'=>$stats['active_visits'] ?? 0,'Approved reports'=>$counts['approved'] ?? 0] as $label=>$value): ?><article class="panel"><span><?= ps_escape($label) ?></span><strong data-admin-metric="<?= ps_escape($label) ?>"><?= number_format((int)$value) ?></strong></article><?php endforeach; ?>
</section>
<section class="admin-chart panel" aria-labelledby="activity-title"><div class="chart-heading"><div><h2 id="activity-title">Community activity</h2><p>Daily totals in IST. Visits are page loads, not unique people.</p></div><div class="chart-filters"><label for="chart-metric" class="sr-only">Chart metric</label><select id="chart-metric"><option value="received_reports">Received reports</option><option value="visits">Page visits</option></select><label for="chart-period" class="sr-only">Chart period</label><select id="chart-period"><option value="7">Past 7 days</option><option value="30" selected>Past 30 days</option><option value="90">Past 90 days</option><option value="all">All time</option></select><label for="chart-group" class="sr-only">Group chart by</label><select id="chart-group"><option value="day">Daily</option><option value="week">Weekly</option><option value="month">Monthly</option></select></div></div><div id="admin-activity-chart" role="img" aria-label="Daily report activity"></div><div id="admin-chart-data" hidden data-api-url="<?= ps_escape(ps_url('api/admin-stats')) ?>" data-series="<?= ps_escape(json_encode($daily)) ?>" data-today="<?= ps_escape($today->format('Y-m-d')) ?>"></div><table class="sr-only"><caption>Daily totals</caption><thead><tr><th>Date (IST)</th><th>Reports received</th><th>Page visits</th></tr></thead><tbody><?php foreach($daily as $row): ?><tr><td><?= ps_escape($row['day']) ?></td><td><?= (int)$row['received_reports'] ?></td><td><?= (int)$row['visits'] ?></td></tr><?php endforeach; ?></tbody></table><p id="admin-update-state" class="form-hint" role="status">Counts and graphs refresh while this page is visible. Refresh the page to update the review queue.</p></section>
<div class="queue-heading"><h2>Incident review</h2><nav aria-label="Review status"><?php foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $key=>$label): ?><a href="<?= ps_escape(ps_url('admin').'?status='.$key) ?>" <?= $key===$filter?'aria-current="page"':'' ?>><?= $label ?></a><?php endforeach; ?></nav></div>
<p class="form-hint">Check duplicates, abusive targeting, personal identifiers and venue/branch ambiguity. Reject details identifying survivors or children. Optional online account handles are unverified private context. They and venue details stay private after approval; never contact or expose an account based on a report.</p>
<?php if(!$reports): ?><div class="panel queue-empty">No <?= $filter==='all'?'':ps_escape($filter) ?> reports to show.</div><?php endif; ?>
<?php foreach($reports as $report): $venue=ps_decrypt_venue($report['venue_encrypted']); ?>
<article class="review-card panel"><div class="review-heading"><h3><?= ps_escape(INCIDENT_CATEGORIES[$report['category']] ?? $report['category']) ?></h3><span class="review-status"><?= ps_escape(ucfirst($report['status'])) ?></span></div><dl><div><dt>Place type</dt><dd><?= ps_escape(PLACE_TYPES[$report['place_type']] ?? $report['place_type']) ?></dd></div><div><dt>Location</dt><dd><?= ps_escape($report['office_name'].' · '.$report['pincode'].' · '.$report['state_name']) ?></dd></div><div><dt>Incident time (IST)</dt><dd><?= ps_escape($report['incident_date'].' '.sprintf('%02d:%02d',$report['incident_hour'],$report['incident_minute'])) ?></dd></div><div><dt>Received (IST)</dt><dd><?= ps_escape((new DateTimeImmutable($report['submitted_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, H:i')) ?></dd></div><?php if($venue['name'] || $venue['website']): ?><div><dt>Private venue details</dt><dd><?= ps_escape($venue['name']) ?><br><?= ps_escape($venue['website']) ?></dd></div><?php endif; ?><?php if(($venue['handle']??'') || ($venue['platform']??'')): ?><div><dt>Private online context · unverified</dt><dd><?= ps_escape($venue['platform']??'') ?><br><?= ps_escape($venue['handle']??'') ?></dd></div><?php endif; ?><?php if(($venue['latitude']??'')!=='' && ($venue['longitude']??'')!==''): ?><div><dt>Optional shared coordinates · private</dt><dd><?= ps_escape($venue['latitude'].' / '.$venue['longitude']) ?></dd></div><?php endif; ?></dl>
<form method="post" class="review-actions"><input type="hidden" name="csrf" value="<?= ps_escape($_SESSION['csrf']) ?>"><input type="hidden" name="report_id" value="<?= ps_escape($report['id']) ?>"><?php if($report['status']!=='approved'): ?><button class="button button-primary" name="action" value="approve">Approve for grouped publication</button><?php endif; ?><?php if($report['status']!=='rejected'): ?><button class="button button-secondary" name="action" value="reject">Reject</button><?php endif; ?><?php if($report['status']!=='pending'): ?><button class="button button-secondary" name="action" value="reopen">Return to pending</button><?php endif; ?></form></article>
<?php endforeach; ?>
<nav class="pagination" aria-label="Queue pages"><?php if($page>1): ?><a href="<?= ps_escape(ps_url('admin').'?status='.$filter.'&page='.($page-1)) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> · <?= number_format($total) ?> reports</span><?php if($page*25<$total): ?><a href="<?= ps_escape(ps_url('admin').'?status='.$filter.'&page='.($page+1)) ?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
</div>
<?php require DIR_INC.'/content-end.php'; ?>
