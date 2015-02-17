<?php

use Plumcake\Parsers\Searcher as Searcher;
use Plumcake\Monger as Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

$m = new Monger($config);
$task = $m->findBacklinkTask();
$task['query'] = urldecode($task['query']);

$search = new Searcher($config, $m);
$search->initChannels(['google' => $task]);
$linkBatches = $search->now();

// there only google =)
foreach ($linkBatches as $engine => $links) {
	if (empty($links)) {
		$m->updateTask($task, 0, $status = 'pause');
	} else {
		$m->saveBackinks($links, 'google');
		list($min, $max) = $config['engines'][$engine]['full'];
		if (count($links) >= $min && count($links) <= $max) {
			$m->updateTask($task, count($links), $status = 'run');
		} else {
			$m->updateTask($task, count($links), $status = 'stop');
		}
		$m->saveDebug();
	}
}
