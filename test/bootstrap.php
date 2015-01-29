<?php

$me = dirname(__FILE__);
$root = dirname($me);

if (!file_exists($autoloader = $root . '/vendor/autoload.php')) {
    echo "Please run `composer install` first.\n";
    exit(1);
}

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once $autoloader;

if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
    require_once $me . '/FakeSessionHandler.php';
}

require_once $me . '/FakeHandler.php';
