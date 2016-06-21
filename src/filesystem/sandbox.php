<?php

namespace atoum\builder\filesystem;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;

class sandbox
{
    /**
     * @var string
     */
    private $base;

    /**
     * @var string
     */
    private $repository;

    /**
     * @var string
     */
    private $output;

    public function __construct($base = null)
    {
        $this->base = $base ?: sys_get_temp_dir();
    }

    public function create(Filesystem $fs)
    {
        $sandbox = clone $this;
        $id = uniqid();

        try
        {
            $fs->mkdir($this->base . DIRECTORY_SEPARATOR . $id);
        }
        catch (IOException $exception)
        {
            throw new \runtimeException(sprintf('Unable to create sandbox at "%s"', $this->base), $exception->getCode(), $exception);
        }

        $sandbox->repository = $this->base . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'repository';

        try
        {
            $fs->mkdir($sandbox->repository);
        }
        catch (IOException $exception)
        {
            $fs->remove($this->base);

            throw new \runtimeException(sprintf('Unable to create repository directory at "%s"', $sandbox->repository), $exception->getCode(), $exception);
        }

        $sandbox->output = $this->base . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'output';

        try
        {
            $fs->mkdir($sandbox->output);
        }
        catch (IOException $exception)
        {
            $fs->remove($this->base);

            throw new \runtimeException(sprintf('Unable to create output directory at "%s"', $sandbox->output), $exception->getCode(), $exception);
        }

        return $sandbox;
    }

    public function klone(ProcessBuilder $git, $url)
    {
        if ($this->repository === null)
        {
            throw new \LogicException('Repository directory is not defined');
        }

        if (is_dir($this->repository) === false)
        {
            throw new \LogicException(sprintf('Repository directory does not exist at "%s"', $this->repository));
        }

        $git = clone $git;

        $git
            ->add('clone')
            ->add($url)
            ->add($this->repository)
            ->getProcess()
                ->mustRun()
        ;

        return new repository($this->repository);
    }

    public function build(repository $repository, ProcessBuilder $php)
    {
        return $repository->build($php, $this->output);
    }
}
