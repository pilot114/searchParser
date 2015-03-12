<?php

namespace Plumcake;


class Delivery
{
    private $monger;
    private $task;
    private $opts;
    private $proxies;

    public function __construct(Monger $monger, $task)
    {
        $this->monger = $monger;
        $this->task   = $task;
    }

    public function prepareProxies($count)
    {
        $cProxies = $this->monger->getRandomProxy($count);
        $proxies = array_values(iterator_to_array($cProxies));

        $optsTemplate = [
//            CURLOPT_VERBOSE           => true,
            CURLOPT_REFERER           => $this->task['query'],
            CURLOPT_RETURNTRANSFER    => 1,
            CURLOPT_FOLLOWLOCATION    => 1,
            CURLOPT_HEADER            => 1,
            CURLOPT_MAXREDIRS         => 10,
            CURLOPT_CONNECTTIMEOUT    => $count * 5,
            CURLOPT_USERAGENT         => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
        ];
        $opts = [];
        foreach ($proxies as $proxy) {
            $optsTemplate[CURLOPT_PROXY] = $proxy['proxy'];
            $opts[] = $optsTemplate;
        }
        $this->proxies = $proxies;
        $this->opts    = $opts;
    }

    public function run($cLinks, $limit = false)
    {
        $startTime = microtime(true);

        $mc = new Mcurl();
        $chs = $mc->addChannels($cLinks, $this->opts, $random=false);
        $result = [];
        foreach ($chs as $i => $channel) {
            $result[$i]['channel'] = $channel;
            $result[$i]['proxy'] = $this->proxies[$i]['proxy'];
            $result[$i]['hits'] = (isset($this->proxies[$i]['hits'])) ?: 0;
            $result[$i]['avtime'] = (isset($this->proxies[$i]['avtime'])) ?: false;
            $result[$i]['time'] = 999;
            $result[$i]['_id'] = $this->proxies[$i]['_id'];
            $result[$i]['status'] = 0;
        }

        $mc->run(function($headers, $body, $chinfo, $chres) use (&$result, &$suc, $mc, $startTime, $limit){

            // all unfinished channel time = 999
            if($limit){
                if(intval(microtime(true) - $startTime) > $limit){
                    $mc->stop();
                }
            }
            foreach ($result as $i => &$current) {
                if($current['channel'] == $chres){
                    $current['status'] = $chinfo['http_code'];
                    $current['url']    = $chinfo['url'];
                    if(intval($chinfo['total_time'])){
                        $current['time']   = intval($chinfo['total_time']);
                    }
                    if(!$current['avtime']){
                        $current['avtime'] = $current['time'];
                    }
                    break;
                }
            }
        });
        $mc->closeChannels();
        return $result;
    }
}