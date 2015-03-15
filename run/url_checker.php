<?php

use Plumcake\Mcurl;
use Plumcake\Monger;

require_once __DIR__.'/../vendor/autoload.php';
$config = include(__DIR__.'/../config.php');
$m = new Monger($config);

$count = 50;

$idsLinks = array_map(function($el){
    return $el['link'];
}, iterator_to_array($m->getRandUniq($count)));
$cLinks = array_values($idsLinks);

$opts = [
    CURLOPT_RETURNTRANSFER    => 1,
    CURLOPT_BINARYTRANSFER    => 1,
    CURLOPT_FOLLOWLOCATION    => 1,
    CURLOPT_HEADER            => 0,
    CURLOPT_TIMEOUT           => 60,
    CURLOPT_USERAGENT         => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
];

$mc = new Mcurl();
$chs = $mc->addChannels($cLinks, [$opts], $random=false);
$result = [];
$mc->run(function($headers, $body, $chinfo, $chres) use (&$result){
    echo $chinfo['url'] . "\n";
    echo $chinfo['http_code'] . "\n";
    if($chinfo['http_code'] == 200){
        $result[] = parse_url($chinfo['url'])['host'];
    } else {
       print_r($chinfo);
    }
});
$mc->closeChannels();

$fails = array_diff($cLinks, $result);
$res = [];
foreach ($fails as $fail) {
    foreach ($idsLinks as $key => $link) {
        if($fail == $link){
            $res[$key] = $link;
        }
    }
}
echo "\nRemove: " . count($res) . "\n";

//$m->removeUniqs(array_keys($res));
die();