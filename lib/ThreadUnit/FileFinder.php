<?php
/**
 * @author: mix
 * @date: 16.03.14
 */
namespace ThreadUnit;

class FileFinder
{
    /** @var Params */
    private $params;

    private $suites = [];

    private $fileMap = [];

    public function __construct($params) {
        $this->params = $params;
    }

    public function getSuites() {
        return $this->suites;
    }

    public function getSuiteIdByFile($file) {
        if (isset($this->fileMap[$file])) {
            return $this->fileMap[$file];
        } else {
            static $unknowns = [];
            if (!in_array($file, $unknowns)) {
                echo "\nunknown test: $file";
                $unknowns[] = $file;
            }

            return null;
        }
    }

    public function getTestFiles($testSuite = null) {
        if ($this->params->hasFile()) {
            $file = $this->params->getFile();
            $tests = [$file];
            $this->fileMap[$file] = 0;
            $this->suites = [""];

            return $tests;
        }

        $tests = [];

        $suites = $this->params->getSuites()->childNodes;
        for ($i = 0; $i < $suites->length; $i++) {
            /** @var \DOMElement $suite */
            $suite = $suites->item($i);
            if ($suite->nodeName == "testsuite") {
                $suiteName = trim($suite->getAttribute("name"));
                if ($testSuite) {
                    if ($suiteName != $testSuite) {
                        continue;
                    }
                }
                $this->suites[] = $suiteName;
                $suiteId = count($this->suites) - 1;
                $suiteFiles = [];
                $exclude = [];
                $items = $suite->childNodes;
                for ($j = 0; $j < $items->length; $j++) {
                    /** @var \DOMDocument $item */
                    $item = $items->item($j);
                    if ($item->nodeName == "file") {
                        $suiteFiles[] = trim($item->nodeValue);
                    } elseif ($item->nodeName == "directory") {
                        $dir = trim($item->nodeValue);
                        $suffix = $item->attributes->getNamedItem("suffix");
                        if ($suffix) {
                            $suiteFiles = array_merge($suiteFiles, $this->scanDir($dir, trim($suffix->nodeValue)));
                        } else {
                            $suiteFiles = array_merge($suiteFiles, $this->scanDir($dir));
                        }
                    } elseif ($item->nodeName == "exclude") {
                        $excludeFile = trim($item->nodeValue);
                        $exclude[] = $excludeFile;
                    }
                }

                // perform exclude
                $filteredFiles = [];
                foreach ($suiteFiles as $file) {
                    $doExclude = false;
                    foreach ($exclude as $exFile) {
                        if (strpos($file, $exFile) === 0) {
                            $doExclude = true;
                            break;
                        }
                    }
                    if (!$doExclude) {
                        $filteredFiles[] = $file;
                    }
                }

                foreach ($filteredFiles as $file) {
                    $this->fileMap[$file] = $suiteId;
                }

                $tests = array_merge($tests, $filteredFiles);
            }
        }

        return $tests;
    }

    private function scanDir($dir, $suffix = null) {
        if (!file_exists($dir)) {
            return [];
        }
        $files = scandir($dir);
        if (!$files) {
            return [];
        }
        $out = [];
        foreach ($files as $file) {
            if (in_array($file, [".", ".."])) {
                continue;
            }
            if (is_dir($dir . "/" . $file)) {
                $out = array_merge($this->scanDir($dir . "/" . $file, $suffix), $out);
                continue;
            }
            if ($suffix) {
                if (preg_match("/{$suffix}$/", $file)) {
                    $out[] = $this->normalizePath($dir . "/" . $file);
                }
            } else {
                $out[] = $this->normalizePath($dir . "/" . $file);
            }
        }

        return $out;
    }

    private function normalizePath($path) {
        return str_replace($this->params->getRoot(), "", realpath($this->params->getRoot() . "/" . $path));
    }
}
