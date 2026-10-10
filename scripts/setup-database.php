<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$db = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', getenv('HERMAP_SETUP_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE IF NOT EXISTS hermap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE hermap');
foreach (['countries','states','cities'] as $table) {
    if ($db->query("SHOW TABLES LIKE 'ps_crm_$table'")->fetchColumn()) continue;
    if (!is_file("$root/ps_crm_$table.sql")) continue;
    $sql = file_get_contents("$root/ps_crm_$table.sql");
    // The supplied exports contain only table definitions, inserts and index changes.
    $sql = str_replace('DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci', 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
    foreach(preg_split('/;[ \t]*(?:\r\n|\n|$)/', $sql) as $statement) {
        if(trim($statement)!=='') $db->exec($statement);
    }
}
$db->exec(file_get_contents("$root/database/schema.sql"));
$db->exec(file_get_contents("$root/database/location-seed.sql"));
if ($db->query("SHOW TABLES LIKE 'ps_crm_states'")->fetchColumn()) {
$db->exec("INSERT IGNORE INTO hm_states(id,name,code) SELECT id,name,state_code FROM ps_crm_states WHERE country_id=101 AND id NOT IN(9,46,47)");
$db->exec("UPDATE hm_states SET name='Dadra and Nagar Haveli and Daman and Diu' WHERE id=8");
$db->exec("UPDATE hm_states SET name='Puducherry' WHERE id=31");
}
if ($db->query("SHOW TABLES LIKE 'ps_crm_cities'")->fetchColumn() && $db->query("SHOW TABLES LIKE 'ps_crm_states'")->fetchColumn()) {
$db->exec("INSERT IGNORE INTO hm_cities(id,state_id,name) SELECT c.id,IF(c.state_id=9,8,c.state_id),c.name FROM ps_crm_cities c JOIN ps_crm_states s ON s.id=c.state_id WHERE s.country_id=101 AND c.state_id NOT IN(46,47)");
}
$db->exec('ALTER TABLE hm_cities AUTO_INCREMENT=200000');
if (!in_array('--schema-only', $argv, true) && !is_file("$root/.env")) {
    $password=bin2hex(random_bytes(24));
    $adminPassword=bin2hex(random_bytes(12));
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $totp='';
    for($i=0;$i<32;$i++) $totp.=$alphabet[random_int(0,31)];
    $db->exec("CREATE USER IF NOT EXISTS 'hermap_app'@'127.0.0.1' IDENTIFIED BY ".$db->quote($password));
    $db->exec("ALTER USER 'hermap_app'@'127.0.0.1' IDENTIFIED BY ".$db->quote($password));
    // Table grants avoid touching the shared mysql.db table and stay within HerMap.
    foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $ownedTable) {
        if(!preg_match('/^(hm_|ps_crm_)[a-z_]+$/',$ownedTable)) continue;
        $db->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON hermap.`$ownedTable` TO 'hermap_app'@'127.0.0.1'");
    }
    $adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
    $env="APP_ENV=local\nSITE_URL=http://localhost/hermap.in\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_NAME=hermap\nDB_USER=hermap_app\nDB_PASSWORD=$password\nSUPPORT_EMAIL=help@hermap.in\nREPORT_INTAKE_ENABLED=1\nAPP_SECRET=".bin2hex(random_bytes(32))."\nAPP_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\nADMIN_USERNAME=$adminUser\nADMIN_PASSWORD_HASH=".password_hash($adminPassword,PASSWORD_DEFAULT)."\nADMIN_TOTP_SECRET=$totp\nPUBLIC_MIN_REPORTS=5\nPUBLICATION_DELAY_HOURS=48\n";
    file_put_contents("$root/.env",$env);
    $access=getenv('HERMAP_ACCESS_FILE') ?: "$root/tmp/admin-access.txt";
    if(!is_dir(dirname($access))) mkdir(dirname($access),0700,true);
    file_put_contents($access,"HerMap local moderation access\nURL: http://localhost/hermap.in/admin\nUsername: $adminUser\nPassword: $adminPassword\nAuthenticator (TOTP) secret: $totp\nIssuer: HerMap.in\nKeep this file private. Add the secret to an authenticator app.\n");
    echo "Private application credentials and admin access file created.\n";
}
echo json_encode(['database'=>'hermap','countries'=>1,'states'=>(int)$db->query('SELECT COUNT(*) FROM hm_states')->fetchColumn(),'cities'=>(int)$db->query('SELECT COUNT(*) FROM hm_cities')->fetchColumn()], JSON_PRETTY_PRINT)."\n";
