<?php
$dir['root'] = '/var/www/cron';
$dir['jobs'] = $dir['root']."/".'jobs';
$dir['temp'] = $dir['root'].'/tmp';

$io['0']['type'] = 'Mysql';
$io['0']['server'] = 'localhost';
$io['0']['database'] = 'test';
$io['0']['username'] = 'test';
$io['0']['password'] = 'test';
$io['0']['prefix'] = 'prefix_';

$io['1']['type'] = 'Shell';
// Debugger 
define("DEBUG", FALSE);
?>