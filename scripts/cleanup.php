<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/inc/config.php';
ps_cleanup();echo "Expired application records cleaned in bounded batches.\n";
