<?php

namespace atoum\builder\filesystem;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;

class phar
{
    /**
     * @var string
     */
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function test(ProcessBuilder $php)
    {
        $php = clone $php;

        $php
            ->add($this->path)
            ->add('--test-it')
            ->add('-ulr')
            ->getProcess()
                ->mustRun()
        ;

        return $this;
    }

    public function deploy(Filesystem $fs, $version, $directory)
    {
        $destination = $directory . DIRECTORY_SEPARATOR . 'atoum-' . $version . '.phar';

        $fs->copy($this->path, $destination, true);

        return new self($destination);
    }

    public function sign(ProcessBuilder $gpg)
    {
        $gpg = clone $gpg;

        $gpg
            ->add('--armor')
            ->add('--detach-sign')
            ->add('--yes')
            ->add($this->path)
            ->getProcess()
                ->mustRun(function($_, $buffer) { echo $buffer; })
        ;

        return $this;
    }
}
