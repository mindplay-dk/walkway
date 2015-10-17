<?php

namespace mindplay\walkway;

use Closure;

/**
 * This interface defines the call signature of a component capable of
 * invoking a function, given a set of named parameters.
 *
 * By implementing this interface, you can create a custom invoker to
 * provide integration with e.g. a dependency injection container.
 */
interface InvokerInterface
{
    /**
     * @param Closure $func   the Closure to be invoked
     * @param array   $params map where parameter name => value
     *
     * @return mixed return value from the invoked function
     *
     * @throws InvocationException on missing parameter
     */
    public function invoke(Closure $func, array $params);
}
