ThreadUnit
==========

a multithread wrapper for phpunit


<pre>
Usage: threadunit [options]
Example: threadunit -t4 -f 5 --testsuite=Main
will lunch threadunit in 4 threads with 5 files per single run of phpunit

Options:

  -c|--configuration       Path to config file
  -t|--threads             Threads count
  -f|--files-per-thread    Max files in one phpunit run
  --testsuite              Run a single suite
  --file                   Run a single file
  --log-junit              Write log
  --old-log                Use old log to opimize test balanser
  -o|--phpunit-options     PHPUnit options
  -h|--help                Displays this help
</pre>
