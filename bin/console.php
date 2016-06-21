<?php

use atoum\builder\commands\worker\backlog;

$app = include __DIR__ . '/../src/bootstrap.php';
$console = $app['console'];

$console->add(new backlog($app['broker']));

set_time_limit(0);
ini_set('memory_limit', -1);

$console->run();
