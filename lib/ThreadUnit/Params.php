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
     * @var \DOMDocument|bool
     */
    private $config;

    /**
     * @var string
     */
    private $root;

    /**
     * @var string
     */
    private $configPath;

    const HELP = "help";

    const CONFIG = "config";

    const THREADS = "threads";

    const FILESINTHREAD = "files_in_thread";

    const SUITE = "testsuite";

    const FILE = "file";

    const OLDLOG = "log";

    const LOGJUNIT = "log_junit";

    const OPTIONS = "options";

    const DEBUG = "debug";

    const TMPDIR = "tmpdir";

    private $args = [
        self::HELP => ["-h", "--help"],
        self::CONFIG => ["-c", "--configuration"],
        self::THREADS => ["-t", "--threads", "threads"],
        self::FILESINTHREAD => ["-f", "--files-per-thread", "files-per-thread"],
        self::SUITE => ["--testsuite"],
        self::FILE => ["--file"],
        self::LOGJUNIT => ["--log-junit"],
        self::OLDLOG => ["--old-log"],
        self::OPTIONS => ["-o", "--phpunit-options"],
        self::TMPDIR => ["--tmp-dir"],
        self::DEBUG => ["--debug"],
    ];

    private $help = [
        self::CONFIG => "Path to config file",
        self::THREADS => "Threads count",
        self::FILESINTHREAD => "Max files in one phpunit run",
        self::SUITE => "Run a single suite",
        self::FILE => "Run a single file",
        self::LOGJUNIT => "Write log",
        self::OLDLOG => "Use old log to opimize test balanser",
        self::OPTIONS => "PHPUnit options",
        self::HELP => "Displays this help",
        self::TMPDIR => "Temp directory. If exists, tmp files are not deleted. Used for debug",
        self::DEBUG => "Debug",
    ];

    public function __construct(array $namedArgs) {
        $this->configPath = $this->getConfigPath();
        if (!$this->configPath) {
            $this->configPath = getcwd() . "/phpunit.xml";
            $namedArgs["-c"] = $this->configPath;
        }
        $this->params = $namedArgs;
        $this->root = getcwd() . "/";
    }

    /**
     * @param string $name
     * @param $default
     * @return string
     */
    private function getParamByList($name, $default = null) {
        $list = $this->args[$name];
        foreach ($list as $key) {
            if (array_key_exists($key, $this->params)) {
                return $this->params[$key];
            }
        }
        if ($config = $this->config) {
            foreach ($list as $key) {
                $value = $config->documentElement->getAttribute($key);
                if ($value) {
                    return $value;
                }
            }
        }

        return $default;
    }

    public function addParam($key, $value) {
        $this->params[$key] = $value;
    }

    public function getSuites() {
        return $this->getConfig()->getElementsByTagName("testsuites")->item(0);
    }

    private function loadConfig() {
        if ($this->config === null) {
            if (file_exists($this->configPath)) {
                $this->config = new \DOMDocument();
                $this->config->load($this->configPath);
            } else {
                $this->config = false;
            }
        }

        return $this->config;
    }

    public function getConfig() {
        $config = $this->loadConfig();
        if (!$config) {
            throw new EnvironmentException("config not found in " . $this->configPath);
        }

        return $config;
    }

    public function getRoot() {
        return $this->root;
    }

    private function getPath($path) {
        if (strpos("~", $path) == 0) {
            $path = str_replace("~", $_SERVER["HOME"], $path);
        }

        return $path;
    }

    /**
     * @param $name
     * @return bool
     */
    private function has($name) {
        $list = $this->args[$name];
        $exists = false;
        foreach ($list as $key) {
            $exists |= array_key_exists($key, $this->params);
        }

        return $exists;
    }

    public function displayHelp() {
        echo "\n";
        echo "Usage: threadunit [options]\n";
        echo "Example: threadunit -t4 -f 5 --testsuite=Main\n";
        echo "will lunch threadunit in 4 threads with 5 files per single run of phpunit\n";
        echo "\n";
        echo "Options:\n";
        echo "\n";
        $out = [];
        $length = 0;
        foreach ($this->help as $name => $description) {
            $args = implode("|", array_slice($this->args[$name], 0, 2));
            $out[] = [$args, $description];
            $length = max($length, strlen($args));
        }
        foreach ($out as $line) {
            list($arg, $desc) = $line;
            echo "  " . $arg;
            for ($i = strlen($arg); $i < $length; $i++) {
                echo " ";
            }
            echo "    " . $desc . "\n";
        }
    }

    public function getTmp() {
        return $this->getPath($this->getParamByList(self::TMPDIR));
    }

    public function getConfigPath() {
        return $this->getPath($this->getParamByList(self::CONFIG));
    }

    public function getThreads() {
        return $this->getParamByList(self::THREADS, 1);
    }

    public function getFilePerThread() {
        return $this->getParamByList(self::FILESINTHREAD);
    }

    public function debug() {
        return $this->has(self::DEBUG) && $this->getParamByList(self::DEBUG) != "Off";
    }

    public function getTestSuite() {
        return $this->getParamByList(self::SUITE);
    }

    public function getJlog() {
        return $this->getPath($this->getParamByList(self::LOGJUNIT));
    }

    public function getOldLog() {
        return $this->getPath($this->getParamByList(self::OLDLOG));
    }

    public function getFile() {
        return $this->getPath($this->getParamByList(self::FILE));
    }

    public function hasFile() {
        return $this->has(self::FILE);
    }

    public function getOptions() {
        $this->getParamByList(self::OPTIONS);
    }

    public function needHelp() {
        return $this->has(self::HELP);
    }
}