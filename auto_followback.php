<?php

require_once (dirname(__FILE__).'/twitter/user_stream.php');

$conf_nm = isset($argv[1]) ? $argv[1] : '';

$auto_followback = \Twitter\User_Stream::getInstance($conf_nm);
$auto_followback->run();

echo 'Done: '.__FILE__.PHP_EOL; //User_Streamを使っているため、実質的にこの処理が行われることはない。