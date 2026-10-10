<?php require_once __DIR__ . '/config.php';
$visitToken='';
if(($route??'')!=='admin' && (http_response_code()?:200)===200 && ($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
    try{$visitToken=ps_start_visit();}catch(Throwable $e){error_log('HerMap visit counter unavailable');}
    header('Cache-Control: no-store');
}
?>
<!doctype html><html lang="en"><head><?php require DIR_INC . '/meta.php'; require DIR_INC . '/css.php'; ?></head><body>
<?php require DIR_INC . '/header.php'; ?>
<main id="main" class="content-main" tabindex="-1">
