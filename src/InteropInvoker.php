<?php

namespace mindplay\walkway;

use Closure;
use Interop\Container\ContainerInterface;
use ReflectionFunction;

/**
 * This is an invoker implementation that attempts to resolve parameters directly
 * provided first, then attempts to have a dependency injection container resolve
 * the argument by type.
 *
 * This provides integration with a number of DI containers via `container-interop`:
 *
 * https://github.com/container-interop/container-interop#compatible-projects
 */
class InteropInvoker implements InvokerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

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

            $argument_type = $param_ref->getClass();

            if ($argument_type && $this->container->has($argument_type->name)) {
                $args[] = $this->container->get($argument_type->name);

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
