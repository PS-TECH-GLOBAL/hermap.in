<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/inc/config.php';
if(PROD)exit("Run only against the local development installation.\n");
$checks=0;$db=ps_db();$createdId=null;$countedDay=null;$curl=null;$auditIds=[];$visitHash=null;$visitCountedDay=null;$httpSourceIp=null;$httpStart=time();
function check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function fails(callable $callback,int $status):void{try{$callback();throw new RuntimeException('Expected rejection.');}catch(HerMapError $e){check($e->status===$status,'Wrong rejection status.');}}
try{
 try{$db->query('SELECT COUNT(*) FROM mysql.global_priv');throw new RuntimeException('Runtime database account can read server permissions.');}catch(PDOException $e){check((int)($e->errorInfo[1]??0)===1142,'Unexpected scoped-account denial.');}
 check(ps_totp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',1)==='287082','RFC 6238 TOTP vector failed.');
 check(ps_in_india(74.3,35.2)&&ps_in_india(96,28)&&ps_in_india(77.22,28.64),'Official outline misses expected Indian locations.');
 check(!ps_in_india(65,20),'Outline accepts outside point.');
 $venue=['name'=>'HERMAP TEST VENUE — fictional','website'=>'https://example.com'];$cipher=ps_encrypt_venue($venue);
 check($cipher!==null&&!str_contains($cipher,$venue['name'])&&ps_decrypt_venue($cipher)===$venue,'Authenticated venue encryption failed.');
 $tampered=base64_decode($cipher);$tampered[30]=chr(ord($tampered[30])^1);check(ps_decrypt_venue(base64_encode($tampered))['name']==='Unable to decrypt','Tampered ciphertext accepted.');
 $online=ps_online_details(['online_handle'=>'@fictional_test_account','online_platform'=>'Test platform'],'online','online');
 $context=$venue+$online;$encrypted=ps_encrypt_venue($context);check(ps_decrypt_venue($encrypted)===$context&&!str_contains($encrypted,$online['handle']),'Private online context encryption failed.');
 check(ps_online_details(['online_handle'=>'@stale'],'transport','public')===['handle'=>'','platform'=>''],'Stale online details were retained for another context.');
 check(ps_online_details([],'online','online')===['handle'=>'','platform'=>''],'Optional online details required.');
 check(ps_optional_location(['latitude'=>'28.6139','longitude'=>'77.209'],28.61,77.21)===['latitude'=>'','longitude'=>''],'Point saved without explicit sharing.');
 $point=ps_optional_location(['share_location'=>true,'latitude'=>'28.6139123','longitude'=>'77.2090123'],28.61,77.21);check($point===['latitude'=>'28.613912','longitude'=>'77.209012'],'Optional point validation/precision failed.');
 fails(fn()=>ps_optional_location(['share_location'=>true,'latitude'=>'bad','longitude'=>'77.21'],28.61,77.21),422);
 fails(fn()=>ps_optional_location(['share_location'=>true,'latitude'=>20,'longitude'=>65],28.61,77.21),422);
 fails(fn()=>ps_optional_location(['share_location'=>true,'latitude'=>12.97,'longitude'=>77.59],28.61,77.21),422);
 fails(fn()=>ps_online_details(['online_handle'=>'https://example.com/profile'],'online','online'),422);
 fails(fn()=>ps_online_details(['online_handle'=>str_repeat('a',101)],'online','online'),422);
 fails(fn()=>ps_online_details(['online_platform'=>'<script>'],'online','online'),422);
 $states=json_decode(file_get_contents(ROOT_PATH.'/assets/data/india-states.geojson'),true,512,JSON_THROW_ON_ERROR);check(count($states['features'])===36,'State boundary data missing territories.');
 check(in_array('Lakshadweep',array_column(array_column($states['features'],'properties'),'name'),true),'Lakshadweep state geometry missing.');
 ps_session();$_SESSION['challenge']=['id'=>'unit','answer'=>'21','issued'=>time()-3];ps_validate_challenge(['challenge_id'=>'unit','challenge_answer'=>'21']);check(!isset($_SESSION['challenge']),'Challenge was not consumed.');fails(fn()=>ps_validate_challenge(['challenge_id'=>'unit','challenge_answer'=>'21']),422);
 $_SESSION['challenge']=['id'=>'unit','answer'=>'21','issued'=>time()-601];fails(fn()=>ps_validate_challenge(['challenge_id'=>'unit','challenge_answer'=>'21']),422);fails(fn()=>ps_require_csrf('wrong'),403);
 $office=$db->query("SELECT * FROM hm_post_offices WHERE pincode='110001' AND state_id=10 AND latitude IS NOT NULL LIMIT 1")->fetch();check((bool)$office,'Delhi fixture location missing.');
 $x=(int)floor((float)$office['longitude']*4);$y=(int)floor((float)$office['latitude']*4);$before=ps_summary($x,$y,30);
 // Uncommitted fixtures are invisible to other database connections and always rolled back.
 $db->beginTransaction();$insert=$db->prepare('INSERT INTO hm_incidents(id,category,place_type,state_id,city_id,post_office_id,cell_x,cell_y,incident_date,incident_hour,incident_minute,status,approved_at,receipt_hash,duplicate_hash,delete_after) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $date=(new DateTimeImmutable('last monday',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
 $fixture=function(string $status='approved',string $approval='-3 days',?string $dateOverride=null,string $category='workplace')use($insert,$office,$x,$y,$date){$id=bin2hex(random_bytes(16));$insert->execute([$id,$category,'office',10,$office['city_id'],$office['id'],$x,$y,$dateOverride ?: $date,21,5,$status,$status==='approved'?gmdate('Y-m-d H:i:s',strtotime($approval)):null,hash('sha256',$id.'r'),hash('sha256',$id.'d'),gmdate('Y-m-d H:i:s',time()+400*86400)]);};
 // Assert threshold on an otherwise empty cell; do not assume the entire installation is empty.
 if(!$before['available']){for($i=0;$i<4;$i++)$fixture();check(!ps_summary($x,$y,30)['available'],'Sparse group exposed.');$fixture();$summary=ps_summary($x,$y,30);check($summary['available']&&$summary['total']==='5–9','Eligible group/count band failed.');check($summary['category']==='Workplace or office harassment'&&$summary['hour']==='9 pm–10 pm IST'&&$summary['day']==='Monday','Category/time/day summary failed.');$fixture('pending');$fixture('approved','now');$fixture('approved','-3 days',gmdate('Y-m-d',time()-60*86400));check(ps_summary($x,$y,30)['total']==='5–9','Review/delay/window filtering failed.');check(!str_contains(json_encode($summary),'receipt_hash')&&!isset($summary['reports']),'Private fields leaked.');}
 $db->rollBack();check(ps_summary($x,$y,30)===$before,'Fixture rollback failed.');
 if(!in_array('--http',$argv,true)){echo "$checks backend checks passed; all fixtures rolled back.\n";exit;}
 $base=rtrim(getenv('HERMAP_TEST_URL') ?: 'http://127.0.0.1:8095','/');$curl=curl_init();$cookies=ROOT_PATH.'/tmp/http-test-'.bin2hex(random_bytes(8)).'.txt';
 // A separate loopback source isolates test rate limits from the user's browser.
 $httpSourceIp='127.0.0.'.random_int(2,254);
 curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookies,CURLOPT_COOKIEJAR=>$cookies,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_INTERFACE=>$httpSourceIp]);
 $request=function(string $path,?array $body=null,bool $form=false)use($curl,$base){curl_setopt($curl,CURLOPT_URL,$base.$path);curl_setopt($curl,CURLOPT_POST,$body!==null);curl_setopt($curl,CURLOPT_HTTPHEADER,$body?['Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json'),'Origin: '.$base]:[]);if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));$text=curl_exec($curl);if($text===false)throw new RuntimeException('Local HTTP request failed.');return[(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$text];};
 foreach(['/.env','/inc/config.php','/scripts/setup-database.php','/ps_crm_countries.sql','/tmp/admin-access.txt']as$path){[$status]=$request($path);check($status===403,'Private path exposed.');}
 [$status]=$request('/api/admin-stats');check($status===401,'Private dashboard data exposed without login.');
 [$status]=$request('/api/summary?days[]=30');check($status===400,'Nested query value accepted.');
 [$status]=$request('/api/reports',['csrf'=>['nested']]);check($status===400,'Nested form value accepted.');
 [$status,$text]=$request('/api/stats');$visitsBefore=json_decode($text,true)['total_visits'];
 [$status,$text]=$request('/about');preg_match('/data-visit-token="([a-f0-9]{48})"/',$text,$visitMatch);$visitToken=$visitMatch[1]??'';$visitHash=hash('sha256',$visitToken);$visitCountedDay=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');check($status===200&&strlen($visitToken)===48,'Public page visit tracking failed.');
 [$status,$text]=$request('/api/stats');check(json_decode($text,true)['total_visits']===$visitsBefore+1,'Public page load did not increment total visits exactly once.');
 $db->prepare('UPDATE hm_live_visits SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 MINUTE) WHERE token_hash=?')->execute([$visitHash]);
 [$status,$text]=$request('/api/heartbeat',['token'=>$visitToken]);check($status===200&&json_decode($text,true)['active']===true,'A returning visible visit could not renew.');
 [$status,$text]=$request('/api/stats');check(json_decode($text,true)['total_visits']===$visitsBefore+1,'API reads/heartbeat incorrectly increment total visits.');
 [$status,$text]=$request('/api/locations?type=states');check($status===200&&count(json_decode($text,true)['items'])===36,'Dynamic states failed.');
 [$status,$text]=$request('/api/reports',['csrf'=>'wrong']);check($status===403,'Report accepted without CSRF.');
 [$status,$text]=$request('/api/challenge');$challenge=json_decode($text,true);check($status===200&&!isset($challenge['answer']),'Challenge invalid or answer exposed.');
 $when=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->sub(new DateInterval('PT2H'));$body=['csrf'=>$challenge['csrf'],'challenge_id'=>$challenge['id'],'challenge_answer'=>'999','category'=>'workplace','place_type'=>'office','state_id'=>10,'post_office_id'=>(int)$office['id'],'date'=>$when->format('Y-m-d'),'time'=>$when->format('H:i'),'venue_name'=>$venue['name'].' '.bin2hex(random_bytes(4)),'venue_website'=>$venue['website'],'adult'=>true,'consent'=>true,'website_check'=>''];
 [$status]=$request('/api/reports',$body);check($status===422,'Expected CAPTCHA rejection, received HTTP '.$status.'.');
 [$status,$text]=$request('/api/challenge');$challenge=json_decode($text,true);preg_match('/(\d+) ([+−]) (\d+)/u',$challenge['question'],$parts);$body['csrf']=$challenge['csrf'];$body['challenge_id']=$challenge['id'];$body['challenge_answer']=(string)($parts[2]==='+'?(int)$parts[1]+(int)$parts[3]:(int)$parts[1]-(int)$parts[3]);sleep(2);
 $body['category']='online';$body['online_handle']='@hermap_fictional_test';$body['online_platform']='Example platform';
 $body['share_location']=true;$body['latitude']='28.6139';$body['longitude']='77.209';
 [$status,$text]=$request('/api/reports',$body);$saved=json_decode($text,true);check($status===201&&preg_match('/^[a-f0-9]{48}$/',$saved['receipt']??''),'Real submission did not save.');
 $stmt=$db->prepare('SELECT * FROM hm_incidents WHERE receipt_hash=?');$stmt->execute([hash('sha256',$saved['receipt'])]);$record=$stmt->fetch();$createdId=$record['id']??null;$countedDay=(new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');check($createdId!==null&&$record['status']==='pending','Submission did not enter private queue.');check(ps_decrypt_venue($record['venue_encrypted'])['name']===$body['venue_name'],'Private venue not stored encrypted.');
 check(ps_decrypt_venue($record['venue_encrypted'])['handle']===$body['online_handle']&&!str_contains($record['venue_encrypted'],$body['online_handle']),'Online account handle not stored encrypted.');
 $private=ps_decrypt_venue($record['venue_encrypted']);check($private['latitude']==='28.6139'&&$private['longitude']==='77.209'&&(int)$record['cell_x']===(int)floor(77.209*4),'Shared point did not save encrypted/assign broad cell.');
 [$status,$public]=$request('/api/summary?days=30');check(!str_contains($public,$body['online_handle'])&&!str_contains($public,$body['venue_name']),'Private context exposed in public summary.');
 check(!str_contains($public,'28.6139')&&!str_contains($public,'77.209'),'Precise shared coordinates exposed publicly.');
 [$status]=$request('/api/reports',$body);check($status===422,'Challenge replay accepted.');
 [$status,$text]=$request('/api/challenge');$duplicate=json_decode($text,true);preg_match('/(\d+) ([+−]) (\d+)/u',$duplicate['question'],$parts);$body['csrf']=$duplicate['csrf'];$body['challenge_id']=$duplicate['id'];$body['challenge_answer']=(string)($parts[2]==='+'?(int)$parts[1]+(int)$parts[3]:(int)$parts[1]-(int)$parts[3]);sleep(2);[$status]=$request('/api/reports',$body);check($status===409,'Duplicate report accepted.');
 [$status,$text]=$request('/admin');check($status===200&&str_contains($text,'Moderator sign in')&&!str_contains($text,'Incident review'),'Admin is not protected.');preg_match('/name="csrf" value="([a-f0-9]+)"/',$text,$match);$adminCsrf=$match[1];
 $accessPath=getenv('HERMAP_ACCESS_FILE') ?: ROOT_PATH.'/tmp/admin-access.txt';
 $access=file_get_contents($accessPath);preg_match('/^Password: (.+)$/m',$access,$match);$password=trim($match[1]??'');
 [$status]=$request('/admin',['csrf'=>$adminCsrf,'action'=>'login','username'=>'amit','password'=>$password,'otp'=>'bad'],true);check($status===200,'Unexpected login response.');
 [$status]=$request('/admin',['csrf'=>$adminCsrf,'action'=>'login','username'=>'amit','password'=>$password,'otp'=>ps_totp(getenv('ADMIN_TOTP_SECRET'),(int)floor(time()/30))],true);check($status===303,'Password plus TOTP login failed.');
 [$status,$text]=$request('/admin');check(str_contains($text,'Reports all time')&&str_contains($text,'chart-group')&&str_contains($text,ps_escape($body['venue_name'])),'Dashboard/private queue missing.');preg_match('/name="csrf" value="([a-f0-9]+)"/',$text,$match);$adminCsrf=$match[1];
 [$status,$text]=$request('/api/admin-stats');$dashboard=json_decode($text,true);check($status===200&&isset($dashboard['metrics']['Reports this week'],$dashboard['daily']),'Live admin dashboard data failed.');
 [$status]=$request('/admin',['csrf'=>'bad','action'=>'approve','report_id'=>$createdId],true);$stmt->execute([hash('sha256',$saved['receipt'])]);check($stmt->fetch()['status']==='pending','Moderation bypassed CSRF.');
 [$status]=$request('/admin',['csrf'=>$adminCsrf,'action'=>'approve','report_id'=>$createdId],true);check($status===303,'Moderator approval failed.');$stmt->execute([hash('sha256',$saved['receipt'])]);check($stmt->fetch()['status']==='approved','Review not persisted.');
 [$status,$text]=$request('/api/receipt',['csrf'=>$duplicate['csrf'],'receipt'=>$saved['receipt']]);check($status===403,'Login failed to rotate session CSRF.');
 [$status,$text]=$request('/api/challenge');$csrf=json_decode($text,true)['csrf'];[$status,$text]=$request('/api/receipt',['csrf'=>$csrf,'receipt'=>$saved['receipt']]);check($status===200&&json_decode($text,true)['status']==='approved','Receipt status failed.');
 $audit=$db->prepare('SELECT id FROM hm_moderation_actions WHERE report_id=?');$audit->execute([$createdId]);$auditIds=$audit->fetchAll(PDO::FETCH_COLUMN);
 [$status]=$request('/api/receipt',['csrf'=>$csrf,'receipt'=>$saved['receipt'],'action'=>'delete']);check($status===200,'Receipt deletion failed.');$stmt->execute([hash('sha256',$saved['receipt'])]);check(!$stmt->fetch(),'Deleted record still present.');
 [$status]=$request('/admin',['csrf'=>$adminCsrf,'action'=>'logout'],true);check($status===303,'Logout failed.');[$status,$text]=$request('/admin');check(str_contains($text,'Moderator sign in')&&!str_contains($text,'Incident review'),'Logged-out admin still accessible.');
 echo "$checks backend and local HTTP checks passed. Test reports/counters are removed in final cleanup.\n";
}finally{
 if($db->inTransaction())$db->rollBack();
 foreach($auditIds as $auditId)$db->prepare('DELETE FROM hm_moderation_actions WHERE id=?')->execute([$auditId]);
 if($createdId){$db->prepare('DELETE FROM hm_moderation_actions WHERE report_id=?')->execute([$createdId]);$db->prepare('DELETE FROM hm_incidents WHERE id=?')->execute([$createdId]);}
 if($countedDay)$db->prepare('UPDATE hm_site_daily SET received_reports=GREATEST(0,received_reports-1) WHERE day=?')->execute([$countedDay]);
 if($visitHash)$db->prepare('DELETE FROM hm_live_visits WHERE token_hash=?')->execute([$visitHash]);
 if($visitCountedDay)$db->prepare('UPDATE hm_site_daily SET visits=GREATEST(0,visits-1) WHERE day=?')->execute([$visitCountedDay]);
 if($httpSourceIp)foreach(array_unique([(int)floor($httpStart/3600),(int)floor(time()/3600)])as$slot)foreach(['report','admin-login','challenge','receipt']as$purpose)$db->prepare('DELETE FROM hm_rate_limits WHERE bucket=?')->execute([hash_hmac('sha256',$purpose.'|'.$slot.'|'.$httpSourceIp,ps_secret())]);
 if($curl){curl_close($curl);if(isset($cookies)&&is_file($cookies))unlink($cookies);}
}
