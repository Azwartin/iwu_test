<?php

namespace Nagaev\DumpFaker\processing;

use Nagaev\DumpFaker\io\{
    Reader, Writer
};

/**
 * Dump obfuscator read from reader, change private data and write to writer
*/
class DumpObfuscator 
{
    /* @var Reader $reader */
    protected $reader;
    /* @var Writer $writer */
    protected $writer;
    protected $faker;

    protected const STATE_INIT = 1;
    protected const STATE_TABLE_NAME = 2;
    protected const STATE_FIELDS = 3;
    protected const STATE_VALUES = 4;

    public function __construct(Reader $reader, Writer $writer) 
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    /**
     * Starts the rewriting process
    */
    public function run() 
    {
        $table = '';
        $reader = $this->reader; $writer = $this->writer;

        $state = self::STATE_INIT;
        while ($reader->valid()) {
            $line = $reader->readLine();
            $writer->writeLine($line);
            continue;//todo parse sql

            // $position = 0;
            // if ($state == self::STATE_INIT) {
            //     $position = mb_stripos($line, 'INSERT INTO');
            //     if ($position === false) {
            //         $writer->writeLine($line);
            //     } else {
            //         $state = self::STATE_TABLE_NAME;
            //     }
            // }

            // if ($state == self::STATE_TABLE_NAME) {
            //     for ($i = $position, $len = mb_strlen($line); $i < $line; $i++) {
            //         if (!ctype_space($line[$i])) {
            //             break;
            //         }
            //     }

            //     for (; $i < $len; $i++) {
            //         if (!ctype_space($line[$i])) {
            //             $table .= $line[$i];
            //         } else {
            //             $state = self::STATE_FIELDS;
            //         }
            //     }

            //     $position = $i;
            // }

            // if ($state == self::STATE_FIELDS) {
            //     if (!$fields) {
            //         $start = mb_stripos($line, '(', $position);
            //         $end =  mb_stripos($line, ')', $position);
            //     } else {
            //         $start = false;
            //         $end = mb_stripos($line, ')', $position);
            //     }

            //     if ($start !== false && $end !== false) {
            //         $fields = expand(',', mb_substr($line, $start, $end - $start + 1));
            //         $state = self::STATE_VALUES;
            //     } elseif ($end === false) {
            //         //check that values not found now
            //         if (mb_stripos($line, 'VALUES', $position) !== false) {
            //             //generate insert into command write to file
            //             $state = self::STATE_INIT;
            //         } else {
            //             $fields .= mb_substr($line, $start ?: 0);
            //         }
            //     } elseif ($end !== false) {
            //         $fields .= mb_substr($line, 0, $end);
            //         $fields = expand(',', $fields);
            //         $state = self::STATE_VALUES;
            //     }
            // }

            // if ($state == self::STATE_VALUES) {
                
            // }
        }
    }
}