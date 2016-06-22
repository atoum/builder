<?php

namespace atoum\builder;

use atoum\builder\controllers\error;
use atoum\builder\controllers\hook;
use atoum\builder\filesystem\sandbox;
use atoum\builder\providers\log;
use atoum\builder\providers\resque;
use JDesrosiers\Silex\Provider\SwaggerServiceProvider;
use Knp\Provider\ConsoleServiceProvider;
use Silex;
use Silex\Provider\ValidatorServiceProvider;
use SwaggerUI\Silex\Provider\SwaggerUIServiceProvider;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @SWG\Info(
 *     title="atoum builder",
 *     contact="team@atoum.org",
 *     license="BSD-3-Clause",
 *     licenseUrl="https://raw.githubusercontent.com/atoum/builder/master/LICENSE"
 * )
 */
class application extends Silex\Application
{
	public function boot()
	{
		$this
			->register(
				new SwaggerServiceProvider(),
				[
					'swagger.apiVersion' => 1,
					'swagger.apiDocPath' => '/docs',
					'swagger.srcDir' => __DIR__ . '/../vendor/zircote/swagger-php/library',
					'swagger.servicePath' => __DIR__ . '/controllers',
				]
			)
			->register(
				new SwaggerUIServiceProvider(),
				[
					'swaggerui.path'       => '/swagger',
					'swaggerui.apiDocPath' => '/docs'
				]
			)
			->register(new ValidatorServiceProvider())
			->register(new log())
			->register(new resque())
			->register(new ConsoleServiceProvider())
		;

		$this['sandbox'] = function() {
			return new sandbox();
		};

		parent::boot();
	}

	public function run(Request $request = null)
	{
		$this->get('/', function() { return new RedirectResponse('/phar'); });
		$this->post('/hook/push/{token}', new hook\push($this['auth_token'], $this['broker'], $this['validator'], $this['logger']));
		$this->post('/hook/pr/{token}', new hook\pr($this['auth_token'], $this['broker'], $this['validator'], $this['logger']));
		$this->error(new error());

		parent::run($request);
	}
}
