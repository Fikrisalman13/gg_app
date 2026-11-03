<?php

echo "PHP Version: " . PHP_VERSION . "<br>";

echo "Loaded INI: " .
     php_ini_loaded_file() . "<br>";

echo "Extension Directory: " .
     ini_get('extension_dir') . "<br>";

echo "SQLSRV Loaded: ";
var_dump(extension_loaded('sqlsrv'));

echo "<br>PDO SQLSRV Loaded: ";
var_dump(extension_loaded('pdo_sqlsrv'));

echo "<br>Function sqlsrv_connect: ";
var_dump(function_exists('sqlsrv_connect'));

echo "<br>Thread Safety: ";
var_dump(defined('ZEND_THREAD_SAFE') && ZEND_THREAD_SAFE);