<?php

/**
 * BracessStringValidator check for balanced braces in a string
*/
class BracesStringValidator 
{
    /**
     * @var array $braces - array where the key is an open brace 
     * and the value is a closed brace
    */
    protected static $braces = [
        '(' => ')',
        '[' => ']',
        '<' => '>',
        '{' => '}',
    ];

    /**
     * validate - validating function
     * @param string $str
     * @return int by the terms of the task we return int not bool
    */
    public static function validate(string $str): int 
    {
        //for utf-8 support https://stackoverflow.com/questions/3666306/how-to-iterate-utf-8-string-in-php
        $str = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        $stack = new SplStack();
        $openToClosedBraces = self::$braces;
        $closedToOpenBraces = array_flip($openToClosedBraces);
        foreach ($str as $chr) {
            if (isset($openToClosedBraces[$chr])) { //add open brace to stack
                $stack->push($chr);
            } elseif (isset($closedToOpenBraces[$chr])) { //check that for closed brace we have open brace in stack
                if (!$stack->isEmpty() && $stack->top() === $closedToOpenBraces[$chr]) {
                    $stack->pop();//if ok - goto next closed brace
                } else {
                    return 1;
                }
            }
        }

        return (int) !$stack->isEmpty();//if stack is not empty - there is some open brace in stack
    }
}

/**
 * Simple teset
*/
function test()
{
    $cases = [
        '---(++++)----' => 0,
        '' => 0,
        'before ( middle []) after ' => 0,
        ') (' => 1,
        '} {' => 1,
        '<(   >)' => 1,
        '(  [  <>  ()  ]  <>  )' => 0,
        '   (      [)' => 1,
        '<([{[]}])>' => 0,
        '<p>Hello</p>' => 0,
        '<(_{_)>' => 1
    ];

    foreach ($cases as $str => $exp) {
        $got = BracesStringValidator::validate($str);
        echo "For $str expected $exp got $got " . ($exp === $got ? 'OK' : 'Error') . "\n";
    }
}

test();