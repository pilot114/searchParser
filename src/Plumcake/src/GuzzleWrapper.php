<?php

/*
BASIC USAGE:

$options = ['timeout' => 10, 'pool' => 10];

// init
$gw = new GuzzleWrapper([$defaultHeaders, $options]);
// prepare requests
$gw->addRequests($urls, [$headers, $proxies]);
$result = $gw->run();

=>
['time', 'complete', 'error']

 */



namespace Plumcake;

use GuzzleHttp\Client;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Pool;

class GuzzleWrapper
{
    private $client;
    private $requests = [];
    private $timeout;
    private $pool;

    // set default headers
    public function __construct(array $headers = [], array $options = [])
    {
        $this->timeout = (isset($options['timeout'])) ? $options['timeout'] : 10;
        $this->pool = (isset($options['pool'])) ? $options['pool'] : 30;

        $defaults = [
            'timeout' => $this->timeout,
            'debug' => false
        ];
        if(!empty($headers)){
            $defaults['headers'] = $headers;
        }

        $this->client = new Client([
            'defaults' => $defaults
        ]);
    }

    // WARNING: count urls === count proxies
    public function addRequests(array $urls, array $headers = [], array $proxies = [])
    {
        foreach ($urls as $index => $url) {
            if(!empty($proxies)){
                $request = $this->client->createRequest('GET', $url, [
                    'proxy' => $proxies[$index]
                ]);
            } else {
                $request = $this->client->createRequest('GET', $url);
            }
            if(!empty($headers)){
                $request->setHeaders($headers);
            }
            $this->requests[] = $request;
        }
    }

    public function run()
    {
        $complete = [];
        $error = [];
        $time_start = microtime(true);
        Pool::send($this->client, $this->requests, [
            'complete' => function (CompleteEvent $event) use (&$complete){
                echo '+';
                $complete[] = $event;
            },
            'error' => function (ErrorEvent $event) use (&$error){
                echo '-';
                $error[] = $event;
            },
            'pool_size' => $this->pool
        ]);
        $time_end = microtime(true);
        $result = [
            'time'       => intval($time_end - $time_start),
            'complete'   => $complete,
            'error'      => $error
        ];
        return $result;
    }
}