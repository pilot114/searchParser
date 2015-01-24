<?php

use Plumcake\Monger;
use Plumcake\Searcher as Searcher;

require_once __DIR__.'/../vendor/autoload.php';
$config = include('../config.php');

$app = new Silex\Application();
$app['debug'] = true;

//$app->register(new Silex\Provider\MonologServiceProvider(), array(
//    'monolog.logfile' => __DIR__.'/../logs/requests.log',
//));


/*
		ROUTES
*/
$parser = $app['controllers_factory'];

$parser->get('/', function() use ($app) {
	return file_get_contents('main.html');
});
$parser->get('/work', function() use ($app) {
	return file_get_contents('work.html');
});
$parser->post('/work', function() use ($app, $config) {
	// prepare queries
	$queries = array_map('trim', explode("\n", $_POST['query']));
	$queries = array_map('urlencode', $queries);
	$queries = array_filter($queries); // remove empty string

	$m = new Monger($config);
	$search = new Searcher($config, $m);

	if($_POST['after'] == 'true'){
		$search->after($queries, $_POST['engines']);
	} else {
		// fake tasks for run NOW only FIRST query
		$tasks = [];
		foreach($_POST['engines'] as $engine){
			$tasks[$engine] = [
				'query'  => $queries[0],
				'status' => 'run',
				$engine  => 0
			];
		}
		$search->initChannels($tasks);
		$linkBatches = $search->now();
		$search->closeChannels();
		foreach ($linkBatches as $engine => $links) {
			if (!empty($links)) {
				$m->saveLinks($links, $engine);
			}
		}
	}
	$debug = $m->saveDebug();
	return $app->json($debug);
});



$parser->get('/formatter', function() use ($app) {
	return file_get_contents('formatter.html');
});
$parser->post('/formatter', function() use ($app, $config) {
	$links = array_map('trim', explode("\n", $_POST['links']));
	$links = array_filter($links); // remove empty string
	foreach ($links as $i => $link) {
		if(filter_var($link, FILTER_VALIDATE_URL)){
			$links[$i] = parse_url($link)['host'];
		} else {
			$links[$i] = $link;
		}
	}
	$links = array_unique($links);

	if ($_POST['ex_tasks']) {
		$m = new Monger($config);
		$mLinks = $m->getUniqTasks();
		foreach ($links as $i => $link) {
			if(in_array($link, $mLinks)){
				unset($links[$i]);
			}
		}
	}

	echo '<h1>Count: ' . count($links) . '</h1>';
	foreach ($links as $link) {
		echo $link . '<br>';
	}

	return false;
});




$parser->get('/run', function() use ($app, $config) {
	return include('../run/worker.php');	
});




$parser->get('/stat', function() use ($app, $config) {
	$m = new Monger($config);
	list($links, $counters) = $m->getUniqs();

	echo '<h1>Уникальных доменов: ' . count($links) . '</h1>';
	foreach ($counters as $engine => $counter) {
		echo '<h2>' . $engine . ': ' . $counter . '</h2>';
	}
	echo '<form method="POST"><input type="submit" value="Получить список"/>';
	return false;
});
$parser->post('/stat', function()use($app, $config) {
	$m = new Monger($config);
	list($links, $counters) = $m->getUniqs();
	arsort($links);
	$stream = function() use ($links){
		foreach ($links as $link => $count) {
			if(isset($_GET['num'])){
				echo $count . ': ' . $link . "<br>";
			} else {
				echo $link . "<br>";
			}
			ob_flush();
			flush();
		}
	};
	return $app->stream($stream);
});



$parser->get('/regex', function() use($app){
	return file_get_contents('regex.html');
});
$parser->post('/regex', function() use($app, $config){
	$links = array_map('trim', explode("\n", $_POST['links']));
	$stream = function() use ($links){
		foreach ($links as $link) {
			if(preg_match($_POST['regex'], $link) === 1){
				echo $link . "<br>";
			}
			ob_flush();
			flush();
		}
	};
	return $app->stream($stream);
});


$parser->get('/routes', function() use($app){
	foreach ($app['routes'] as $route) {
		echo $route->compile()->getTokens()[0][1] . '<br>';
	}
	return false;
});

$app->mount('/sp', $parser);
$app->run();