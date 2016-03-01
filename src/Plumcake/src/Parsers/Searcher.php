<?php

namespace Plumcake\Parsers;

use nokogiri;
use Plumcake\Monger;
use Plumcake\GuzzleWrapper;

class Searcher
{
	private $monger;
	private $conf;
	private $links;
	private $gw;
	private $info = [];

	function __construct($config, Monger $monger)
	{
		$this->conf   = $config;
		$this->monger = $monger;

		$options  = ['timeout' => 10, 'pool' => 4];
		$headers = [
			'Referer'    => 'https://www.google.ru',
			'User-Agent' => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
		];
		$this->gw = new GuzzleWrapper($headers, $options);
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
			$this->info[$engineName] = [
				'query'   => $task['query'],
				'shift'   => $task[$engineName]
			];
			$urls[] = $searchUrl;
		}
		$this->gw->addRequests($urls);
	}

	// return links group by engines
	public function now()
	{
		$result = $this->gw->run();

		foreach ($result['complete'] as $complete) {

			// detect engine
			$url = $complete->getRequest()->getUrl();
			foreach ($this->conf['engines'] as $name => $c) {
				if(strpos($url, $name) !== false){
					$curEngName = $name;
					$curInfo = $this->info[$curEngName];
				}
			}

			// parse
			$body = (string)$complete->getResponse()->getBody();
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
						'q'	=> $curInfo['query'],
						'sl' => $link['href'],
						'l' => parse_url($link['href'])['host']
					];
				} elseif( substr( $link['href'], 0, 7 ) === '/url?q=' ){
					$query = parse_url($link['href'])['query'];
					parse_str($query, $args);

					$this->links[$curEngName][] = [
						'q' => $curInfo['query'],
						'sl' => $args['q'],
						'l' => parse_url($args['q'])['host']
					];
				}
			}

			// add debug for fails
			if (count($this->links[$curEngName]) == 0) {
				$errorStack[$curEngName] = [
					'url' => $curInfo['query'],
				];
			}
		}
		foreach ($result['error'] as $error) {
			$url = $error->getRequest()->getUrl();
			foreach ($this->conf['engines'] as $name => $c) {
				if(strpos($url, $name) !== false){
					$curEngName = $name;
					$curInfo = $this->info[$curEngName];
				}
			}
			$errorStack['messages'][$curEngName] = $error->getException->getMessage();
		}

		if(!empty($errorStack)){
			$this->monger->debug->error_stack = $errorStack;
		}
		return $this->links;
	}

	public function after($queries, $engines, $type='common')
	{
		$tasks = [];
		foreach ($queries as $query) {
			foreach ($engines as $engine) {
				$task['query'] = $query;
				$task['type'] = $type;
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