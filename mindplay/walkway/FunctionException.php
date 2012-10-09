<?php

namespace mindplay\walkway;

use Closure;
use Exception;
use ReflectionFunction;

/**
 * Base-class for Exception-types (possibly related to an anonymous function)
 */
abstract class FunctionException extends Exception
{
    /**
     * @param string $message the error-message
     * @param Closure|null $func an anonymous function related to the error-message
     */
    public function __construct($message, Closure $func = null)
    {
        if ($func !== null) {
            $fn = new ReflectionFunction($func);
            $source = $fn->getFileName() . '#' . $fn->getStartLine();
            $message = $message . ' at ' . $source;
        }

        parent::__construct($message);
    }
}
