<?php
/**
 * @author: mix
 * @date: 09.03.14
 */
namespace ThreadUnit;

/**
 * Class ThreadUnit
 *
 * @package ThreadUnit
 */
class ThreadUnit
{
    private $rawArgs;

    private $namedArgs = [];

    private $listArgs;

    /**
     * @var Params
     */
    private $params;

    public function __construct($consoleArgs) {
        $this->rawArgs = $consoleArgs;
        $this->parseArgList();
        $this->params = new Params($this->namedArgs);
        if (!$this->params->getTmp()) {
            $tmpDir = $this->createTmpDir();
            $this->params->addParam("--tmp-dir", $tmpDir);
        }
    }

    private function parseArgList() {
        $currentKey = "";
        foreach ($this->rawArgs as $arg) {
            if ($arg[0] == "-") {
                if ($arg[1] == "-") {
                    if (strpos($arg, "=")) {
                        list($currentKey, $value) = explode("=", $arg);
                        $this->namedArgs[$currentKey] = $value;
                    } else {
                        $currentKey = $arg;
                    }
                } else {
                    $currentKey = mb_strcut($arg, 0, 2);
                    $value = mb_strcut($arg, 2);
                    $this->namedArgs[$currentKey] = $value;
                }
            } else {
                if ($currentKey) {
                    $this->namedArgs[$currentKey] = $arg;
                } else {
                    $this->listArgs[] = $arg;
                }
            }
        }
    }

    public function run() {

        $testMap = new TestMap($this->params);
        $tests = $testMap->getWorkers();

        $tests->prepare();



        do{
            $tests->tic();
        }while(!$tests->done());


        $pidCount = count($tests);

        $log = $testMap->getLogBuilder();
        $logPath = $this->params->getJlog();
        if ($logPath) {

            $log->save($logPath);
        }
        $log->echoStatus($pidCount);

        return $log->getExitStatus();
    }

    private function createTmpDir() {
        do {
            $str = "";
            for ($i = 0; $i < 10; $i++) {
                $str .= ('a' + mt_rand(0, 26));
            }
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $str;
            if (!file_exists($path)) {
                mkdir($path);

                return $path . "/";
                break;
            }
        } while (true);

        return null;
    }

    private function rmDir($dir) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (in_array($file, [".", ".."])) {
                continue;
            }
            if (is_dir($dir . "/" . $file)) {
                $this->rmDir($dir . "/" . $file);
            } else {
                unlink($dir . "/" . $file);
            }
        }
        rmdir($dir);
    }
}