<?php

namespace Plumcake;


use MongoClient;
use MongoDate;
use MongoId;

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

            // for proxy, exceeded limit
            if(!isset($result['time'])){
                $query = [
                    '$inc' => [
                        'ft' => 1,
                        'hits' => 1,
                    ]
                ];
            } else {
                $newAvTime = ($proxy['avtime']*$proxy['hits']+$proxy['time']) / ($proxy['hits']+1);
                if($direct == 1){
                    $query = [
                        '$set' => [
                            'ft' => 0,
                            'avtime' => $newAvTime
                        ],
                        '$inc' => [
                            'respect' => $direct,
                            'hits' => 1,
                        ]
                    ];
                } else {
                    $query = [
                        '$set' => [
                            'avtime' => $newAvTime
                        ],
                        '$inc' => [
                            'respect' => $direct,
                            'ft' => 1,
                            'hits' => 1,
                        ]
                    ];
                }
            }

            // execute
            $this->dbh->proxies->update(
                ['_id' => $proxy['_id']],
                $query
            );
        }
    }

    /*
     *      UNIQ
     * */
    public function updateUniqs($start, $end, $debug)
    {
        $unicLinks = [];
        $engineCounters = [];
        foreach ($this->engines as $engine) {
            $result = $this->aggregateByDate($start, $end, $engine, $debug);
            foreach($result as $link){
                $url = $link['_id']['link'];
                @$unicLinks[$url]++;
                if($unicLinks[$url] == 1){
                    @$engineCounters[$engine]++;
                }
            }
        }

        if($debug){
            echo "Aggregates finish. common unics: " . count($unicLinks) . "\n";
            echo "Prepare uniqs...\n";
        }

        $docUniqLinks = [];
        foreach ($unicLinks as $unicLink => $count) {
            $docUniqLinks[] = [
                'link'  => $unicLink,
                'count' => $count
            ];
        }

        if($debug){
            echo "Save uniqs...\n";
        }

        foreach ($docUniqLinks as $doc) {
            $this->dbh->uniq->update(
                [
                    'link' => $doc['link']
                ],
                [
                    '$set' => [
                        'link' => $doc['link'],
                    ],
                    '$inc' => [
                        'count' => $doc['count'],
                    ]
                ],
                [
                    'upsert' => true,
                ]
            );
        }
        if($debug){
            echo "Complete!\n";
        }
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
    public function removeUniqs($ids)
    {
        foreach ($ids as $id) {
            $this->dbh->uniq->remove(['_id' => new MongoId($id)]);
        }
    }


    /*
     *      TASKS
     * */
    public function addTasks($tasks)
    {
        $this->dbh->tasks->batchInsert($tasks);
    }

    public function updateDeliveryTask($task)
    {
        $this->dbh->tasks->update(
            ['_id' => $task['_id']],
            [
                '$set' => [
                    'status' => $task['status'],
                    'count' => $task['count'],
                ]
            ]
        );
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
                    '$set' => [
                        'query'  => $task['query'],
                        'status' => 'pause'
                    ]
                ]
            );
        } else { // newStatus == stop
            $this->dbh->tasks->update(
                ['_id' => $task['_id']],
                [
                    '$inc' => [$curEngine => $count],
                    '$set' => [
                        'query' => $task['query'],
                        'status' => 'stop'
                    ],
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
                    'type'   => 'common',
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
    public function findBacklinkTask()
    {
        return $this->dbh->tasks->findOne([
            'type'   => 'backlink',
            'status' => 'run'
        ]);
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
     *      BACKLINKS
     * */
    public function saveBackinks($links)
    {
        $this->dbh->backlinks->batchInsert($links);
        $this->debug->backlinks = count($links);
        $this->debug->message = 'save backlinks';
    }
    public function getBackinks()
    {
        return $this->dbh->backlinks->find();
    }
    public function getRandBacklinks($count)
    {
        $max = $this->dbh->backlinks->count();
        $rand = rand(0, $max-$count);
        return $this->dbh->backlinks->find()
            ->skip($rand)
            ->limit($count);
    }

    /*
     *      DELIVERY
     * */
    public function findDeliveryTask()
    {
        return $this->dbh->tasks->find(
            [
                'type'   => 'delivery',
                'status' => 'run',
            ])->sort(['count' => 1])
            ->getNext();
    }


    /*
     *      OTHER
     * */
    private function aggregateByDate($start, $end, $engine, $debug)
    {
        // format: 2010-01-15 00:00:00
        $mStart = new MongoDate(strtotime($start));
        $mEnd = new MongoDate(strtotime($end));

        $ops = [
            [
                '$match' => [
                    't' => [
                        '$gte' => $mStart,
                        '$lt' => $mEnd
                    ],
                ],
            ],
            [
                '$group' => [
                    '_id' => ['link' => '$l'],
                ]
            ]
        ];

        if($debug){
            echo "$engine aggregate... ";
        }

        $result = $this->dbh->$engine->aggregate($ops)['result'];

        if($debug){
            echo count($result) . "\n";
            $input = readline('Continue? (y/n): ');
            if($input == 'n'){
                echo "Bye.\n";
                die();
            }
        }
        return $result;
    }

    public function saveLinks($links, $engine)
    {
        $this->dbh->$engine->batchInsert($links);
        $this->debug->$engine = count($links);
        $this->debug->message = 'save links';
    }
    // save all debug info in current dbh life AND addInfo
    public function saveDebug($addIndo = [])
    {
        if(!empty($addIndo)){
            foreach ($addIndo as $key => $massage) {
                $this->debug->$key = $massage;
            }
        }
        $this->dbh->debug->insert($this->debug);
        unset($this->debug->_id);
        return $this->debug;
    }
    public function dropCurDb()
    {
        return $this->dbh->drop();
    }
}