<?php

use Plumcake\Monger as Monger;
use Plumcake\Mcurl;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');

$m = new Monger($config);

// delivery settings
$col = 'uniqs';
$count = 10;

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

$cProxies = $m->getRandomProxy($count);
$proxies = array_values(iterator_to_array($cProxies));



// DELIVERY
$optsTemplate = [
    CURLOPT_REFERER           => $task['query'],
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
$chs = $mc->addChannels($cLinks, $opts, $random=false);
$temp = [];
foreach ($chs as $i => $channel) {
    $temp[$i]['channel'] = $channel;
    $temp[$i]['proxy'] = $proxies[$i]['proxy'];
    $temp[$i]['_id'] = $proxies[$i]['_id'];
    $temp[$i]['status'] = 0;
}

$mc->run(function($headers, $body, $chinfo, $chres) use (&$temp){
    foreach ($temp as $i => &$current) {
        if($current['channel'] == $chres){
            $current['status'] = $chinfo['http_code'];
            break;
        }
    }
});
$mc->closeChannels();

// up proxies
$m->modifyProxies($temp);

// up task
$countSuccess = 0;
foreach($temp as $proxy){
    if($proxy['status'] == 200){
        $countSuccess++;
    }
}
$task['count'] += $countSuccess;
if($task['count'] >= $task['limit']){
    $task['status'] = 'stop';
}
$m->updateDeliveryTask($task);
