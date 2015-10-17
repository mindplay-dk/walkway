<?php

namespace mindplay\walkway;

use Closure;
use ReflectionFunction;

/**
 * This is the default invoker implementation, which is only capable of resolving
 * parameters directly provided.
 */
class Invoker implements InvokerInterface
{
    public function invoke(Closure $func, array $params)
    {
        $func_ref = new ReflectionFunction($func);
        $param_refs = $func_ref->getParameters();

        $args = array();

        foreach ($param_refs as $param_ref) {
            if (array_key_exists($param_ref->name, $params)) {
                $args[] = $params[$param_ref->name];

                continue;
            }

            if ($param_ref->isDefaultValueAvailable()) {
                $args[] = $param_ref->getDefaultValue();

                continue;
            }

            throw new InvocationException("missing parameter: \${$param_ref->name}", $func);
        }

        return call_user_func_array($func, $args);
    }
}
