<?php

require '../vendor/autoload.php';
$config = include('../config.php');

$m = new MongoClient($config['connection']);
$dbh = $m->common;

function prepareCurls($url, $refer, $mh)
{
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT,
		"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36");
	curl_setopt($ch, CURLOPT_REFERER, $refer);
	curl_multi_add_handle($mh, $ch);
	return $ch;
}

function getArrayUrl($filename)
{
	$strings = [];
	$handle = fopen($filename, "r");
	if ($handle) {
	    while (($buffer = fgets($handle, 4096)) !== false) {
	        $strings[] = $buffer;
	    }
	    fclose($handle);
	}
	return $strings;
}


$time = time();


// $pattern = 'http://mamnonlongbien.com/index.php?language=vi&nv=statistics&op=allreferers&page='; ?
$pattern = 'http://mamnonlongbien.com/index.php?language=vi&nv=statistics&op=allreferers&page=';
$urls = [];
for ($i=0; $i < 5; $i++) {
	$url = $pattern . $i*50;
	$body = file_get_contents($url);
	echo $body; 
	preg_match_all("/google/", $body, $links);
	print_r($links);
	die();
}


// foreach ($urls[1] as $link) {
// 	$links[] = [
// 	'source' => "http://www.bebaldodente.it/?info=1",
// 	'time'   => $time,
// 	'link'   => $link,
// ];
// }
// if(!empty($links)){
// 	$dbh->simple->batchInsert($links);
// }
// die();


$mh = curl_multi_init();
$running = 0;
$channels = [];
$links = [];
$selector = "td.tableb span.smallfont a";



foreach ($urls as $url) {	
	$channels[] = prepareCurls($url, "http://polomolo.com", $mh, $channels);
}

do {
    $status = curl_multi_exec($mh, $running);

	if ($mhinfo = curl_multi_info_read($mh)) {
		$chinfo = curl_getinfo($mhinfo['handle']);
		$output = curl_multi_getcontent($mhinfo['handle']);
		$header_size = curl_getinfo($mhinfo['handle'], CURLINFO_HEADER_SIZE);
		$headers = substr($output, 0, $header_size);
		$body = substr($output, $header_size);

		$saw = new nokogiri($body);

		foreach ($saw->get($selector) as $link) {
			if(
				substr( $link['href'], 0, 4 ) === "http" &&
				strpos($link['href'], '.info') > 0
			  ){
				$links[] = [
					'source' => $chinfo['url'],
					'time'   => $time,
					'link'   => $link['href'],
				];
			}	
		}
	}	

    curl_multi_select($mh);
} while($status === CURLM_CALL_MULTI_PERFORM || $running > 0);

foreach($channels as $key => $channel) {
    curl_multi_remove_handle($mh, $channel);
}
curl_multi_close($mh);

print_r($links);
// die();
if(empty($links)){
	echo 'not links';
} else {
	$dbh->simple->batchInsert($links);
}
