<?php

use Plumcake\Monger;

class MongerTest extends PHPUnit_Framework_TestCase
{
    private $monger;

    private function setupLinks()
    {
        $time = new \MongoDate(time());
        $links = [
            [
                'q'  => 'blablabla',
                't'  => $time,
                'sl' => 'http://habrahabr.ru/test',
                'l'  => 'habrahabr.ru'
            ],
            [
                'q'  => 'blablabla',
                't'  => $time,
                'sl' => 'http://vk.com/test',
                'l'  => 'vk.com'
            ],
            [
                'q'  => 'blablabla',
                't'  => $time,
                'sl' => 'http://ru.wikipedia.org/test',
                'l'  => 'ru.wikipedia.org'
            ],
        ];
        foreach ($this->monger->engines as $engine) {
            $this->monger->saveLinks($links, $engine);
        }
    }

    public function setUp()
    {
        $conf = require('config.php');
        $conf['db'] = 'pt';
        $this->monger = new Monger($conf);
    }
    public function tearDown()
    {
        $this->monger->dropCurDb();
    }


    public function testTask()
    {
        $queries = ['hello world'];
        $tasks = [];
        foreach ($queries as $query) {
            foreach ($this->monger->engines as $engine) {
                $task['query'] = $query;
                $task['status'] = 'run';
                $task[$engine] = 0;
                $tasks[] = $task;
                unset($task);
            }
        }
        $this->monger->addTasks($tasks);
        $tasks = $this->monger->findTasks();

        $trueCount = count($this->monger->engines);
        $this->assertEquals($trueCount, count($tasks));

        // imitation worker
        $cur = 0;
        foreach ($tasks as $engine => $task) {
            if($cur == 0){
                $this->monger->updateTask($task, 0, 'pause');
                $cur++;
            } elseif($cur == 1){
                $this->monger->updateTask($task, 5, 'stop');
                $cur++;
            } else {
                $this->monger->updateTask($task, 10, 'run');
            }
        }

        // only 1 "run" for each engine
        $tasks = $this->monger->findTasks();
        $this->assertEquals($trueCount-2, count($tasks));

        $tasks = $this->monger->getUniqTasks();
        $this->assertEquals(count($queries), count($tasks));
    }

    public function testProxy()
    {
        $proxies = [
            ['proxy' => '110.4.24.176:80', 'respect' => 10],
            ['proxy' => '61.53.143.179:80', 'respect' => 10]
        ];
        $count = count($proxies);

        $this->monger->addProxies($proxies);
        $proxiesFromDb = $this->monger->getRandomProxy($count);
        $this->assertEquals($count, $proxiesFromDb->count());

        $generateDocs = [];
        foreach ($proxiesFromDb as $proxy) {
            $doc = [];
            $doc['proxy'] = $proxy;
            $doc['status'] = 200;
            $generateDocs[] = $doc;
        }
        $this->monger->modifyProxies($generateDocs);
        $proxiesFromDb = $this->monger->getRandomProxy($count);
        $this->assertEquals($count, $proxiesFromDb->count());
    }

    public function testUniq()
    {
        $countLinks = 3;
        $this->setupLinks();
        $this->monger->updateUniqs();
        $counters = $this->monger->getUniqCounters();
        foreach ($counters as $counter) {
            $this->assertEquals($counter, $countLinks);
        }
        $uniqs = $this->monger->getUniqs();
        $this->assertEquals($uniqs->count(), $countLinks);

        $debug = $this->monger->saveDebug();
        foreach ($this->monger->engines as $engine) {
            $this->assertEquals($debug->$engine, $countLinks);
        }
    }
}