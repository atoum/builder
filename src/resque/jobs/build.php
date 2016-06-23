<?php

namespace atoum\builder\resque\jobs;

use atoum\builder\filesystem\sandbox;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;

class build
{
	const PENDING = 'pending';
	const FAILED = 'failure';
	const SUCCESS = 'success';

	public $args = [];

	/**
	 * @var sandbox
	 */
	private $sandbox;

	/**
	 * @var string
	 */
	private $directory;

	/**
	 * @var \Github\Client
	 */
	private $github;

	/**
	 * @var string
	 */
	private $url;

	public function setUp()
	{
		$application = include __DIR__ . '/../../bootstrap.php';

		$this->setSandbox($application['sandbox']);
		$this->setDirectory($application['phar.directory']);
		$this->setGithub($application['github']);
		$this->setUrl($application['app.url']);
	}

	public function setSandbox(sandbox $sandbox)
	{
		$this->sandbox = $sandbox;
	}

	public function setDirectory($directory)
	{
		$this->directory = $directory;
	}

	public function setGithub(\Github\Client $github = null)
	{
		$this->github = $github;
	}

	public function setUrl($url = null)
	{
		$this->url = $url;
	}

	public function perform()
	{
		$arguments = $this->getArguments();

		if (null !== $this->github) {
			$this->github->repo()->statuses()->create(
				$arguments['owner'],
				$arguments['repository'],
				$arguments['sha'],
				array(
					'state' => self::PENDING,
					'description' => 'Building the PHAR...',
					'context' => 'continuous-integration/atoum/builder'
				)
			);
		}

		try {
			$fs = new Filesystem();
			$git = new ProcessBuilder(['git']);
			$php = new ProcessBuilder(['php']);
			$gpg = new ProcessBuilder(['gpg']);

			$branch = preg_replace('#^refs/heads/#', '', $arguments['ref']);

			$sandbox = $this->sandbox->create($fs);
			$repository = $sandbox
				->klone($git, $arguments['url'])
				->checkout($git, $branch)
				->tag($git, $php, $arguments['prefix'])
			;
			$phar = $sandbox
				->build($repository, $php)
				->test($php)
			;

			if ($this->directory !== null)
			{
				$repository
					->deploy($phar, $this->directory, $fs)
					->sign($gpg)
				;
			}

			if (null !== $this->github) {
				$this->github->api('repo')->statuses()->create(
					$arguments['owner'],
					$arguments['repository'],
					$arguments['sha'],
					array(
						'state' => self::SUCCESS,
						'description' => 'Successfully built the PHAR',
						'context' => 'continuous-integration/atoum/builder',
						'target_url' => $this->url
					)
				);
			}
		} catch (\Exception $exception) {
			if (null !== $this->github) {
				$this->github->api('repo')->statuses()->create(
					$arguments['owner'],
					$arguments['repository'],
					$arguments['sha'],
					array(
						'state' => self::FAILED,
						'description' => 'Failed to build the PHAR...',
						'context' => 'continuous-integration/atoum/builder'
					)
				);
			}
		}

	}

	private function getArguments()
	{
		return [
			'ref' => preg_replace('#^refs/heads/#', '', isset($this->args['push']) ? $this->args['push']['ref'] : $this->args['pr']['pull_request']['head']['ref']),
			'url' => isset($this->args['push']) ? $this->args['push']['repository']['url'] : $this->args['pr']['pull_request']['head']['repo']['clone_url'],
			'prefix' => isset($this->args['push']) ? 'dev' : ('pr' . $this->args['pr']['number']),
			'sha' => isset($this->args['push']) ? $this->args['push']['head'] : $this->args['pr']['pull_request']['head']['sha'],
			'owner' => isset($this->args['push']) ? $this->args['push']['repository']['owner']['name'] : $this->args['pr']['pull_request']['base']['repo']['owner']['login'],
			'repository' => isset($this->args['push']) ? $this->args['push']['repository']['name'] : $this->args['pr']['pull_request']['base']['repo']['name'],
		];
	}
}
