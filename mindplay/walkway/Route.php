<?php

/**
 * Walkway
 * =======
 *
 * A modular router for PHP.
 *
 * @author Rasmus Schultz <http://blog.mindplay.dk>
 * @license GPL3 <http://www.gnu.org/licenses/gpl-3.0.txt>
 */

namespace mindplay\walkway;

use Closure;
use ArrayAccess;
use ReflectionFunction;
use ReflectionParameter;

/**
 * This class represents an individual Route: a part of a path (referred to as a token)
 * and a set of patterns to be matched and mapped against nested Route definition-functions.
 *
 * It implements ArrayAccess as the method for defining patterns/functions.
 *
 * It also implements a collection of HTTP method-handlers (e.g. GET, PUT, POST, DELETE)
 * which can be defined and accessed via e.g. <code>$get</code> and other properties.
 *
 * @property Closure|null $get
 * @property Closure|null $head
 * @property Closure|null $post
 * @property Closure|null $put
 * @property Closure|null $delete
 */
class Route implements ArrayAccess
{
    /**
     * @var mixed[] map of parameter-names to values collected during traversal
     */
    public $vars;

    /**
     * @var Module the Module to which this Route belongs
     */
    public $module;

    /**
     * @var Route|null parent Route; or null if this is the root-Route.
     */
    public $parent;

    /**
     * @var string the token that was matched when this Route was constructed.
     */
    public $token;

    /**
     * @var string the (partial) path associated with this Route.
     */
    public $path;

    /**
     * @var Closure[] map of patterns to Route definition-functions
     * @see resolve()
     */
    protected $patterns = array();

    /**
     * @var Closure[] map of method-names to functions
     * @see Request::execute()
     */
    protected $methods = array();

    /**
     * @param $module Module owner Module
     * @param $parent Route|null parent Route; or null if this is a root-Route.
     * @param $token string the token (partial path) that was matched when this Route was constructed.
     * @param $vars mixed[] list of named values
     */
    public function __construct(Module $module, Route $parent = null, $token = '', $vars = array())
    {
        $this->module = $module;
        $this->parent = $parent;
        $this->token = $token;

        $this->path = ($parent === null || $parent->path === '')
            ? $token
            : "{$parent->path}/{$token}";

        $this->vars = $vars;
        $this->vars['route'] = $this;
        $this->vars['module'] = $this->module;
    }

    /**
     * @param string $message
     * @see $onLog
     */
    public function log($message)
    {
        if ($log = $this->module->onLog) {
            $log($message);
        }
    }

    /**
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($pattern, $init)
    {
        $this->log("define pattern: {$pattern}");
        $this->patterns[$pattern] = $init;
    }

    /**
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($pattern)
    {
        return isset($this->patterns[$pattern]);
    }

    /**
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($pattern)
    {
        unset($this->patterns[$pattern]);
    }

    /**
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($pattern)
    {
        return $this->patterns[$pattern];
    }

    /**
     * @param string $name
     * @return Closure
     */
    public function __get($name)
    {
        $name = strtolower($name);
        
        return isset($this->methods[$name]) ? $this->methods[$name] : null;
    }
    
    /**
     * @param string $name
     * @param Closure $value
     */
    public function __set($name, $value)
    {
        $this->log("define method: {$name}");
        $name = strtolower($name);
        $this->methods[$name] = $value;
    }

