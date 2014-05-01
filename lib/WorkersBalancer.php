<?php
/**
 * @author: mix
 * @date: 16.03.14
 */
namespace ThreadUnit;

/**
 * Class WorkersBalancer
 *
 * @package ThreadUnit
 */
class WorkersBalancer implements \Iterator, \Countable
{
    /**
     * @var Worker[]
     */
    private $pack = [];

    private $map = [];

    private $counter = 0;

    public function __construct(Params $params) {
        $threadsCount = $params->getThreads();
        for ($i = 0; $i < $threadsCount; $i++) {
            $this->pack[] = new Worker($params, $i, $this);
            $this->map[] = 0;
        }
    }

    public function addTest($name, $weight) {
        asort($this->map);
        reset($this->map);
        list($id) = each($this->map);
        $this->pack[$id]->addTest($name, $weight);
        $this->map[$id] += $weight;
    }

    public function getLogFiles(){
        $out = [];
        foreach($this->pack as $worker){
            $files = $worker->getLogFiles();
            $out = array_merge($out,$files);
        }

        return $out;
    }

    public function prepare(){
        foreach($this->pack as $test){
            $test->prepareTests();
        }
    }


    public function tic(){
        foreach($this->pack as $test){
            if(!$test->done()){
                $test->tic();
            }
        }
    }

    public function done(){
        foreach($this->pack as $test){
            if(!$test->done()){
                return false;
            }
        }
        return true;
    }


    public function output(){
        foreach($this->pack as $test){
            $test->output();
        }
    }


    public function notifyTest($char){
        static $count=0;
        if($count % 65 == 0){
            echo "\n";
        }
        echo $char;
        $count++;
    }



    /**
     * @return Worker
     */
    public function current() {
        return $this->pack[$this->counter];
    }

    public function next() {
        $this->counter++;
    }

    public function key() {
        return $this->counter;
    }

    public function valid() {
        return $this->counter < count($this->map);
    }

    public function rewind() {
        $this->counter = 0;
    }


    public function count() {
        return count($this->map);
    }
}