<?php

/*

create Delivery!
1. conf
2. get data
3. run
4. up data

 * */

use Plumcake\GuzzleWrapper;
use Plumcake\Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include __DIR__.'/../config.php';
$m = new Monger($config);

// delivery settings
$col = 'uniqs';
$countDelivery = 200;
$options = ['timeout' => 30, 'pool' => 50];

if ($col == 'uniqs') {
    $cLinks = $m->getRandUniq($countDelivery);
}
if ($col == 'backlink') {
    $cLinks = $m->getRandBacklinks($countDelivery);
}

$cLinks = array_map(function($el){
    return $el['link'];
}, iterator_to_array($cLinks));
$cLinks = array_values($cLinks);

$task = $m->findDeliveryTask();
if(!$task){
    die("Task not found\n");
}

$cProxies = $m->getRandomProxy($countDelivery);
$cProxies = array_values(iterator_to_array($cProxies));
$proxies = array_column($cProxies, 'proxy');

// delivery
$headers = [
    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
    'Referer'    => $task['query']
];
$gw = new GuzzleWrapper($headers, $options);

$gw->addRequests($cLinks, [], $proxies);
$result = $gw->run();

$countComplete = count($result['complete']);
$countError    = count($result['error']);

$debugAdded = [];
$debugAdded['meta'] = array_merge(
    $options,
    [ 'complete' => $countComplete, 'error' => $countError]
);

$debugAdded['query'] = $task['query'];
$debugAdded['type'] = 'delivery';
$debugAdded['complete'] = [];
$debugAdded['errors'] = [];
$debugAdded['time'] = new \MongoDate(time());
$debugAdded['duration'] = $result['time'];

echo 'Time: ' . $result['time'] . "\n";
echo 'Complete: ' . $countComplete . "\n";
echo 'Error: ' . $countError . "\n";

foreach ($result['complete'] as $s) {
    $debugAdded['complete'][] = $s->getResponse()->getEffectiveUrl();
}

foreach ($result['error'] as $f) {
    $url =     $f->getRequest()->getUrl();
    $message = $f->getException()->getMessage();
    $debugAdded['errors'][] = $url . ': ' . $message;
}

// up task
$task['count'] += $countComplete;
if($task['count'] >= $task['limit']){
    $task['status'] = 'stop';
}
$m->updateDeliveryTask($task);

// save debug
$m->saveDebug($debugAdded);