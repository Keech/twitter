<?php

require_once 'twitter/rest.php';

$conf_nm = isset($argv[1]) ? $argv[1] : '';

$twitter = \Twitter\Rest::getInstance($conf_nm);
$twitter->search_and_favorite();

echo 'Done: '.__FILE__.PHP_EOL;