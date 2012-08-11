<?php

namespace mindplay\walkway;

use Closure;
use Exception;
use ReflectionFunction;

/**
 * Base-class for Exception-types related to an anonymous function
 */
abstract class FunctionException extends Exception
{
  /**
   * @param string $message the error-message
   * @param Closure $func the anonymous function related to the error-message
   */
  public function __construct($message, Closure $func)
  {
    $fn = new ReflectionFunction($func);
    $source = $fn->getFileName() . '#' . $fn->getStartLine();
    parent::__construct($message . ' at ' . $source);
  }
}
