<?php

$example = file_get_contents('./index.php');
$c = curl_init('http://markup.su/api/highlighter');
curl_setopt_array($c, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST 		   => true,
    CURLOPT_POSTFIELDS 	   => 'language=PHP&theme=Sunburst&source=' . urlencode($example)
]);
$response = curl_exec($c);
$info = curl_getinfo($c);
curl_close($c);

if ($info['http_code'] == 200 && $info['content_type'] == 'text/html') {
    return $response;
} else {
    return 'Error';
}