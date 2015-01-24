<?php

namespace Plumcake;

use nokogiri;

class Searcher
{	
	private $channels = [];
	private $monger;
	private $conf;
	private $initTime;
	private $mcHandler;
	private $links;

	function __construct($config, Monger $monger)
	{
		$this->conf   = $config;
		$this->monger = $monger;
	}

	private function shiftesDenormalize($engine, $shift)
	{
		if(!$engine[2]){
			$shift /= 10;
		}
		$searchUrl = '&' . $engine[0]. '=' . ($engine[1] + $shift);
		return $searchUrl;
	}

	private function prepareCurls($url, $refer, $mh)
	{
		// set CURLOPT_HTTPPROXYTUNNEL for use proxy

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_REFERER, $refer);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	// for return data
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // for redirects
		curl_setopt($ch, CURLOPT_VERBOSE, 0);			// adding info
		curl_setopt($ch, CURLOPT_HEADER, 1);			// headers
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_USERAGENT,
			"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36");
		curl_multi_add_handle($mh, $ch);
		return $ch;
	}

	public function initChannels($tasks)
	{
		foreach ($tasks as $name => $task) {
			if(!isset($this->conf['engines'][$name])) {
				die('Engine "' . $name . '" not found');
			} else {
				$this->links[$name] = [];
			}
		}

		$this->mcHandler = curl_multi_init();
		$this->initTime = time();

		foreach ($tasks as $engineName => $task) {
			$engine = $this->conf['engines'][$engineName];
			$searchUrl = $engine['url'] . '?' . $engine['query']. '=' . $task['query'];
			if($task[$engineName] > 0){
				$searchUrl.= $this->shiftesDenormalize($engine['page'], $task[$engineName]);
			}
			if(isset($engine['num'])){
				$searchUrl.= '&' . $engine['num'];
			}
			$this->channels[] = [
				'channel' => $this->prepareCurls($searchUrl, 'https://www.google.ru', $this->mcHandler),
				'engine'  => $engineName,
				'query'   => $task['query'],
				'shift'   => $task[$engineName]
			];
		}
	}

	public function closeChannels()
	{
		foreach($this->channels as $key => $channel) {
		    curl_multi_remove_handle($this->mcHandler, $channel['channel']);
		}
		curl_multi_close($this->mcHandler);
	}

	// return links group by engines
	public function now()
	{
		$errorStack = [];
		$curChannel = [];
		$running = 0;

		do {
		    $status = curl_multi_exec($this->mcHandler, $running);
			if ($mhinfo = curl_multi_info_read($this->mcHandler)) {
				$chinfo = curl_getinfo($mhinfo['handle']);
				$output = curl_multi_getcontent($mhinfo['handle']);

				foreach ($this->channels as $key => $channel) {
					if($channel['channel'] == $mhinfo['handle']){
						$curChannel = $channel;
					}
				}

				$header_size = curl_getinfo($mhinfo['handle'], CURLINFO_HEADER_SIZE);
				$headers = substr($output, 0, $header_size);
				$body = substr($output, $header_size);

				$saw = new nokogiri($body);
				$curEngName = $curChannel['engine'];
				$curQuery   = trim($curChannel['query']);
				$curShift   = $curChannel['shift'];

				foreach ($saw->get($this->conf['engines'][$curEngName]['selector']) as $link) {

					$host = parse_url($link['href']);

					// TODO specific-engine callback for check url

					// removing fake links (adver, images, video etc)
					if(
						substr( $link['href'], 0, 4 ) === "http" &&
						strpos($link['href'], 'bz.yandex.ru') === false &&
						strpos($link['href'], 'my.mail.ru') === false
					  ){
						$this->links[$curEngName][] = [
							'q'	=> $curQuery,
							't' => new \MongoDate($this->initTime),
							'sl' => $link['href'],
							'l' => $host['host']
						];
					} elseif( substr( $link['href'], 0, 7 ) === '/url?q=' ){
						$query = parse_url($link['href'])['query'];
						$link['href'] = parse_str($query, $args);

						$this->links[$curEngName][] = [
							'q' => $curQuery,
							't' => new \MongoDate($this->initTime),
							'sl' => $link['href'],
							'l' => $host['host']
						];
					}
				}

				if (count($this->links[$curEngName]) == 0) {
					$errorStack[$curEngName] = $chinfo;
				}
			}

		    curl_multi_select($this->mcHandler);
		} while($status === CURLM_CALL_MULTI_PERFORM || $running > 0);

		if(!empty($errorStack)){
			$this->monger->debug->error_stack = $errorStack;
		}

		return $this->links;
	}

	public function after($queries, $engines)
	{
		$tasks = [];
		foreach ($queries as $query) {
			foreach ($engines as $engine) {
				$task['query'] = $query;
				$task['status'] = 'run';
				$task[$engine] = 0;
				$tasks[] = $task;
				unset($task);
			}
		}
		$this->monger->addTasks($tasks);
		$this->monger->debug->addTasks = count($tasks);
	}
}