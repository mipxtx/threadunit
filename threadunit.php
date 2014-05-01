#!/usr/bin/env php
<?php
namespace ThreadUnit;


spl_autoload_register(
    function ($className) {
        $className = str_replace("ThreadUnit\\", "", $className);
        $file = __DIR__ . "/lib/" . str_replace("_", DIRECTORY_SEPARATOR, $className) . ".php";
        require $file;
    }
);

$time = microtime(1);
try {
    $app = new ThreadUnit($argv);
    $exitCode = $app->run();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    $exitCode = 1;
}

$total = (microtime(1) - $time);
echo "\nTotal time: ";
if ($total > 1) {
    echo round($total, 2) . "s\n";
} else {
    echo round($total * 1000, 2) . "ms\n";
}

exit($exitCode);