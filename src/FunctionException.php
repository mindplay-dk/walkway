<?php

namespace mindplay\walkway;

use Closure;
use Exception;
use ReflectionFunction;

/**
 * Base-class for exception types, optionally adding some helpful diagnostic information
 * about a user-defined closure that caused a problem.
 */
abstract class FunctionException extends Exception
{
    /**
     * @param string       $message the error-message
     * @param Closure|null $func    an anonymous function related to the error-message
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
