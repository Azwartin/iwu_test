<?php 

namespace Nagaev\DumpFaker\io;

use Nagaev\DumpFaker\io\Writer;

/**
 * Dump file writer
*/
class FileWriter implements Writer
{
    /**
     * @var resource $handle
    */
    protected $handle;

    public function __construct(string $file) 
    {
        $this->handle = fopen($file, 'w');
        if (!$this->handle) {
            throw new \RuntimeException("Can't open File $file");
        }
    }

    /**
     * Writes line to file
     * @inheritdoc
    */
    public function writeLine(string $line): bool 
    {
        return (bool) fwrite($this->handle, $line);
    }
}