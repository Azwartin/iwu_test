<?php

namespace Nagaev\DumpFaker\app;

/***
 * Class for parsing stratup parameters
*/
class ArgumentParser 
{
    /**
     * @var array of parsed args [dump => path, dest => path, conf => path, help => bool]
    */
    protected $params;

    public function __construct(array $args) 
    {
        $this->params = $this->parseArgs($args);
    }

     /**
     * Get array of script arguments, return associative array of app stratup parameters
     * @return array 
    */
    protected function parseArgs(array $args): array 
    {
        $params = [];
        for ($i = 0, $len = count($args); $i < $len; $i++) {
            $arg = $args[$i];
            switch ($arg) {
                case '--dump':
                    $i++;
                    $params['dump'] = $this->getStringValueFromArgs($args, $i);
                    break;
                case '--dest':
                    $i++;
                    $params['dest'] = $this->getStringValueFromArgs($args, $i);
                    break;
                case '--conf':
                    $i++;
                    $params['conf'] = $this->getStringValueFromArgs($args, $i);
                    break;
                case '--help':
                    $params['help'] = true;
                    break;
            }
        }
        
        return $params;
    }

    public function getHelp(): bool 
    {
        return $this->params['help'] ?? false;
    }

    public function getConfPath(): string 
    {
        return $this->params['conf'] ?? '';
    }

    public function getDestPath(): string 
    {
        return $this->params['dest'] ?? '';
    }

    public function getDumpPath(): string 
    {
        return $this->params['dump'] ?? '';
    }

    /**
     * @param array $args
     * @param int $i
     * @return string from array $args on position $i - if not set or not string - return empty string
    */
    protected function getStringValueFromArgs(array $args, int $i): string 
    {
        return isset($args[$i]) && is_string($args[$i]) ? $args[$i] : '';
    }
}