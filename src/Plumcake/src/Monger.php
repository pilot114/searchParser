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
        $this->dbh     = $m->$conf['db'];
        $this->debug   = new \stdClass();
        $this->engines = array_keys($conf['engines']);
        $this->conf    = $conf;
    }

    /*
     *      PROXIES
     * */
    public function addProxies($proxies)
    {
        $this->dbh->proxies->batchInsert($proxies);
    }
    public function getProxies()
    {
        $this->dbh->proxies->find();
    }
    public function getRandomProxy($limit)
    {
        $max = $this->dbh->proxies->count();
        $rand = rand(0, $max-$limit);
        return $this->dbh->proxies->find()->skip($rand)->limit($limit);
    }
    public function modifyProxies($proxies)
    {
        foreach ($proxies as $proxy) {
            $direct = ($proxy['status'] == 200) ? 1:-1;
            $this->dbh->proxies->update(
                ['_id' => $proxy['proxy']['_id']],
                [
                    '$inc' => ['respect' => $direct]
                ]
            );
        }
    }

    /*
     *      UNIQ
     * */
    public function updateUniqs()
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
        $docUniqLinks = [];
        foreach ($unicLinks as $unicLink => $count) {
            $docUniqLinks[] = [
                'link'  => $unicLink,
                'count' => $count
            ];
        }
        $this->dbh->uniq->batchInsert($docUniqLinks);
    }
    public function getRandUniq($count)
    {
        $max = $this->dbh->uniq->count();
        $rand = rand(0, $max-$count);
        return $this->dbh->uniq->find()
            ->skip($rand)
            ->limit($count);
    }
    public function getUniqCounters()
    {
        $ops = [
            [
                '$group' => [
                    '_id' => ['link' => '$l'],
                ]
            ]
        ];
        $engineCounters = [];
        foreach ($this->engines as $engine) {
            $result = $this->dbh->$engine->aggregate($ops)['result'];
            $engineCounters[$engine] = count($result);
        }
        return $engineCounters;
    }
    public function getUniqs()
    {
        return $this->dbh->uniq->find();
    }

    /*
     *      TASKS
     * */
    public function addTasks($tasks)
    {
        $this->dbh->tasks->batchInsert($tasks);
    }
    public function updateTask($task, $count, $newStatus)
    {
        foreach ($this->engines as $engine) {
            if(isset($task[$engine])){
                $curEngine = $engine;
            }
        }

        if ($newStatus == 'run') {
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    '$inc' => [$curEngine => $count]
                ]
            );
        } elseif ($newStatus == 'pause') {
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    $curEngine    => $task[$curEngine],
                    'query'       => $task['query'],
                    'status' 	  => 'pause'
                ]
            );
        } else { // newStatus == stop
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    '$inc' => [$curEngine => $count],
                    '$set' => ['query' => $task['query'], 'status' => 'stop'],
                ]
            );
        }
    }
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

    /*
     *      DORKS
     * */
    public function setDorkLinks($links)
    {
        $this->dbh->backlinks->batchInsert($links);
        $this->debug->dork = count($links);
        $this->debug->message = 'save dork links';
    }

    /*
     *      OTHER
     * */
    public function saveLinks($links, $engine)
    {
        $this->dbh->$engine->batchInsert($links);
        $this->debug->$engine = count($links);
        $this->debug->message = 'save links';
    }
    // save all debug info in current life
    public function saveDebug()
    {
        $this->dbh->debug->insert($this->debug);
        unset($this->debug->_id);
        return $this->debug;
    }
    public function dropCurDb()
    {
        return $this->dbh->drop();
    }
}