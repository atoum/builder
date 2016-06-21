<?php

namespace atoum\builder;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new application();

$app['auth_token'] = getenv('ATOUM_BUILDER_AUTH_TOKEN');

$app['phar.directory'] = getenv('ATOUM_BUILDER_PHAR_DIRECTORY') ?: null;

$app['monolog.name'] = 'atoum-builder';
$app['monolog.logfile'] = 'php://stdout';

$app['console.name'] = $app['monolog.name'];
$app['console.version'] = '1.0.0';
$app['console.project_directory'] = __DIR__;

$app['redis.host'] = getenv('ATOUM_BUILDER_REDIS_HOST') ?: null;
$app['redis.port'] = getenv('ATOUM_BUILDER_REDIS_PORT') ?: null;
$app['resque.queue'] = getenv('ATOUM_BUILDER_RESQUE_QUEUE') ?: null;

$app->boot();

return $app;
