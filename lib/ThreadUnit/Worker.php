<?php
/**
 * @author: mix
 * @date: 11.03.14
 */
namespace ThreadUnit;

/**
 * Class Worker
 *
 * @package ThreadUnit
 */
class Worker
{
    const STATE_LUNCH = "lunch";

    const STATE_OPEN = "open";

    const STATE_READ = "read";

    const STATE_CLOSE = "close";

    const STATE_FINALIZE = "finalaze";

    const STATE_SKIP = "skip";

    private $tests = [];

    private $params;

    private $threadId;

    private $testPacks = [];

    private $logFiles = [];

    /**
     * @var WorkersBalancer
     */
    private $testHub;

    public function  __construct(Params $params, $threadId, $testHub) {
        $this->params = $params;
        $this->threadId = $threadId;
        $this->testHub = $testHub;
    }

    public function addTest($name, $weight) {
        $this->tests[$name] = $weight;
    }

    public function prepareTests() {
        $packs = [];
        $filesPerThread = $this->params->getFilePerThread();
        if ($filesPerThread) {
            $i = 0;
            $packId = 0;
            arsort($this->tests);
            foreach ($this->tests as $test => $weight) {
                /** @var int $packId */
                $packs[$packId][] = $test;
                $i++;
                if ($i == $filesPerThread) {
                    $packId++;
                    $i = 0;
                }
            }
        } else {
            $packs[] = array_keys($this->tests);
        }
        $this->testPacks = $packs;
    }

    public function generateConfig($id) {
        $config = $this->params->getConfig();

        $pack = $this->testPacks[$id];

        $node = $config->getElementsByTagName("testsuites")->item(0);
        $node->parentNode->removeChild($node);
        $suites = $config->createElement("testsuites");
        $config->documentElement->appendChild($suites);
        $suite = $config->createElement("testsuite");
        $suites->appendChild($suite);
        foreach ($pack as $test) {
            $file = $config->createElement("file");
            $file->nodeValue = $test;
            $suite->appendChild($file);
        }
        $config->preserveWhiteSpace = false;
        $config->formatOutput = true;

        return $config;
    }

    protected function getLogPath($id) {
        return $this->params->getTmp() . "log." . $this->threadId . "." . $id . ".xml";
    }

    protected function getConfigPath($id) {
        return $this->params->getRoot() . "phpunit." . $this->threadId . "." . $id . ".xml";
    }

    public function getLogFiles() {
        return $this->logFiles;
    }

    /**
     * @var resource
     */
    private $proc;

    /**
     * @var array
     */
    private $pipes;

    private $currentId = 0;

    private $state = self::STATE_LUNCH;

    private $output = "";

    private $lastChar = "";

    private $lastCmd;

    public function lunch() {

        if (count($this->testPacks) == $this->currentId) {
            $this->state = self::STATE_SKIP;

            return;
        }

        $id = $this->currentId;
        $this->output = "";
        $config = $this->generateConfig($id);
        $path = $this->getConfigPath($id);
        $config->save($path);
        $log = $this->getLogPath($id);
        $cmd = $this->lastCmd =
            "phpunit -c $path -d 'display_errors=On' --log-junit=" . $log;

        if ($this->params->debug()) {
            $path2 = $this->params->getTmp() . "phpunit." . $this->threadId . "." . $id . ".xml ";

            $config->save($path2);
            echo "\n" . $cmd . "\n";
            echo "files: \n" . implode("\n", $pack = $this->testPacks[$id]) . "\n";
        }

        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin - канал, из которого дочерний процесс будет читать
            1 => array("pipe", "w"), // stdout - канал, в который дочерний процесс будет записывать
            2 => array("pipe", "w") // stderr - файл для записи
        );

        $this->proc = proc_open($cmd, $descriptorspec, $this->pipes);
        fclose($this->pipes[0]);

        $this->state = self::STATE_OPEN;
    }

    public function open() {

        $count = 0;
        do {
            $char = fread($this->pipes[1], 1);
            $this->output .= $char;
            if ($char === "\n") {
                $count++;
            }

            if (feof($this->pipes[1])) {
                $this->state = self::STATE_FINALIZE;

                return;
            }
        } while ($count < 4);
        //$this->output = "";
        $this->state = self::STATE_READ;
    }

    public function read() {
        if (feof($this->pipes[1])) {
            $this->state = self::STATE_FINALIZE;

            return;
        }
        $char = fread($this->pipes[1], 1);
        $this->output .= $char;

        if (in_array($char, [".", "S", "F", "E"])) {
            $this->testHub->notifyTest($char);
        } elseif ($this->lastChar == "F" && $char == "a") {
            $this->state = self::STATE_FINALIZE;

            return;
        }

        if ($this->lastChar === $char && $char === "\n") {
            $this->state = self::STATE_CLOSE;
        }
        $this->lastChar = $char;
    }

    public function close() {
        if (feof($this->pipes[1])) {
            $this->state = self::STATE_FINALIZE;

            return;
        }
        $char = fread($this->pipes[1], 1);
        $this->output .= $char;
    }

    public function finalize() {

        $out = stream_get_contents($this->pipes[1]);

        $code = proc_close($this->proc);

        if ($code == 255) {

            $pack = $this->testPacks[$this->currentId];
            if (count($pack) == 1) {
                if ($out) {
                    $out = "Fa" . $out;
                } else {
                    $out = "Unexpected end of test {$pack[0]}. Exit code:$code. ";
                }

                $str = "<?xml version='1.0' encoding='UTF-8'?>
<testsuites>
  <testsuite name='' tests='0' assertions='0' failures='0' errors='1' time='0'>
    <testsuite name='' file='{$pack[0]}' tests='0' assertions='0' failures='0' errors='1' time='0'>
      <error type='Fatal_Error'>
        {$out}
      </error>
    </testsuite>
  </testsuite>
</testsuites>";
                file_put_contents($this->getLogPath($this->currentId), $str);

                $this->logFiles[] = $this->getLogPath($this->currentId);
            } else {
                $new = [[], []];
                foreach ($pack as $i => $file) {
                    $new[$i % 2][] = $file;
                }
                $this->testPacks[] = $new[0];
                $this->testPacks[] = $new[1];
            }
        } else {
            $this->logFiles[] = $this->getLogPath($this->currentId);
        }

        if (!$this->params->debug()) {
            unlink($this->getConfigPath($this->currentId));
        }

        $this->currentId++;

        $this->state = self::STATE_LUNCH;
    }

    public function generateFatalLog($file, $id, $out) {
    }

    public function tic() {
        switch ($this->state) {
            case self::STATE_LUNCH :
                $this->lunch();
                break;
            case self::STATE_OPEN :
                $this->open();
                break;
            case self::STATE_READ :
                $this->read();
                break;
            case self::STATE_CLOSE :
                $this->close();
                break;
            case self::STATE_FINALIZE :
                $this->finalize();
                break;
        }
    }

    public function output() {
        echo $this->output . "\n";
    }

    public function done() {
        return $this->state == self::STATE_SKIP;
    }

    public function debug() {
        $sum = 0;
        foreach ($this->tests as $test => $weight) {
            $sum += $weight;
        }
        echo "Total: $sum\n";
    }
}