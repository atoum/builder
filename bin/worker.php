<?php

$app = include __DIR__ . '/../src/bootstrap.php';

include __DIR__ . '/gpg.php';

$app['worker']->consume();
