<?php

use Plumcake\Mcurl;
use Plumcake\Monger;
use Plumcake\Searcher as Searcher;

require_once __DIR__.'/../vendor/autoload.php';
$config = include('../config.php');

$app = new Silex\Application();
$app['debug'] = $config['debug'];

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../logs/requests.log',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->boot();



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
	$counters = $m->getUniqCounters();
	echo '<h2>По поисковикам (без учета пересечений)</h2>';
	foreach ($counters as $engine => $counter) {
		echo '<h3>' . $engine . ': ' . $counter . '</h3>';
	}
	echo '<h1>Уникальных доменов: ' . $m->getUniqs()->count() . '</h1>';

	return false;
});



$parser->get('/regex', function() use($app){
	return file_get_contents('regex.html');
});
$parser->post('/regex', function() use($app, $config){

	if(isset($_POST['db'])){
		$m = new Monger($config);
		$counters = $m->getUniqCounters();
		$links = $m->getUniqs();
	} else {
		$links = array_map('trim', explode("\n", $_POST['links']));
	}
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

$parser->get('/refs', function() use($app){
	return file_get_contents('refs.html');
});
$parser->post('/refs', function() use($app, $config){
	$refer = $_POST['refer'];
	$count = $_POST['count'];

	$m = new Monger($config);
	// its mongo docs
	$links   = $m->getRandUniq($count);
	$proxies = $m->getRandomProxy($count);
	foreach ($links as $i => $link) {
		$links[$i] = $link['domain'];
	}


	$optsTemplate = [
		CURLOPT_REFERER           => $refer,
		CURLOPT_RETURNTRANSFER    => 1,
		CURLOPT_FOLLOWLOCATION    => 1,
		CURLOPT_HEADER            => 1,
		CURLOPT_CONNECTTIMEOUT    => 5,
		CURLOPT_USERAGENT         => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
	];
	$opts = [];
	foreach ($proxies as $proxy) {
		$optsTemplate[CURLOPT_PROXY] = $proxy['proxy'];
		$opts[] = $optsTemplate;
	}
	$mc = new Mcurl();
	$chs = $mc->addChannels($links, $opts, $random=false);
	$temp = [];
	foreach ($chs as $i => $channel) {
		$temp['channel'] = $channel;
		$temp['proxy'] = $proxies[$i];
	}

	$mc->run(function($headers, $body, $chinfo, $chres) use (&$result, &$temp){
		foreach ($temp as $i => $current) {
			if($current['channel'] == $chres){
				$current['status'] = $chinfo['http_code'];
				unset($temp[$i]['channel']);
				break;
			}
		}
	});
	$mc->closeChannels();

	$m->modifyProxies($temp);
	return false;
});

$parser->get('/proxy', function() use($app){
	return file_get_contents('proxy.html');
});
$parser->post('/proxy', function() use($app, $config){
	$proxies = array_map('trim', explode("\n", $_POST['proxy']));
	$proxies = array_filter($proxies); // remove empty string
	foreach ($proxies as $i => $proxy) {
		$proxies[$i] = [
			'proxy'   => $proxy,
			'respect' => 10,
		];
	}
	$m = new Monger($config);
	if(!empty($proxies)){
		$m->addProxies($proxies);
		echo 'Добавлено прокси:' . count($proxies) . '<br>';
	}
	foreach ($m->getProxies() as $proxy) {
		echo $proxy['proxy'] . '<br>';
	}
	return false;
});


$parser->get('/bl', function() use($app){
	return file_get_contents('backlinks.html');
});
$parser->post('/bl', function() use($app, $config){
	return false;
});



//		DEBUG ROUTES
$parser->get('/routes', function() use($app){
	$routes = [];
	foreach ($app['routes'] as $route) {
		$name = $route->compile()->getTokens()[0][1];
		$routes[$name] = null;
	}
	foreach ($routes as $name => $route) {
		echo $name . '<br>';
	}
	return false;
});

$parser->before(function() use ($app, $config) {
	if(!$app['debug']){
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			header('WWW-Authenticate: Basic realm=SP');
			return $app->json(array('Message' => 'Not Authorised'), 401);
		} else {
			$users = $config['users'];
			if($users[$_SERVER['PHP_AUTH_USER']] !== $_SERVER['PHP_AUTH_PW']) {
				header('WWW-Authenticate: Basic realm=SP');
				return $app->json(array('Message' => 'Not Authorised'), 401);
			}
		}
	}
});
$app->mount('/sp', $parser);
$app->run();