    /**
     * Follow a (relative) path, walking from this Route to a destination Route.
     *
     * @param $path string relative path
     *
     * @return Route|null returns the resolved Route, or null if no Route was matched
     *
     * @throws RoutingException if a bad Route is encountered
     */
    public function resolve($path)
    {
        /** @var string $part partial path being resolved in the current iteration */
        $part = trim($path, '/'); // trim leading/trailing slashes

        /** @var bool $matched indicates whether the last partial path matched a pattern */
        $matched = true; // assume success (empty path will successfully resolve as root)

        /** @var Route $route the current route (switches as we walk through each token in the path) */
        $route = $this; // track the current Route, starting from $this

        $iteration = 0;

        while ($part) {
            $iteration += 1;

            $this->log("* resolving partial path '{$part}' (iteration {$iteration} of path '{$path}')");

            if (count($route->patterns) === 0) {
                $this->log("end of routes - no match found");
                return null;
            }

            $matched = false; // assume failure

            foreach ($route->patterns as $pattern => $init) {
                /**
                 * @var string $pattern the pattern, with substitutions applied
                 * @var Closure $init route initialization function
                 */

                // apply pattern-substitutions:
                
                foreach ($this->module->substitutions as $subpattern => $sub) {
                    $pattern = preg_replace_callback($subpattern, $sub, $pattern);
                    
                    if ($pattern === null) {
                        throw new RoutingException("invalid substitution pattern: '{$subpattern}' (preg_replace_callback returned null)", $init);
                    }
                }
                
                $this->log("testing pattern '{$pattern}'");

                /** @var int|bool $match result of preg_match() against $pattern */
                $match = @preg_match('#^' . $pattern . '(?=$|/)#i', $part, $matches);

                if ($match === false) {
                    throw new RoutingException("invalid pattern '{$pattern}' (preg_match returned false)", $init);
                }

                if ($match !== 1) {
                    continue; // this pattern was not a match - continue with the next pattern
                }

                $matched = true;

                /** @var string $token the matched token */
                $token = array_shift($matches);

                $this->log("token '{$token}' matched by pattern '{$pattern}'");

                // look for a Module type-hint in the arguments:

                /** @var ReflectionFunction $ref reflection of the Route initialization-function */
                $ref = new ReflectionFunction($init);

                /** @var ReflectionParameter $mod_param reflection of Module-type to be injected */
                $mod_param = null;

                foreach ($ref->getParameters() as $param) {
                    if ($param->getClass() && $param->getClass()->isSubClassOf(__NAMESPACE__ . '\\Module')) {
                        $mod_param = $param;
                        break;
                    }
                }

                if ($mod_param) {
                    // switch to the Module found in the arguments:

                    /* @var string $class intermediary variable holding the class-name of a Module-type to be injected */
                    $class = $mod_param->getClass()->name;

                    $this->log("switching to Module: {$class}");

                    $route = new $class($route, $token);

                    $route->vars[$mod_param->name] = $route;
                } else {
                    // switch to a nested Route:

                    $route = new Route($route->module, $route, $token, $route->vars);
                }

                /** @var string[] $values list of nameless substrings captured in regular expression */
                $values = array();

                // identify named variables or nameless values:

                /** @var int $named_vars number of named substrings captured in regular expression */
                $named_vars = 0;

                foreach ($matches as $key => $value) {
                    if (is_int($key)) {
                        $values[] = $value;
                    } else {
                        $this->log("captured named variable '{$key}' as '{$value}'");
                        $route->vars[$key] = $value;
                        $named_vars += 1;
                    }
                }

                if ($named_vars > 0) {
                    if ($named_vars !== count($values)) {
                        throw new RoutingException("invalid pattern '{$pattern}' (mix of nameless and named substring captures)", $init);
                    }

                    $values = array();
                }

                // initialize the nested Route:

                if ($route->invoke($init, $values) === false) {
                    // the function explicitly aborted the route
                    $this->log("aborted");
                    return null;
                }

                break; // skip any remaining patterns
            }

            if (isset($token)) {
                // remove previous token from remaining part of path:
                $part = substr($part, strlen($token) + 1);
            } else {
                break;
            }
        }

        return $matched ? $route : null;
    }

    /**
     * @param $method string name of HTTP method-handler to execute (e.g. 'get', 'put', 'post', 'delete', etc.)
     * @return mixed|bool the value returned by the HTTP method-handler; true if the method-handler returned
     *                    no value - or false if the method-handler was not found (or returned false)
     */
    public function execute($method = 'get')
    {
        $func = $this->__get($method);

        if ($func === null) {
            return false; // method-handler not found
        }

        $result = $this->invoke($func);

        return $result === null
            ? true // method-handler executed but returned no value
            : $result; // method-handler returned a result
    }

    /**
     * Invoke a function using variables collected during traversal, while filling
     * any missing parameters with values from a given set of nameless parameters -
     * as parameters are identified, they are added to the list of named variables.
     *
     * @param Closure $func the function to be invoked.
     * @param mixed[] $values additional nameless values to be identified
     * @return mixed the value returned by the invoked function
     * @throws InvocationException
     * @see $vars
     */
    protected function invoke($func, $values = array())
    {
        /**
         * @var $value_index int the index of the next nameless value to use from $values
         * @var $params mixed[] the list of parameters to be applied to $func
         * @var $last_index int the index of the last nameless value
         */

        $fn = new ReflectionFunction($func);

        $value_index = -1;

        $params = array();

        $last_index = count($values) - 1;

        foreach ($fn->getParameters() as $param) {
            if (array_key_exists($param->name, $this->vars)) {
                // fill parameter using named value:
                $params[] = $this->vars[$param->name];
            } else {
                // fill parameter using nameless value:
                $value_index++;
                if ($value_index > $last_index) {
                    throw new InvocationException('insufficient nameless values to fill the parameter-list', $func);
                }
                $params[] = $values[$value_index];
                // add to list of named values:
                $this->vars[$param->name] = $values[$value_index];
            }
        }

        if ($value_index !== $last_index) {
            $error = $value_index - $last_index;
            
            throw new InvocationException('wrong parameter-count: ' . abs($error) . ' too ' . ($error>0 ? 'many' : 'few'), $func);
        }

        return call_user_func_array($func, $params);
    }
    
    /*
    public function __destruct()
    {
        unset($this->module);
        unset($this->parent);
        
        foreach ($this->vars as $key => $value) {
            unset($this->vars[$key]);
        }
        
        unset($this->vars);
        
        foreach ($this->patterns as $key => $value) {
            unset($this->patterns[$key]);
        }
        
        unset($this->patterns);
        
        foreach ($this->methods as $key => $value) {
            unset($this->methods[$key]);
        }
        
        unset($this->methods);
        
        echo "- OUT OF SCOPE -\n";
    }
    */
}
