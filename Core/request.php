<?php
chdir(dirname(dirname(__FILE__)));
require_once('Core/Core.php');
echo Dispatcher::dispatch($_SERVER['REQUEST_URI'], $_REQUEST);