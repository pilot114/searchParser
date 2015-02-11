<?php

use Plumcake\Searcher as Searcher;
use Plumcake\Monger as Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

$m = new Monger($config);
$m->updateUniqs();