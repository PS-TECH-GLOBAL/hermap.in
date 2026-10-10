<?php
declare(strict_types=1);
const INCIDENT_CATEGORIES = [
 'stalking'=>'Stalking or following','verbal'=>'Verbal harassment','touching'=>'Unwanted touching',
 'threats'=>'Intimidation or threats','transport'=>'Transport incident','public_transport'=>'Public transport incident',
 'assault'=>'Assault or attempted assault','workplace'=>'Workplace or office harassment',
 'online'=>'Online harassment','other'=>'Other or unsure',
];
const PLACE_TYPES=['office'=>'Office or workplace','cafe'=>'Café or restaurant','hotel'=>'Hotel or accommodation','landmark'=>'Public landmark or attraction','public'=>'Street or public place','transport'=>'Transport or station','online'=>'Online / digital space','other'=>'Other or unsure'];
final class HerMapError extends RuntimeException {
    public function __construct(string $message, public int $status=400){parent::__construct($message);}
}
function ps_session(): void {
    if(session_status()===PHP_SESSION_ACTIVE) return;
    $path=ROOT_PATH.'/tmp/sessions'; if(!is_dir($path)) mkdir($path,0700,true);
    session_save_path($path); session_name('HERMAP_SESSION');
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['lifetime'=>0,'path'=>parse_url(SITE_URL,PHP_URL_PATH) ?: '/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function ps_secret(): string {
    $secret=getenv('APP_SECRET') ?: ''; if(strlen($secret)<32) throw new HerMapError('The service is not configured yet.',503); return $secret;
}
function ps_rate_limit(string $purpose,int $maximum,int $seconds=3600):void {
    $slot=(int)floor(time()/$seconds);
    $key=hash_hmac('sha256', $purpose.'|'.$slot.'|'.($_SERVER['REMOTE_ADDR'] ?? 'cli'),ps_secret());
    $db=ps_db();
    $stmt=$db->prepare('INSERT INTO hm_rate_limits(bucket,attempts,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1');
    $stmt->execute([$key,gmdate('Y-m-d H:i:s',($slot+1)*$seconds)]);
    $stmt=$db->prepare('SELECT attempts FROM hm_rate_limits WHERE bucket=?');$stmt->execute([$key]);
    if((int)$stmt->fetchColumn()>$maximum) throw new HerMapError('Too many attempts. Please try again later.',429);
}
function ps_require_csrf(?string $token):void {
    ps_session();
    if(!$token || !hash_equals($_SESSION['csrf'],$token)) throw new HerMapError('Your session expired. Refresh the form and try again.',403);
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
    $siteOrigin=parse_url(SITE_URL,PHP_URL_SCHEME).'://'.parse_url(SITE_URL,PHP_URL_HOST).(parse_url(SITE_URL,PHP_URL_PORT) ? ':'.parse_url(SITE_URL,PHP_URL_PORT) : '');
    if($origin!=='' && $origin!==$siteOrigin) throw new HerMapError('This request is not allowed.',403);
}
function ps_challenge():array {
    ps_session();ps_rate_limit('challenge',100);
    $a=random_int(12,40);$b=random_int(2,11);$add=(bool)random_int(0,1);
    $id=bin2hex(random_bytes(16));
    $_SESSION['challenge']=['id'=>$id,'answer'=>(string)($add?$a+$b:$a-$b),'issued'=>time()];
    return ['csrf'=>$_SESSION['csrf'],'id'=>$id,'question'=>"$a ".($add?'+':'−')." $b =",'expires_in'=>600];
}
function ps_validate_challenge(array $body):void {
    $challenge=$_SESSION['challenge'] ?? null;
    unset($_SESSION['challenge']); // Every answer is single use, even if wrong.
    if(!$challenge || !hash_equals($challenge['id'],(string)($body['challenge_id'] ?? '')) || time()-$challenge['issued']>600
        || time()-$challenge['issued']<2 || !hash_equals($challenge['answer'],trim((string)($body['challenge_answer'] ?? '')))) {
        throw new HerMapError('The check was incorrect or expired. Please solve the new question.',422);
    }
    if(trim((string)($body['website_check'] ?? ''))!=='') throw new HerMapError('The request could not be accepted.',422);
}
function ps_encrypt_venue(array $venue):?string {
    if(!array_filter($venue,fn($value)=>$value!=='')) return null;
    $key=hex2bin(getenv('APP_ENCRYPTION_KEY') ?: '');
    if(!$key || strlen($key)!==32) throw new HerMapError('Secure storage is not configured.',503);
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt(json_encode($venue,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'HerMap venue v1');
    if($cipher===false) throw new HerMapError('Secure storage is unavailable.',503);
    return base64_encode($iv.$tag.$cipher);
}
function ps_decrypt_venue(?string $cipher):array {
    if(!$cipher) return ['name'=>'','website'=>''];
    $raw=base64_decode($cipher,true); $key=hex2bin(getenv('APP_ENCRYPTION_KEY') ?: '');
    $json=$raw && $key ? openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'HerMap venue v1') : false;
    return $json ? json_decode($json,true,512,JSON_THROW_ON_ERROR) : ['name'=>'Unable to decrypt','website'=>''];
}
function ps_ring_contains(array $ring,float $lng,float $lat):bool {
    $inside=false;$j=count($ring)-1;
    foreach($ring as $i=>$p){$q=$ring[$j]; if(($p[1]>$lat)!==($q[1]>$lat) && $lng<($q[0]-$p[0])*($lat-$p[1])/($q[1]-$p[1])+$p[0]) $inside=!$inside; $j=$i;}
    return $inside;
}
function ps_in_india(float $lng,float $lat):bool {
    static $polygons=null;
    $polygons ??= json_decode(file_get_contents(ROOT_PATH.'/assets/data/india-outline.geojson'),true,512,JSON_THROW_ON_ERROR)['features'][0]['geometry']['coordinates'];
    foreach($polygons as $rings) {if(ps_ring_contains($rings[0],$lng,$lat)) {foreach(array_slice($rings,1) as $hole) if(ps_ring_contains($hole,$lng,$lat)) return false; return true;}}
    return false;
}
function ps_save_incident(array $body):array {
    if(!INTAKE_ENABLED) throw new HerMapError('Report intake is currently closed.',503);
    if(PROD && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) throw new HerMapError('A secure HTTPS connection is required.',503);
    ps_require_csrf((string)($body['csrf'] ?? ''));ps_rate_limit('report',8);ps_validate_challenge($body);
    if(($body['consent'] ?? false)!==true || ($body['adult'] ?? false)!==true) throw new HerMapError('Please confirm the privacy notice and that you are 18 or older.',422);
    $category=(string)($body['category'] ?? '');$place=(string)($body['place_type'] ?? '');
    if(!isset(INCIDENT_CATEGORIES[$category],PLACE_TYPES[$place])) throw new HerMapError('Choose a valid experience and place type.',422);
    $state=filter_var($body['state_id'] ?? null,FILTER_VALIDATE_INT);$office=filter_var($body['post_office_id'] ?? null,FILTER_VALIDATE_INT);
    $stmt=ps_db()->prepare('SELECT * FROM hm_post_offices WHERE id=? AND state_id=?');$stmt->execute([$office,$state]);$location=$stmt->fetch();
    if(!$location) throw new HerMapError('Choose a post office/PIN that belongs to the selected state.',422);
    if($location['latitude']===null || !ps_in_india((float)$location['longitude'],(float)$location['latitude'])) throw new HerMapError('This PIN has no reliable map position. Please choose another nearby post office.',422);
    $date=(string)($body['date'] ?? '');$time=(string)($body['time'] ?? '');
    $when=DateTimeImmutable::createFromFormat('!Y-m-d H:i',"$date $time",new DateTimeZone('Asia/Kolkata'));
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata'));
    if(!$when || $when->format('Y-m-d H:i')!=="$date $time" || $when>$now || $when<$now->sub(new DateInterval('P365D'))) throw new HerMapError('Choose an incident date and time within the past year, not in the future.',422);
    $name=trim((string)($body['venue_name'] ?? ''));$website=trim((string)($body['venue_website'] ?? ''));
    if(mb_strlen($name)>120 || strlen($website)>300 || preg_match('/[\x00-\x1f<>]/u',$name)) throw new HerMapError('Please check the venue details.',422);
    if($website!=='' && (!filter_var($website,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($website,PHP_URL_SCHEME) ?: ''),['http','https'],true) || parse_url($website,PHP_URL_USER) || parse_url($website,PHP_URL_PASS))) throw new HerMapError('Use a public http or https website without login information.',422);
    if(!in_array($place,['office','cafe','hotel','landmark'],true)) {$name='';$website='';}
    $online=ps_online_details($body,$category,$place);
    $point=ps_optional_location($body,(float)$location['latitude'],(float)$location['longitude']);
    $cellX=(int)floor((float)($point['longitude']!==''?$point['longitude']:$location['longitude'])*4);$cellY=(int)floor((float)($point['latitude']!==''?$point['latitude']:$location['latitude'])*4);
    $duplicateParts=[$category,$place,$location['pincode'],$date,$time,mb_strtolower($name)];
    if($online['handle']!=='' || $online['platform']!=='')array_push($duplicateParts,mb_strtolower($online['handle']),mb_strtolower($online['platform']));
    $fingerprint=hash_hmac('sha256',implode('|',$duplicateParts),ps_secret());
    $receipt=bin2hex(random_bytes(24));$id=bin2hex(random_bytes(16));$db=ps_db();$db->beginTransaction();
    try {
        $stmt=$db->prepare('INSERT INTO hm_incidents(id,category,place_type,state_id,city_id,post_office_id,cell_x,cell_y,incident_date,incident_hour,incident_minute,venue_encrypted,receipt_hash,duplicate_hash,delete_after) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$id,$category,$place,$state,$location['city_id'],$office,$cellX,$cellY,$date,(int)$when->format('G'),(int)$when->format('i'),ps_encrypt_venue(['name'=>$name,'website'=>$website]+$online+$point),hash('sha256',$receipt),$fingerprint,gmdate('Y-m-d H:i:s',time()+400*86400)]);
        $db->prepare('INSERT INTO hm_site_daily(day,received_reports) VALUES(?,1) ON DUPLICATE KEY UPDATE received_reports=received_reports+1')->execute([$now->format('Y-m-d')]);
        $db->commit();
    }catch(PDOException $e){$db->rollBack();if($e->getCode()==='23000') throw new HerMapError('A matching report already exists. Please avoid submitting the same experience twice.',409);throw $e;}catch(Throwable $e){$db->rollBack();throw $e;}
    return ['message'=>'Your report was saved for private review. It is not public. Keep this receipt to check its status or request deletion.','receipt'=>$receipt];
}
function ps_online_details(array $body,string $category,string $place):array {
    $details=['handle'=>'','platform'=>''];
    if($category!=='online' && $place!=='online')return $details;
    $details=['handle'=>trim((string)($body['online_handle']??'')),'platform'=>trim((string)($body['online_platform']??''))];
    if(mb_strlen($details['handle'])>100 || ($details['handle']!=='' && !preg_match('/^@?[\p{L}\p{N}_.-]{1,99}$/u',$details['handle']))) throw new HerMapError('Use only an account username or handle, without links or contact details. You can leave it blank.',422);
    if(mb_strlen($details['platform'])>60 || preg_match('/[\x00-\x1f<>]/u',$details['platform'])) throw new HerMapError('Please check the platform name, or leave it blank.',422);
    return $details;
}
function ps_optional_location(array $body,float $pinLat,float $pinLng):array {
    $empty=['latitude'=>'','longitude'=>''];
    if(($body['share_location']??false)!==true)return $empty;
    $lat=$body['latitude']??null;$lng=$body['longitude']??null;
    if(!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng))throw new HerMapError('Choose both coordinates, or uncheck private location sharing.',422);
    $lat=round((float)$lat,6);$lng=round((float)$lng,6);
    if(!ps_in_india($lng,$lat))throw new HerMapError('Choose a point inside the India outline, or leave private location sharing off.',422);
    $a=sin(deg2rad($lat-$pinLat)/2)**2+cos(deg2rad($pinLat))*cos(deg2rad($lat))*sin(deg2rad($lng-$pinLng)/2)**2;
    $distance=6371*2*atan2(sqrt(min(1,$a)),sqrt(max(0,1-$a)));
    if($distance>75)throw new HerMapError('Choose a nearby PIN for this point (within 75 km), or change the selected point.',422);
    return ['latitude'=>(string)$lat,'longitude'=>(string)$lng];
}
function ps_count_band(int $count):string {
    if($count<PUBLIC_MIN_REPORTS) return 'Insufficient data';
    $base=(int)(floor($count/5)*5);return $base.'–'.($base+4);
}
function ps_summary(?int $x,?int $y,int $days):array {
    $where="status='approved' AND approved_at<=? AND incident_date>=?";
    $params=[gmdate('Y-m-d H:i:s',time()-PUBLICATION_DELAY_HOURS*3600),(new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata')))->sub(new DateInterval('P'.($days-1).'D'))->format('Y-m-d')];
    if($x!==null && $y!==null){$where.=' AND cell_x=? AND cell_y=?';$params[]=$x;$params[]=$y;}
    $db=ps_db();$stmt=$db->prepare("SELECT COUNT(*) FROM hm_incidents WHERE $where");$stmt->execute($params);$count=(int)$stmt->fetchColumn();
    $result=['available'=>$count>=PUBLIC_MIN_REPORTS,'total'=>ps_count_band($count),'window_days'=>$days,'category'=>null,'hour'=>null,'day'=>null,'categories'=>[],'cell'=>$x===null?null:['x'=>$x,'y'=>$y,'bounds'=>[$x/4,$y/4,($x+1)/4,($y+1)/4]],'note'=>'Approved community reports, not independently verified. Counts are grouped in bands of five; small groups are withheld.'];
    if(!$result['available']) return $result;
    $stmt=$db->prepare("SELECT category,COUNT(*) n FROM hm_incidents WHERE $where GROUP BY category ORDER BY n DESC,category");$stmt->execute($params);$categories=$stmt->fetchAll();
    foreach($categories as $row) if((int)$row['n']>=PUBLIC_MIN_REPORTS) $result['categories'][]=['label'=>INCIDENT_CATEGORIES[$row['category']],'count'=>ps_count_band((int)$row['n'])];
    if($categories && (int)$categories[0]['n']>=PUBLIC_MIN_REPORTS) $result['category']=INCIDENT_CATEGORIES[$categories[0]['category']];
    $stmt=$db->prepare("SELECT incident_hour,COUNT(*) n FROM hm_incidents WHERE $where GROUP BY incident_hour ORDER BY n DESC,incident_hour LIMIT 1");$stmt->execute($params);$hour=$stmt->fetch();
    if($hour && (int)$hour['n']>=PUBLIC_MIN_REPORTS){$h=(int)$hour['incident_hour'];$result['hour']=date('g a',mktime($h,0)).'–'.date('g a',mktime(($h+1)%24,0)).' IST';}
    $stmt=$db->prepare("SELECT WEEKDAY(incident_date) weekday,COUNT(*) n FROM hm_incidents WHERE $where GROUP BY weekday ORDER BY n DESC,weekday LIMIT 1");$stmt->execute($params);$day=$stmt->fetch();
    if($day && (int)$day['n']>=PUBLIC_MIN_REPORTS) $result['day']=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][(int)$day['weekday']];
    return $result;
}
function ps_site_stats():array {
    ps_cleanup();
    $db=ps_db();$totals=$db->query('SELECT COALESCE(SUM(visits),0) visits,COALESCE(SUM(received_reports),0) reports,MIN(day) launched FROM hm_site_daily')->fetch();
    return ['active_visits'=>(int)$db->query('SELECT COUNT(*) FROM hm_live_visits WHERE expires_at>UTC_TIMESTAMP()')->fetchColumn(),'total_visits'=>(int)$totals['visits'],'total_reports'=>(int)$totals['reports'],'since'=>$totals['launched'],'label'=>'Active visits in the last 2 minutes. Visits count public-page loads, not unique people. Report totals include received reports before moderation.'];
}
function ps_cleanup():void {
    // Bounded batches also run on traffic; the CLI job can clear a longer backlog.
    $db=ps_db();
    $db->exec('DELETE FROM hm_live_visits WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) LIMIT 500');
    $db->exec('DELETE FROM hm_rate_limits WHERE expires_at<UTC_TIMESTAMP() LIMIT 500');
    $db->exec('DELETE FROM hm_incidents WHERE delete_after<UTC_TIMESTAMP() LIMIT 100');
    $db->exec('DELETE FROM hm_moderation_actions WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 400 DAY) LIMIT 100');
}
function ps_start_visit():string {
    $token=bin2hex(random_bytes(24));$db=ps_db();$db->beginTransaction();
    try{$db->prepare('INSERT INTO hm_live_visits(token_hash,expires_at) VALUES(?,?)')->execute([hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+120)]);
    $db->prepare('INSERT INTO hm_site_daily(day,visits) VALUES(?,1) ON DUPLICATE KEY UPDATE visits=visits+1')->execute([(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d')]);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
    return $token;
}
function ps_totp(string $secret,int $step):string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split(strtoupper($secret)) as $c){$pos=strpos($alphabet,$c);if($pos===false) return ''; $bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);}
    $key='';for($i=0;$i+8<=strlen($bits);$i+=8)$key.=chr(bindec(substr($bits,$i,8)));
    $hash=hash_hmac('sha1',pack('N2',0,$step),$key,true);$offset=ord($hash[19])&15;$num=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
    return str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT);
}
function ps_admin_logged_in(bool $touch=true):bool {
    if(PROD && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off'))return false;
    ps_session();$ok=isset($_SESSION['admin'],$_SESSION['admin_login'],$_SESSION['admin_seen']) && time()-$_SESSION['admin_seen']<1800 && time()-$_SESSION['admin_login']<28800;
    if($ok && $touch) $_SESSION['admin_seen']=time();elseif(!$ok) unset($_SESSION['admin']);return $ok;
}
function ps_admin_stats():array {
    $db=ps_db();$stats=ps_site_stats();$daily=$db->query('SELECT day,visits,received_reports FROM hm_site_daily ORDER BY day')->fetchAll();
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata'));
    $sum=fn(string $from)=>array_sum(array_map(fn($row)=>$row['day']>=$from?(int)$row['received_reports']:0,$daily));
    $counts=$db->query('SELECT status,COUNT(*) n FROM hm_incidents GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['metrics'=>['Reports today'=>$sum($today->format('Y-m-d')),'Reports this week'=>$sum($today->modify('monday this week')->format('Y-m-d')),'Reports this month'=>$sum($today->format('Y-m-01')),'Reports all time'=>$stats['total_reports'],'Pending review'=>(int)($counts['pending']??0),'Total page visits'=>$stats['total_visits'],'Active visits'=>$stats['active_visits'],'Approved reports'=>(int)($counts['approved']??0)],'daily'=>$daily,'today'=>$today->format('Y-m-d')];
}
