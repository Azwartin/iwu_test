<?php

namespace Nagaev\DumpFaker\io;

/**
 * Common interface for writers
*/
interface Writer
{
    /** 
     * Add line to destination
     * @param string $line
     * @return bool is line added
    */
    public function writeLine(string $line): bool; 
}