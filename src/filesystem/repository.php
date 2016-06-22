<?php

namespace atoum\builder\filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\ProcessBuilder;

class repository
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $branch;

    /**
     * @var string
     */
    private $version;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    public function checkout(ProcessBuilder $git, $branch)
    {
        if (is_dir($this->directory) === false)
        {
            throw new \LogicException(sprintf('Repository directory does not exist at "%s"', $this->directory));
        }

        $git = clone $git;

        $git
            ->add('checkout')
            ->add($branch)
            ->getProcess()
                ->setWorkingDirectory($this->directory)
                ->mustRun()
        ;

        $repository = clone $this;

        $repository->branch = $branch;

        return $repository;
    }

    public function tag(ProcessBuilder $git, ProcessBuilder $php, $prefix = null)
    {
        if (is_dir($this->directory) === false)
        {
            throw new \LogicException(sprintf('Repository directory does not exist at "%s"', $this->directory));
        }

        if ($this->branch === null)
        {
            throw new \LogicException('Branch is not defined');
        }

        $git = clone $git;
        $php = clone $php;

        $process = $git
            ->add('rev-parse')
            ->add($this->branch)
            ->getProcess()
        ;

        $process
            ->setWorkingDirectory($this->directory)
            ->mustRun()
        ;

        $repository = clone $this;

        $sha1 = trim($process->getOutput());
        $prefix = null !== $prefix ? $prefix . '-' : $prefix;
        $repository->version = $prefix . $this->branch .'-' . substr($sha1, 0, 9);

        $process = $php
            ->add('scripts' . DIRECTORY_SEPARATOR . 'tagger.php')
            ->add('-s')
            ->add($this->directory)
            ->add('-v')
            ->add($repository->version)
            ->getProcess()
        ;

        $process
            ->setWorkingDirectory($this->directory)
            ->mustRun(function ($_, $buffer) { echo $buffer; })
        ;

        return $repository;
    }

    public function build(ProcessBuilder $php, $directory)
    {
        if (is_dir($this->directory) === false)
        {
            throw new \LogicException(sprintf('Repository directory does not exist at "%s"', $this->directory));
        }

        if (is_dir($directory) === false)
        {
            throw new \LogicException(sprintf('Destination directory does not exist at "%s"', $directory));
        }

        $php = clone $php;

        $process = $php
            ->add('-dphar.readonly=Off')
            ->add('scripts' . DIRECTORY_SEPARATOR . 'phar' . DIRECTORY_SEPARATOR . 'generator.php')
            ->add('-d')
            ->add($directory)
            ->getProcess()
        ;

        $process
            ->setWorkingDirectory($this->directory)
            ->mustRun()
        ;

        return new phar($directory . DIRECTORY_SEPARATOR . 'atoum.phar');
    }

    public function deploy(phar $phar, $directory, Filesystem $fs)
    {
        return $phar->deploy($fs, $this->version, $directory);
    }
}
