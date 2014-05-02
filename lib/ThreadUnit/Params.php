<?php
/**
 * @author: mix
 * @date: 09.03.14
 */
namespace ThreadUnit;

/**
 * Class Params
 *
 * @package ThreadUnit
 */
class Params
{
    private $params = [];

    /**
     * @var \DOMDocument
     */
    private $config;

    private $root;

    public function __construct(array $namedArgs) {

        $configPath = $this->getConfigPath();
        if (!$configPath) {
            $configPath = getcwd() . "/phpunit.xml";
            $namedArgs["-c"] = $configPath;
        }

        $this->params = $namedArgs;

        if (!file_exists($configPath)) {
            throw new Exception("config not found");
        }

        $this->config = new \DOMDocument();
        $this->config->load($configPath);

        $this->root = getcwd() . "/";
    }

    /**
     * @param array $list
     * @return string
     */
    private function getParamByList(array $list) {

        foreach ($list as $key) {
            if (array_key_exists($key, $this->params)) {
                return $this->params[$key];
            }
        }
        if ($this->config) {
            foreach ($list as $key) {
                $value = $this->config->documentElement->getAttribute($key);
                if ($value) {
                    return $value;
                }
            }
        }

        return null;
    }

    public function addParam($key,$value){
        $this->params[$key] = $value;
    }

    public function getSuites() {
        return $this->config->getElementsByTagName("testsuites")->item(0);
    }

    public function getConfig(){
        return $this->config;
    }

    public function getRoot(){
        return $this->root;
    }

    private function getPath($path){
        if(strpos("~",$path) == 0){
            $path = str_replace("~", $_SERVER["HOME"], $path);
        }
        return $path;
    }

    private function has($name){
        return array_key_exists($name, $this->params);
    }

    public function getTmp(){
        return $this->getPath($this->getParamByList(["--tmp-dir"]));
    }

    public function getConfigPath() {
        return $this->getPath($this->getParamByList(["-c", "--configuration"]));
    }

    public function getThreads() {
        $count = $this->getParamByList(["-t", "--threads", "threads"]);
        if(!$count){
            $count = 1;
        }
        return $count;
    }

    public function getFilePerThread() {
        return $this->getParamByList(["-f", "--files-per-thread", "files-per-thread"]);
    }

    public function debug(){
        return $this->getParamByList(["--debug"]) == "On";
    }

    public function getTestSuite(){
        return $this->getParamByList(["--testsuite",]);
    }

    public function getJlog(){
        return $this->getPath($this->getParamByList(["--log-junit"]));
    }

    public function getOldLog(){
        return $this->getPath($this->getParamByList(["--old-log"]));
    }

    public function getFile(){
        return $this->getPath($this->getParamByList(["--file"]));
    }

    public function hasFile(){
        return $this->has("--file");
    }

    public function getOptions(){
        $this->getParamByList(["-o", "--phpunit-options"]);
    }



}