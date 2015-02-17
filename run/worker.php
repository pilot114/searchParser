<?php

use Plumcake\Parsers\Searcher as Searcher;
use Plumcake\Monger as Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

$m = new Monger($config);
$tasks = $m->findTasks();

if (empty($tasks)) {
	// TODO in debug
	echo "Tasks not found\n"; die();
}
$search = new Searcher($config, $m);
$search->initChannels($tasks);
$linkBatches = $search->now();

foreach ($linkBatches as $engine => $links) {

	if (empty($links)) {
		$m->updateTask($tasks[$engine], 0, $status = 'pause');
	} else {
		$m->saveLinks($links, $engine);
		list($min, $max) = $config['engines'][$engine]['full'];
		if (count($links) >= $min && count($links) <= $max) {
			$m->updateTask($tasks[$engine], count($links), $status = 'run');
		} else {
			$m->updateTask($tasks[$engine], count($links), $status = 'stop');
		}
		$m->saveDebug();
	}
}
