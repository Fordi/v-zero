<?php
spl_autoload_register(function ($class) {
	$fn = dirname(__FILE__).DIRECTORY_SEPARATOR.$class.'.php';
	if (!file_exists($fn)) return;
	include_once($fn);
	if (!class_exists($class, false)) return;
	$autocall = array($class, '__autoload');
	if (is_callable($autocall))
		spl_autoload_register($autocall, false, false);
});
