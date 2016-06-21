<?php

namespace atoum\builder\providers;

use atoum\builder;
use atoum\builder\resque\broker;
use atoum\builder\resque\worker;
use Silex\Application;
use Silex\ServiceProviderInterface;

class resque implements ServiceProviderInterface
{
	public function register(Application $app)
	{
		$app['broker'] = function () use ($app) {
			return $app['resque.broker'];
		};

		$app['worker'] = function () use ($app) {
			return $app['resque.worker'];
		};

		$app['resque.broker'] = $app->share(function (builder\application $app) {
			return new broker(
				isset($app['redis.host']) ? $app['redis.host'] : 'localhost',
				isset($app['redis.port']) ? $app['redis.port'] : 6379,
				isset($app['resque.queue']) ? $app['resque.queue'] : 'atoum'
			);
		});

		$app['resque.worker'] = $app->share(function (builder\application $app) {
			return new worker(
				isset($app['redis.host']) ? $app['redis.host'] : 'localhost',
				isset($app['redis.port']) ? $app['redis.port'] : 6379,
				isset($app['resque.queue']) ? $app['resque.queue'] : 'atoum'
			);
		});
	}

	public function boot(Application $app)
	{

	}
}
