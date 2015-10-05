<?php

require_once dirname(__FILE__).'/twitter/rest.php';

$conf_nm = isset($argv[1]) ? $argv[1] : '';

$twitter = \Twitter\Rest::getInstance($conf_nm);
$user_ids = $twitter->get_blocks_ids();
$twitter->unblock($user_ids);

echo 'Done: '.__FILE__.PHP_EOL;