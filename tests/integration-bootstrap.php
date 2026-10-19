<?php

$loader = require dirname(__DIR__).'/vendor/autoload.php';
$loader->addPsr4('App\\', [dirname(__DIR__).'/app-b/app', dirname(__DIR__).'/app-c/app'], true);
