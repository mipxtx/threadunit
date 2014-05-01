<?php
/**
 * @author: mix
 * @date: 13.03.14
 */
namespace ThreadUnit;

/**
 * Class TestMap
 *
 * @package ThreadUnit
 */
class TestMap
{
    private $params;

    private $fileFinder;

    /**
     * @var WorkersBalancer|Worker[]
     */
    private $workers;

    public function __construct(Params $config) {
        $this->params = $config;
        $this->fileFinder = new FileFinder($this->params);
        $this->workers = new WorkersBalancer($this->params);
        $this->addTests();
    }

    public function getWorkers() {
        return $this->workers;
    }

    public function getLogBuilder() {
        return new LogBuilder($this->params, $this->fileFinder, $this->workers->getLogFiles());
    }

    private function addTests() {
        $files = $this->fileFinder->getTestFiles($this->params->getTestSuite());
        $oldLog = $this->params->getOldLog();
        if ($oldLog) {
            $timings = $this->getTimings($oldLog);
            $avgTime = 0;
            foreach ($timings as $time) {
                $avgTime += ($time / count($timings));
            }
            foreach ($files as $file) {
                $fullName = realpath(dirname($this->params->getConfigPath()) . "/" . $file);
                if (isset($timings[$fullName])) {
                    $value = $timings[$fullName];
                } else {
                    $value = $avgTime;
                }
                $this->workers->addTest($file, $value);
            }
        } else {
            foreach ($files as $file) {
                $this->workers->addTest($file, 1);
            }
        }
    }

    private function getTimings($path) {
        $d = new \DOMDocument();
        $d->load($path);
        $root = $d->getElementsByTagName("testsuite")->item(0);
        $timings = [];
        for ($i = 0; $i < $root->childNodes->length; $i++) {
            $suite = $root->childNodes->item($i);
            if ($suite->nodeName == "testsuite") {
                for ($j = 0; $j < $suite->childNodes->length; $j++) {
                    /** @var \DOMElement $file */
                    $file = $suite->childNodes->item($j);
                    if ($file->nodeName == "testsuite" && $name = $file->getAttribute("file")) {
                        $timings[$name] = $file->getAttribute("time");
                    }
                }
            }
        }

        return $timings;
    }
}