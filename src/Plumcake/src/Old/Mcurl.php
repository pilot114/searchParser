<?php

namespace Plumcake;

class Mcurl
{
    private $mh;
    private $chs = [];
    private $stop = false;

    function __construct()
    {
        $this->mh = curl_multi_init();
    }

    public function addChannels($urls, $opts, $random=false)
    {
        $countOpts = count($opts);
        foreach ($urls as $i => $url) {
            $ch = curl_init($url);
            if(!$ch){
                throw new \Exception('Не удалось создать curl канал');
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            if($random){
                curl_setopt_array($ch, $opts[array_rand($opts)]);
            } else {
                $curOpts = $opts[$i%$countOpts];
                curl_setopt_array($ch, $curOpts);
            }
            if($eCode = curl_multi_add_handle($this->mh, $ch)){
                throw new \Exception('Не удалось добавить канал. Код ошибки: ' . $eCode);
            }
            $this->chs[] = $ch;
        }
        return $this->chs;
    }

    public function run($cb)
    {
        $running = null;
        do {
            $status = curl_multi_exec($this->mh, $running);
            if (curl_multi_select($this->mh) == -1) {
                usleep(1);
            }
            if ($mhinfo = curl_multi_info_read($this->mh)) {
                echo $running;
                $chinfo = curl_getinfo($mhinfo['handle']);
                $output = curl_multi_getcontent($mhinfo['handle']);
                $header_size = curl_getinfo($mhinfo['handle'], CURLINFO_HEADER_SIZE);
                $headers = substr($output, 0, $header_size);
                $body = substr($output, $header_size);
                $cb($headers, $body, $chinfo, $mhinfo['handle']);
            }
        } while(($status === CURLM_CALL_MULTI_PERFORM || $running > 0) && !$this->stop);
    }

    public function stop(){
        $this->stop = true;
    }

    public function closeChannels()
    {
        foreach($this->chs as $ch) {
            curl_multi_remove_handle($this->mh, $ch);
        }
        curl_multi_close($this->mh);
    }
}