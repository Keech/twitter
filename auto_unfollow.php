<?php

require_once 'twitter/rest.php';

$conf_nm = isset($argv[1]) ? $argv[1] : '';

$twitter = \Twitter\Rest::getInstance($conf_nm);
$twitter->auto_unfollow();

echo 'Done: '.__FILE__.PHP_EOL;