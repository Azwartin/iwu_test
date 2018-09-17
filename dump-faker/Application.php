<?php

namespace Nagaev\DumpFaker;

use Nagaev\DumpFaker\app\ArgumentParser;
use Nagaev\DumpFaker\processing\DumpObfuscator;
use Nagaev\DumpFaker\io\{
    FileReader, FileWriter, Reader, Writer
};

class Application 
{
    /**
     * associative array of app config params
     * @var array $config
    */
    protected $config = [];

    public function __construct(array $config) 
    {
        $this->config = $config;
    }

    /**
     * print usage
     * @return void
    */
    public function usage(): void 
    {
        echo <<<"USAGE"
    This script for obfuscating the parameters in sql dump
	Params
        --dump required, dump file path
        --dest required, obfuscated file path
        --conf obfuscation settings file path
        --help show this help\n
USAGE;
    }

    /**
     * @return int app exit code
    */
    public function run(array $args): int 
    {
        try {
            $args = new ArgumentParser($args);
            if ($args->getHelp()) {
                $this->usage();
                return 0;
            }

            if (!$args->getDumpPath() || !$args->getDestPath()) {
                $this->usage();
                return 0;
            }

            if ($args->getConfPath()) {
                $this->config = require $args->getConfPath();
            }

            $reader = $this->getReader($args->getDumpPath());
            $writer = $this->getWriter($args->getDestPath());
            $this->runObfuscation($reader, $writer);
            return 0;
        } catch (\Exception $e) {
            print_r($e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * Attach reader to app
     * @param string $path - dump file path
     * @return Reader
    */
    protected function getReader(string $path): Reader
    {
        return new FileReader($path);
    }

    /**
     * Attach writer to app
     * @param string $path - dump file path
     * @return Writer
    */
    protected function getWriter(string $path): Writer
    {
        return new FileWriter($path);
    }

    /**
     * @param Reader $reader
     * @param Writer $writer
     * @return void
    */
    protected function runObfuscation(Reader $reader, Writer $writer): void
    {
        $obfuscator = new DumpObfuscator($reader, $writer);
        $obfuscator->run();
    }
}