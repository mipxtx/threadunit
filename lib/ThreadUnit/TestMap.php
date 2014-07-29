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
            $files = array_flip($files);
            foreach ($this->getTimings($oldLog) as $filename => $weight) {
                if (isset($files[$filename])) {
                    $this->workers->addTest($filename, $weight);
                    unset($files[$filename]);
                }
            }
            foreach ($files as $filename) {
                $this->workers->addTest($filename, 0);
            }
        } else {
            foreach ($files as $file) {
                $this->workers->addTest($file, 0);
            }
        }
    }

    public function getTimings($path) {
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
                        $timings[str_replace(getcwd() . DIRECTORY_SEPARATOR, "", $name)] = $file->getAttribute("time");
                    }
                }
            }
        }
        arsort($timings);

        return $timings;
    }
}