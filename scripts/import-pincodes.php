<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/inc/config.php';
$db = in_array('--bootstrap', $argv,true)
    ? new PDO('mysql:host=127.0.0.1;dbname=hermap;charset=utf8mb4','root',getenv('HERMAP_SETUP_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]) : ps_db();
$source=$argv[1] ?? ''; $geoFile=$argv[2] ?? '';
if(!is_file($source)||!is_file($geoFile)) exit("Usage: php scripts/import-pincodes.php fallback.json geonames/IN.txt [--bootstrap]\n");
$states=$db->query('SELECT id,name FROM hm_states')->fetchAll(PDO::FETCH_KEY_PAIR);
function normalise(string $v):string {return preg_replace('/[^a-z0-9]/','',strtolower($v));}
$stateMap=[]; foreach($states as $id=>$name) $stateMap[normalise($name)]=(int)$id;
$aliases=['Andaman Nicobar'=>1,'Andaman & Nicobar Islands'=>1,'Dadra & Nagar Haveli'=>8,'Daman & Diu'=>8,'Jammu & Kashmir'=>15,'Lakshdweep'=>20,'Orissa'=>29,'Pondicherry'=>31,'Uttaranchal'=>39,'Uttar Pradesh'=>38];
foreach($aliases as $name=>$id) $stateMap[normalise($name)]=$id;
$coords=[]; $geoRows=[];
$handle=fopen($geoFile,'rb');
while(($row=fgetcsv($handle,0,"\t"))!==false) {
    if(count($row)<12 || !preg_match('/^[1-9]\d{5}$/',$row[1])) continue;
    $lat=(float)$row[9];$lng=(float)$row[10];
    if($lat<6||$lat>37.7||$lng<68||$lng>98) continue;
    $pin=$row[1]; $coords[$pin][] = [$lat,$lng];
    $geoRows[]=['PostOfficeName'=>$row[2],'Pincode'=>$pin,'City'=>$row[5] ?: $row[2],'District'=>$row[5] ?: 'Not specified','State'=>$row[3],'source'=>'GeoNames'];
}
fclose($handle);
$centres=[]; foreach($coords as $pin=>$points) {
    $lats=array_column($points,0);$lngs=array_column($points,1);sort($lats);sort($lngs);$mid=(int)floor(count($points)/2);
    $centres[$pin]=[$lats[$mid],$lngs[$mid]];
}
$fallback=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($source)),true,512,JSON_THROW_ON_ERROR)['Sheet1'];
$insertCity=$db->prepare('INSERT IGNORE INTO hm_cities(state_id,name) VALUES(?,?)');
$findCity=$db->prepare('SELECT id FROM hm_cities WHERE state_id=? AND name=?');
$insert=$db->prepare('INSERT IGNORE INTO hm_post_offices(pincode,office_name,state_id,city_id,district,latitude,longitude,source) VALUES(?,?,?,?,?,?,?,?)');
$cityCache=[];$inserted=0;$invalid=0;$unknown=0;
$db->beginTransaction();
try {
  foreach(array_merge($fallback,$geoRows) as $row) {
    $pin=trim((string)$row['Pincode']);
    if(!preg_match('/^[1-9]\d{5}$/',$pin)) {$invalid++;continue;}
    $sid=$stateMap[normalise((string)$row['State'])] ?? null;
    if($sid===2 && substr($pin,0,2)==='50') $sid=36;
    if($sid===15 && substr($pin,0,3)==='194') $sid=42;
    if(!$sid) {$unknown++;continue;}
    $name=mb_substr(trim((string)$row['City']),0,100);
    if($name==='') $name=mb_substr(trim((string)$row['District']),0,100);
    $key="$sid:".normalise($name);
    if(!isset($cityCache[$key])) {$insertCity->execute([$sid,$name]);$findCity->execute([$sid,$name]);$cityCache[$key]=(int)$findCity->fetchColumn();}
    $point=$centres[$pin] ?? [null,null];
    $insert->execute([$pin,mb_substr(trim((string)$row['PostOfficeName']),0,150),$sid,$cityCache[$key],mb_substr(trim((string)$row['District']),0,100),$point[0],$point[1],$row['source'] ?? 'dpnkrpl fallback']);
    $inserted+=$insert->rowCount();
  }
  $db->exec('UPDATE hm_cities c JOIN (SELECT city_id,AVG(latitude) lat,AVG(longitude) lng FROM hm_post_offices WHERE latitude IS NOT NULL GROUP BY city_id) p ON c.id=p.city_id SET c.latitude=p.lat,c.longitude=p.lng');
  $notes='Fallback repository is historical; GeoNames coordinates are approximate PIN centres, not polygons. Invalid PINs rejected, duplicate office/state/district rows removed. Telangana/Ladakh and merged UT aliases normalised.';
  $db->prepare('INSERT INTO hm_imports(source,imported_rows,notes) VALUES(?,?,?)')->execute(['dpnkrpl/indian-pincodes-database + GeoNames IN.zip',$inserted,$notes]);
  $db->commit();
} catch(Throwable $e){$db->rollBack();throw $e;}
echo json_encode(['new_rows'=>$inserted,'invalid_pins_rejected'=>$invalid,'unknown_states'=>$unknown,'post_offices'=>(int)$db->query('SELECT COUNT(*) FROM hm_post_offices')->fetchColumn(),'unique_pins'=>(int)$db->query('SELECT COUNT(DISTINCT pincode) FROM hm_post_offices')->fetchColumn(),'coordinates'=>(int)$db->query('SELECT COUNT(*) FROM hm_post_offices WHERE latitude IS NOT NULL')->fetchColumn()],JSON_PRETTY_PRINT)."\n";
