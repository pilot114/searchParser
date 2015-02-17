<?php

use Plumcake\Mcurl;

class McurlTest extends \PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $urls = [
            'http://habrahabr.ru',
            'http://vk.com',
            'http://ru.wikipedia.org',
        ];
        $proxies = [
            '110.4.24.176:80',
            '61.53.143.179:80'
        ];

        $optsTemplate = [
//            CURLOPT_VERBOSE           => 1,
            CURLOPT_REFERER           => 'test.com',
            CURLOPT_RETURNTRANSFER    => 1,
            CURLOPT_FOLLOWLOCATION    => 1,
            CURLOPT_HEADER            => 1,
            CURLOPT_CONNECTTIMEOUT    => 5,
            CURLOPT_USERAGENT         => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
        ];
        $opts = [];

        foreach ($proxies as $proxy) {
            $optsTemplate[CURLOPT_PROXY] = $proxy;
            $opts[] = $optsTemplate;
        }

        $mc = new Mcurl();
        $chs = $mc->addChannels($urls, $opts, $random=false);
        $result = [];
        $mc->run(function($headers, $body, $chinfo, $chres) use (&$result){
            $result[] = $chinfo['http_code'];
        });
        $mc->closeChannels();

        $this->assertEquals(count($chs), count($result));
        foreach ($result as $status) {
            $this->assertTrue(in_array($status, [200, 403]));
        }
    }
}