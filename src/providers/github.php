<?php

namespace atoum\builder\providers;

use Silex\Application;
use Silex\ServiceProviderInterface;

class github implements ServiceProviderInterface
{
	public function register(Application $app)
	{
		$app['github'] = function () use ($app) {
			if (isset($app['github.token']) === false) {
				return null;
			}

			$client = new \Github\Client();

			$client->authenticate($app['github.token'], null, \Github\Client::AUTH_HTTP_TOKEN);

			return $client;
		};
	}

	public function boot(Application $app)
	{

	}
}
