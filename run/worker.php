<?php

use Plumcake\Searcher as Searcher;
use Plumcake\Monger as Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

$m = new Monger($config);
$tasks = $m->findTasks();

if (empty($tasks)) {
	echo "Tasks not found\n"; die();
}
$search = new Searcher($config, $m);
$search->initChannels($tasks);
$linkBatches = $search->now();
$search->closeChannels();

foreach ($linkBatches as $engine => $links) {

	if (empty($links)) {
		$m->updateTask($tasks[$engine], count($links), $status = 'pause');
	} else {
		$m->saveLinks($links, $engine);
		list($min, $max) = $config['engines'][$engine]['full'];
		if (count($links) >= $min && count($links) <= $max) {
			$m->updateTask($tasks[$engine], count($links), $status = 'run');
		} else {
			$m->updateTask($tasks[$engine], 0, $status = 'stop');
		}
	}
}
$m->saveDebug();



// 0,3 sec
usleep(300000);
$tasks = $m->findTasks();
if (empty($tasks)) {
	echo "Tasks not found\n"; die();
}
$search = new Searcher($config, $m);
$search->initChannels($tasks);
$linkBatches = $search->now();
$search->closeChannels();

foreach ($linkBatches as $engine => $links) {

	if (empty($links)) {
		$m->updateTask($tasks[$engine], count($links), $status = 'pause');
	} else {
		$m->saveLinks($links, $engine);
		list($min, $max) = $config['engines'][$engine]['full'];
		if (count($links) >= $min && count($links) <= $max) {
			$m->updateTask($tasks[$engine], count($links), $status = 'run');
		} else {
			$m->updateTask($tasks[$engine], 0, $status = 'stop');
		}
	}
}
$m->saveDebug();