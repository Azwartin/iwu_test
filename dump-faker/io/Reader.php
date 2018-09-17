<?php

namespace Nagaev\DumpFaker\io;

/**
 * Common interface for readers
*/
interface Reader
{ 
    /**
     * Return line from src
     * @return string
    */
    public function readLine(): string;

    /**
     * Check that next line is exists
    */
    public function valid(): bool;
}