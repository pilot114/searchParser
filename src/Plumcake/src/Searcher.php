<?php

namespace Plumcake;

use nokogiri;

class Searcher
{
	private $monger;
	private $conf;
	private $initTime;
	private $links;
	private $mcurl;
	private $channels = [];

	function __construct($config, Monger $monger)
	{
		$this->conf   = $config;
		$this->monger = $monger;
		$this->mcurl = new Mcurl();
	}

	private function shiftesDenormalize($engine, $shift)
	{
		if(!$engine[2]){
			$shift /= 10;
		}
		$searchUrl = '&' . $engine[0]. '=' . ($engine[1] + $shift);
		return $searchUrl;
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

		$opt = [
			CURLOPT_REFERER 		=> 'https://www.google.ru',
			CURLOPT_RETURNTRANSFER	=> 1,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_VERBOSE			=> 0,
			CURLOPT_HEADER			=> 1,
			CURLOPT_CONNECTTIMEOUT	=> 10,
			CURLOPT_USERAGENT		=>
				"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
		];

		$urls = [];
		foreach ($tasks as $engineName => $task) {
			$engine = $this->conf['engines'][$engineName];
			$searchUrl = $engine['url'] . '?' . $engine['query']. '=' . urlencode($task['query']);
			if($task[$engineName] > 0){
				$searchUrl.= $this->shiftesDenormalize($engine['page'], $task[$engineName]);
			}
			if(isset($engine['num'])){
				$searchUrl.= '&' . $engine['num'];
			}
			$this->channels[] = [
				'engine'  => $engineName,
				'query'   => $task['query'],
				'shift'   => $task[$engineName]
			];
			$urls[] = $searchUrl;
		}
		$chs = $this->mcurl->addChannels($urls, [$opt], $random=false);
		foreach ($this->channels as $i => $channel) {
			$this->channels[$i]['channel'] = $chs[$i];
		}
	}

	// return links group by engines
	public function now()
	{
		$errorStack = [];
		$result = [];
		$this->initTime = time();
		$this->mcurl->run(function($headers, $body, $chinfo, $chres) use (&$result){

			// detect current curl channel
			$curChannel = [];
			foreach ($this->channels as $channel) {
				if($channel['channel'] === $chres){
					$curChannel = $channel;
				}
			}
			$curEngName = $curChannel['engine'];
			$curQuery   = trim($curChannel['query']);

			// parse
			$saw = new nokogiri($body);
			foreach ($saw->get($this->conf['engines'][$curEngName]['selector']) as $link) {

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
						'l' => parse_url($link['href'])
					];
				} elseif( substr( $link['href'], 0, 7 ) === '/url?q=' ){
					$query = parse_url($link['href'])['query'];
					parse_str($query, $args);

					$this->links[$curEngName][] = [
						'q' => $curQuery,
						't' => new \MongoDate($this->initTime),
						'sl' => $link['href'],
						'l' => $args['q']
					];
				}
			}

			if (count($this->links[$curEngName]) == 0) {
				$errorStack[$curEngName] = [
					'url' 		 => $chinfo['url'],
					'http_code'  => $chinfo['http_code'],
					'total_time' => $chinfo['total_time'],
				];
			}

		});
		$this->mcurl->closeChannels();

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