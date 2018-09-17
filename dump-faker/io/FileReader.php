<?php 

namespace Nagaev\DumpFaker\io;

use Nagaev\DumpFaker\io\Reader;

/**
 * Dump file reader
*/
class FileReader implements Reader
{
    /**
     * @var resource $handle
    */
    protected $handle;

    public function __construct(string $file) 
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("File $file is not exist or not a file");
        }

        $this->handle = fopen($file, 'r');
    }

    /**
     * Read string from file - max length 4096
    */
    public function readLine(): string 
    {
        return fgets($this->handle, 4096);
    }

    /**
     * Check that next line exists
    */
    public function valid(): bool 
    {
        return !feof($this->handle);
    }
}