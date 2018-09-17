#!/usr/bin/env php
<?php

/**
 * application bootstrap file
*/

require __DIR__ . '/bootstrap/autoload.php';
$config = require __DIR__ . '/config/main.php';

$app = new \Nagaev\DumpFaker\Application($config);
$exitCode = $app->run($argv);
exit($exitCode);