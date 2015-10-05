<?php

require_once dirname(__FILE__).'/twitter/rest.php';

$conf_nm = isset($argv[1]) ? $argv[1] : '';

$twitter = \Twitter\Rest::getInstance($conf_nm);
$twitter->search_and_follow();

echo 'Done: '.__FILE__.PHP_EOL;