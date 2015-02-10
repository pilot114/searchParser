<?php

use Plumcake\Monger;
use Plumcake\Searcher;

class SearcherTest extends \PHPUnit_Framework_TestCase
{
    private $monger;
    private $conf;
    private $tasks;

    private function setupTasks()
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
        return $this->monger->findTasks();
    }

    public function setUp()
    {
        $conf = require('config.php');
        $conf['db'] = 'pt';

        $this->monger = new Monger($conf);
        $this->conf   = $conf;
        $this->tasks  = $this->setupTasks();
    }
    public function tearDown()
    {
        $this->monger->dropCurDb();
    }

    public function testSimple()
    {
        $searcher = new Searcher($this->conf, $this->monger);
        $searcher->initChannels($this->tasks);
        $commonLinks = $searcher->now();
        $this->assertEquals(count($commonLinks), count($this->tasks));
    }
}