<?php

namespace atoum\builder\resque\jobs;

use atoum\builder\filesystem\sandbox;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;

class build
{
	public $args = [];

	/**
	 * @var sandbox
	 */
	private $sandbox;

	/**
	 * @var string
	 */
	private $directory;

	public function setUp()
	{
		$application = include __DIR__ . '/../../bootstrap.php';

		$this->setSandbox($application['sandbox']);
		$this->setDirectory($application['phar.directory']);
	}

	public function setSandbox(sandbox $sandbox)
	{
		$this->sandbox = $sandbox;
	}

	public function setDirectory($directory)
	{
		$this->directory = $directory;
	}

	public function perform()
	{
		$fs = new Filesystem();
		$git = new ProcessBuilder(['git']);
		$php = new ProcessBuilder(['php']);
		$gpg = new ProcessBuilder(['gpg']);

		$arguments = $this->getArguments();
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
	}

	private function getArguments()
	{
		return [
			'ref' => preg_replace('#^refs/heads/#', '', isset($this->args['push']) ? $this->args['push']['ref'] : $this->args['pr']['pull_request']['head']['ref']),
			'url' => isset($this->args['push']) ? $this->args['push']['repository']['url'] : $this->args['pr']['pull_request']['head']['repo']['url'],
			'prefix' => isset($this->args['push']) ? 'dev' : ('pr' . $this->args['pr']['number'])
		];
	}
}
