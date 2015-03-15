<?php

use Plumcake\Monger;
use Plumcake\Delivery;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');
$m = new Monger($config);

// delivery settings
$col = 'uniqs';
$count = 100;
$limit = 50;

if ($col == 'uniqs') {
    $cLinks = $m->getRandUniq($count);
}
if ($col == 'backlink') {
    $cLinks = $m->getRandBacklinks($count);
}
$cLinks = array_map(function($el){
    return $el['link'];
}, iterator_to_array($cLinks));
$cLinks = array_values($cLinks);


$task = $m->findDeliveryTask();
if(!$task){
    die("Task not found\n");
}
$delivery = new Delivery($m, $task);

$debugAdded = [];
$debugAdded['query'] = $task['query'];
$debugAdded['urls'] = "\n";

$commonSuccess = 0;
$time_start = microtime(true);

while($commonSuccess < $count){

    $curSuccess = 0;
    $curFails = 0;
    $delivery->prepareProxies($count);
    $results = $delivery->run($cLinks, $limit);

    foreach($results as $result){
        if($result['status'] == 200){
            $debugAdded['urls'] .= parse_url($result['url'])['host'] . "\n";
            $curSuccess++;
        } else {
            $curFails++;
        }
    }

    // up proxies
    $m->modifyProxies($results);
    $commonSuccess += $curSuccess;
    echo 'Success: ' . $curSuccess . "\n";
    echo 'Fails: ' . $curFails . "\n";
}
$time_end = microtime(true);
$debugAdded['time'] = new \MongoDate(time());
$debugAdded['duration'] = intval($time_end - $time_start);

echo 'TOTAL: ' . $debugAdded['duration'] . "\n";

// up task
$task['count'] += $commonSuccess;
if($task['count'] >= $task['limit']){
    $task['status'] = 'stop';
}
$m->updateDeliveryTask($task);

$m->saveDebug($debugAdded);
die();