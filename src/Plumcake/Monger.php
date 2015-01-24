<?php

namespace Plumcake;


use MongoClient;

class Monger
{
    private $dbh;
    private $conf;

    public $engines;
    public $debug;

    function __construct($conf)
    {
        $m = new MongoClient($conf['connection']);
        $this->dbh     = $m->plumcake;
        $this->debug   = new \stdClass();
        $this->engines = array_keys($conf['engines']);
        $this->conf    = $conf;
    }

    public function addTasks($tasks)
    {
        $this->dbh->tasks->batchInsert($tasks);
    }

    // return 1 most important task for each engine
    public function findTasks()
    {
        $tasks = [];
        foreach ($this->engines as $engine) {
            $task = $this->dbh->tasks->find(
                [
                    'status' => 'run',
                    $engine =>['$exists'=>true]
                ])->sort([$engine => 1])
                ->getNext();
            if($task){
                $tasks[$engine] = $task;
            }
        }
        return $tasks;
    }

    // save links, counting in debug
    public function saveLinks($links, $engine)
    {
        $count = 0;
        foreach ($links as $link) {
            $col = $this->dbh->$engine;
            $col->insert($link);
            $count++;
        }
        $this->debug->$engine = $count;
    }

    public function updateTask($task, $count, $status)
    {
        foreach ($this->engines as $engine) {
            if(isset($task[$engine])){
                $curEngine = $engine;
            }
        }

        if ($status == 'run') {
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    '$inc' => [$curEngine => $count]
                ]
            );
        } elseif ($status == 'pause') {
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    $curEngine    => $task[$curEngine],
                    'query'       => $task['query'],
                    'status' 	  => 'pause'
                ]
            );
        } else { // status == stop
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    '$inc' => [$curEngine => $count],
                    '$set' => ['query' => $task['query'], 'status' => 'stop'],
                ]
            );
        }
    }



    // save all debug info in current life
    public function saveDebug()
    {
        $this->dbh->debug->insert($this->debug);
        unset($this->debug->_id);
        return $this->debug;
    }

    public function getUniqs()
    {
        $ops = [
            [
                '$group' => [
                    '_id' => ['link' => '$l'],
                ]
            ]
        ];
        $unicLinks = [];
        $engineCounters = [];
        foreach ($this->engines as $engine) {
            $result = $this->dbh->$engine->aggregate($ops)['result'];
            foreach($result as $link){
                $url = $link['_id']['link'];
                @$unicLinks[$url]++;
                if($unicLinks[$url] == 1){
                    @$engineCounters[$engine]++;
                }
            }
        }
        return [$unicLinks, $engineCounters];
    }

    public function getUniqTasks()
    {
        $ops = [
            [
                '$group' => [
                    '_id' => ['query' => '$query'],
                ]
            ]
        ];
        $unicLinks = [];
        $result = $this->dbh->tasks->aggregate($ops)['result'];
        foreach($result as $query){
            $url = $query['_id']['query'];
            @$unicLinks[$url]++;
        }
        return array_keys($unicLinks);
    }

    public function getUniqCursor()
    {
        return $this->dbh->plumcake->uniq->find();
    }
}