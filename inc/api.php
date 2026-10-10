<?php
declare(strict_types=1);
function ps_json(array $data,int $status=200):never {http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try {
 foreach($_GET as $value) if(!is_scalar($value) || strlen((string)$value)>200) throw new HerMapError('Invalid query.');
 $method=$_SERVER['REQUEST_METHOD'] ?? 'GET'; $name=substr($route,4);
 if($method==='GET') {
  if($name==='challenge') ps_json(ps_challenge());
  if($name==='locations') {
    $db=ps_db();$type=$_GET['type'] ?? 'search';$state=max(0,(int)($_GET['state_id'] ?? 0));$city=max(0,(int)($_GET['city_id'] ?? 0));$q=trim((string)($_GET['q'] ?? ''));
    if($type==='countries') ps_json(['items'=>$db->query("SELECT DISTINCT country_id id,'India' name,'IN' code FROM hm_states WHERE country_id=101")->fetchAll()]);
    if($type==='states') ps_json(['items'=>$db->query('SELECT id,name,code FROM hm_states ORDER BY name')->fetchAll()]);
    if($type==='cities') {$stmt=$db->prepare('SELECT id,name,latitude,longitude FROM hm_cities WHERE state_id=? ORDER BY name LIMIT 2500');$stmt->execute([$state]);ps_json(['items'=>$stmt->fetchAll()]);}
    if(mb_strlen($q)<2 || mb_strlen($q)>80) ps_json(['items'=>[]]);
    $like=str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
    $where="(p.pincode LIKE ? ESCAPE '!' OR p.office_name LIKE ? ESCAPE '!' OR p.district LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!')";$args=[$like,$like,$like,$like];
    if($state){$where.=' AND p.state_id=?';$args[]=$state;}if($city){$where.=' AND p.city_id=?';$args[]=$city;}
    $stmt=$db->prepare("SELECT p.id,p.pincode,p.office_name,p.state_id,p.city_id,p.district,p.latitude,p.longitude,s.name state_name,c.name city_name FROM hm_post_offices p JOIN hm_states s ON s.id=p.state_id LEFT JOIN hm_cities c ON c.id=p.city_id WHERE $where ORDER BY p.pincode,p.office_name LIMIT 15");$stmt->execute($args);ps_json(['items'=>$stmt->fetchAll(),'note'=>'PIN/post-office centres are approximate; PINs are not polygons.']);
  }
  if($name==='summary' || $name==='zones') {
    $rawDays=strtolower(trim((string)($_GET['days'] ?? '90')));
    if(!in_array($rawDays,['7','30','90','365','all','0'],true)) throw new HerMapError('Choose a valid reporting window.');
    $days=($rawDays==='all'||$rawDays==='0')?'all':(int)$rawDays;
    if($name==='summary') {
      $x=$y=null;
      if(isset($_GET['x'],$_GET['y'])) { $x=filter_var($_GET['x'],FILTER_VALIDATE_INT);$y=filter_var($_GET['y'],FILTER_VALIDATE_INT);if($x===false||$y===false||$x<272||$x>390||$y<24||$y>148) throw new HerMapError('Invalid area.',422); }
      if(isset($_GET['lng'],$_GET['lat'])) {$lng=filter_var($_GET['lng'],FILTER_VALIDATE_FLOAT);$lat=filter_var($_GET['lat'],FILTER_VALIDATE_FLOAT);if($lng===false||$lat===false||!ps_in_india((float)$lng,(float)$lat)) throw new HerMapError('Choose a location within the official India outline.',422);$x=(int)floor($lng*4);$y=(int)floor($lat*4);}
      ps_json(ps_summary($x,$y,$days));
    }
    $whereZones="r.status='approved' AND r.approved_at<=?";
    $paramsZones=[gmdate('Y-m-d H:i:s',time()-PUBLICATION_DELAY_HOURS*3600)];
    if($days!=='all'){
      $whereZones.=" AND r.incident_date>=?";
      $paramsZones[]=(new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata')))->sub(new DateInterval('P'.($days-1).'D'))->format('Y-m-d');
    }
    $stmt=ps_db()->prepare("SELECT r.cell_x, r.cell_y, COUNT(*) n, MAX(p.office_name) office_name, MAX(p.pincode) pincode, MAX(p.district) district, MAX(c.name) city_name, MAX(s.name) state_name FROM hm_incidents r LEFT JOIN hm_post_offices p ON p.id=r.post_office_id LEFT JOIN hm_states s ON s.id=r.state_id LEFT JOIN hm_cities c ON c.id=r.city_id WHERE $whereZones GROUP BY r.cell_x, r.cell_y HAVING n>=? LIMIT 10000");
    $paramsZones[]=PUBLIC_MIN_REPORTS;
    $stmt->execute($paramsZones);
    $features=[];
    foreach($stmt->fetchAll() as $row){
      $parts=array_filter([$row['office_name'],($row['city_name']&&$row['city_name']!==$row['office_name']?$row['city_name']:$row['district']),$row['state_name']]);
      $areaName=$parts?(implode(', ',$parts).($row['pincode']?' (PIN '.$row['pincode'].')':'')):'Selected area';
      $features[]=['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[((int)$row['cell_x']+.5)/4,((int)$row['cell_y']+.5)/4]],'properties'=>['x'=>(int)$row['cell_x'],'y'=>(int)$row['cell_y'],'name'=>$areaName,'count'=>ps_count_band((int)$row['n']),'level'=>(int)min(3,floor((int)$row['n']/10))]];
    }
    ps_json(['type'=>'FeatureCollection','features'=>$features]);
  }
  if($name==='stats') ps_json(ps_site_stats());
  if($name==='admin-stats') {if(!ps_admin_logged_in(false))throw new HerMapError('Your session expired. Please sign in again.',401);ps_json(ps_admin_stats());}
 }
 if($method==='POST') {
  if((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>4096 || !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '','application/json')) throw new HerMapError('Send a small JSON request.',413);
  $raw=file_get_contents('php://input',false,null,0,4097);if(strlen($raw)>4096) throw new HerMapError('Request is too large.',413);
  $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($body)) throw new HerMapError('Invalid request.');
  foreach($body as $value) if(!is_scalar($value) && $value!==null) throw new HerMapError('Invalid field value.');
  if($name==='reports') ps_json(ps_save_incident($body),201);
  if($name==='contact') {
    ps_require_csrf((string)($body['csrf'] ?? ''));
    ps_rate_limit('contact', 5, 3600);
    ps_validate_challenge($body);
    $category = (string)($body['category'] ?? 'general');
    $validCategories = ['general', 'privacy_request', 'content_concern', 'legal_grievance', 'bug_report'];
    if (!in_array($category, $validCategories, true)) throw new HerMapError('Please select a valid message category.', 422);
    $name_val = trim((string)($body['name'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $subject = trim((string)($body['subject'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    if (mb_strlen($name_val) > 100 || preg_match('/[\x00-\x1f<>]/u', $name_val)) throw new HerMapError('Please provide a valid name.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) throw new HerMapError('Please provide a valid email address.', 422);
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150 || preg_match('/[\x00-\x1f<>]/u', $subject)) throw new HerMapError('Subject must be between 3 and 150 characters without angle brackets.', 422);
    if (mb_strlen($message) < 10 || mb_strlen($message) > 2500) throw new HerMapError('Message must be between 10 and 2500 characters.', 422);
    $ticketId = 'HM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $ipHash = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'cli', ps_secret());
    $stmt = ps_db()->prepare('INSERT INTO hm_contact_messages(ticket_id, category, name, email, subject, message, status, ip_hash) VALUES(?, ?, ?, ?, ?, ?, "new", ?)');
    $stmt->execute([$ticketId, $category, $name_val ?: 'Anonymous', $email, $subject, $message, $ipHash]);
    ps_json(['success' => true, 'ticket_id' => $ticketId, 'message' => "Thank you. Your message has been saved to our private queue with Ticket ID {$ticketId}. Our team will review it."], 201);
  }
  if($name==='receipt') {
    ps_require_csrf((string)($body['csrf'] ?? ''));ps_rate_limit('receipt',20);
    $token=trim((string)($body['receipt'] ?? ''));
    if(!preg_match('/^(hermap[0-9]{9}|[a-f0-9]{48})$/i',$token)) throw new HerMapError('Please enter a valid receipt number (e.g. hermap000000001).',422);
    $hash=hash('sha256',strtolower($token));$stmt=ps_db()->prepare('SELECT id,status FROM hm_incidents WHERE receipt_hash=?');$stmt->execute([$hash]);$report=$stmt->fetch();
    if(!$report) throw new HerMapError('No report was found for this receipt.',404);
    if(($body['action'] ?? '')==='delete') {ps_db()->prepare('DELETE FROM hm_incidents WHERE id=?')->execute([$report['id']]);ps_json(['message'=>'Your incident record and venue details have been deleted. Aggregate visit/submission counters and non-identifying review logs remain.']);}
    ps_json(['status'=>$report['status'],'message'=>'Receipt status is private. Approved reports enter eligible grouped summaries only after the publication delay.']);
  }
  if($name==='heartbeat') {
    $token=(string)($body['token'] ?? '');if(!preg_match('/^[a-f0-9]{48}$/',$token)) throw new HerMapError('Invalid visit token.',422);
    // Update existing visits only: this endpoint cannot inflate the lifetime total.
    $stmt=ps_db()->prepare('UPDATE hm_live_visits SET expires_at=? WHERE token_hash=? AND expires_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');$stmt->execute([gmdate('Y-m-d H:i:s',time()+120),hash('sha256',$token)]);
    ps_json(['active'=>$stmt->rowCount()>0]);
  }
 }
 throw new HerMapError('Endpoint not found.',404);
}catch(HerMapError $e){ps_json(['error'=>$e->getMessage()],$e->status);}catch(JsonException $e){ps_json(['error'=>'Invalid JSON request.'],400);}catch(Throwable $e){error_log('HerMap API failure: '.get_class($e));ps_json(['error'=>'The service is temporarily unavailable. Please try again later.'],503);}
