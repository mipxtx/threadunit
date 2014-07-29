<?php
/**
 * @author: mix
 * @date: 16.03.14
 */
namespace ThreadUnit;

class LogBuilder
{
    /**
     * @var Params
     */
    private $params;

    /**
     * @var FileFinder
     */
    private $finder;

    private $logParams = ["tests", "assertions", "failures", "errors", "time",];

    /**
     * @var \DOMDocument
     */
    private $log;

    private $logsFileList;

    public function __construct(Params $params, FileFinder $finder, array $logsFileList) {
        $this->params = $params;
        $this->finder = $finder;
        $this->logsFileList = $logsFileList;
    }

    /**
     * @return \DOMDocument
     */
    public function getLog() {
        if (!$this->log) {
            $this->log = $this->createLog();
        }

        return $this->log;
    }

    public function echoStatus($threadsCount, $execTime) {
        /** @var \DOMElement $info */
        $info = $this->getLog()->getElementsByTagName("testsuite")->item(0);
        $desc = [];
        echo "\nThreads: $threadsCount, Test time: {$execTime} s";
        foreach ($this->logParams as $param) {
            $val = $info->getAttribute($param);
            if ($val) {
                $desc[] = ucfirst($param) . ": " . round($val, 2);
            }
        }
        echo "\n" . implode(", ", $desc) . "\n";
        $this->echoErrors("error");
        $this->echoErrors("failure");
    }

    public function echoErrors($tag) {

        $errors = $this->getLog()->getElementsByTagName($tag);
        if ($errors->length > 0) {
            echo "\n" . ucfirst($tag) . "s:";
            for ($i = 0; $i < $errors->length; $i++) {
                $node = $errors->item($i);
                echo "\n#$i: " . trim($node->nodeValue) . "\n";
            }
        }
    }

    public function getExitStatus() {
        $errorCode = 0;
        /** @var \DOMElement $info */
        $info = $this->getLog()->getElementsByTagName("testsuite")->item(0);
        foreach (["failures", "errors"] as $param) {
            $val = $info->getAttribute($param);
            if ($val) {
                $errorCode = 1;
            }
        }

        return $errorCode;
    }

    public function save($path) {
        $this->getLog()->save($path);
    }

    /**
     * @return \DOMDocument
     */
    private function createLog() {
        $params = $this->logParams;
        $out = new \DOMDocument();
        $out->preserveWhiteSpace = true;
        $out->appendChild($out->createElement("testsuites"));
        $rootSuite = $out->createElement("testsuite");
        $out->documentElement->appendChild($rootSuite);
        foreach ($params as $param) {
            $rootSuite->setAttribute($param, "0");
        }
        /** @var \DOMElement[] $outSuites */
        $outSuites = [];
        foreach ($this->finder->getSuites() as $suite) {
            $outSuite = $out->createElement("testsuite");
            $outSuite->setAttribute("name", $suite);
            $outSuites[] = $outSuite;
            $br = $out->createTextNode("\n  ");
            $rootSuite->appendChild($br);
            $rootSuite->appendChild($outSuite);
        }

        $totalSuites = [];

        foreach ($this->logsFileList as $file) {

            $d = new \DOMDocument();
            $d->load($file);
            $suites = $d->getElementsByTagName("testsuite")->item(0);

            // no tests in file
            if (!$suites) {
                continue;
            }

            $children = $suites->childNodes;
            for ($i = 0; $i < $children->length; $i++) {
                /** @var \DOMElement $node */
                $node = $children->item($i);
                if ($node->nodeName == "testsuite") {
                    $file = str_replace(
                        dirname(realpath($this->params->getConfigPath())) . "/",
                        "",
                        $node->getAttribute("file")
                    );
                    $id = $this->finder->getSuiteIdByFile($file);
                    $newNode = $out->importNode($node, true);
                    if (is_int($id)) {
                        $outSuite = $outSuites[$id];
                    } else {
                        $outSuite = $rootSuite;
                    }

                    $br = $out->createTextNode("\n    ");
                    $outSuite->appendChild($br);
                    $outSuite->appendChild($newNode);
                    foreach ($params as $param) {
                        if (!isset($totalSuites[$id][$param])) {
                            $totalSuites[$id][$param] = 0;
                        }
                        $totalSuites[$id][$param] += (float)$node->getAttribute($param);
                    }
                }
            }
        }

        $overall = [];
        foreach ($totalSuites as $suite) {
            foreach ($params as $param) {
                if (!isset($overall[$param])) {
                    $overall[$param] = 0;
                }
                $overall[$param] += $suite[$param];
            }
        }

        foreach ($overall as $param => $value) {
            $rootSuite->setAttribute(
                $param,
                $value
            );
        }

        return $out;
    }
}