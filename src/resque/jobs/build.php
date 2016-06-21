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

		$ref = explode('/', $this->args['push']['ref']);
		$branch = array_pop($ref);

		$sandbox = $this->sandbox->create($fs);
		$repository = $sandbox
			->klone($git, $this->args['push']['repository']['url'])
			->checkout($git, $branch)
			->tag($git, $php)
		;
		$phar = $sandbox
			->build($repository, $php)
			->test($php, function ($_, $buffer) { echo $buffer; })
		;

		if ($this->directory !== null)
		{
			$repository->deploy($phar, $this->directory, $fs);
		}
	}
}
