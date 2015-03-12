<?php

use Plumcake\Monger;
use Plumcake\Delivery;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');
$m = new Monger($config);

// delivery settings
$col = 'uniqs';
$count = 100;

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


// TODO: other worker
// filter urls
// $mc->checkUrls & $m->removeInvalidurls

//$opts = [
//    CURLOPT_RETURNTRANSFER    => 1,
//    CURLOPT_FOLLOWLOCATION    => 1,
//    CURLOPT_HEADER            => 1,
//    CURLOPT_CONNECTTIMEOUT    => $count * 5, // ВАЖНО ДЛЯ КОРРЕКТНОГО ЧЕКА
//    CURLOPT_USERAGENT         => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
//];
//
//$mc = new Mcurl();
//$chs = $mc->addChannels($cLinks, [$opts], $random=false);
//$result = [];
//$mc->run(function($headers, $body, $chinfo, $chres) use (&$result){
//    if($chinfo['http_code'] == 200){
//        $result[] = parse_url($chinfo['url'])['host'];
//    }
//});
//$mc->closeChannels();
//
//
//$fails = array_diff($cLinks, $result);
//print_r($cLinks);
//print_r($result);
//print_r($fails);
//
//$cLinks = $result;
//echo count($cLinks);
//die();


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
    $delivery->prepareProxies($count);
    $results = $delivery->run($cLinks, $limit = 30);

    foreach($results as $result){
        if($result['status'] == 200){
            $debugAdded['urls'] .= parse_url($result['url'])['host'] . "\n";
            $curSuccess++;
        }
    }

    // up proxies
    $m->modifyProxies($results);
    $commonSuccess += $curSuccess;
    echo $curSuccess . "\n";
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