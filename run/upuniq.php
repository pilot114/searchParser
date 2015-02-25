<?php

use Plumcake\Parsers\Searcher as Searcher;
use Plumcake\Monger as Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

//format "2015-01-15:00"
$start = str_replace(':', ' ', $argv[1]);
$end = str_replace(':', ' ', $argv[2]);

$start = "$start:00:00";
$end   = "$end:00:00";

$m = new Monger($config);
$m->updateUniqs($start, $end, $debug=true);