<?php

/**
 * Walkway
 * =======
 *
 * A modular router for PHP.
 *
 * @version 0.1
 * @author Rasmus Schultz <http://blog.mindplay.dk>
 * @license GPL3 <http://www.gnu.org/licenses/gpl-3.0.txt>
 */

namespace mindplay\walkway;

use Closure;
use ArrayAccess;
use ReflectionFunction;
use ReflectionParameter;

/**
 * This class represents an individual Route: a URL token, and a set of URL-patterns
 * mapped to nested Route definition-functions.
 *
 * It implements ArrayAccess as the method for defining patterns/functions.
 *
 * It also implements a collection of HTTP method-handlers (e.g. GET, PUT, POST, DELETE)
 * which can be defined and accessed using get/set magic methods.
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
     * @var string the (partial) URL associated with this Route.
     */
    public $url;

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
     * @param $token string the URL token that was matched when this Route was constructed.
     * @param $vars mixed[] list of named values
     */
    public function __construct(Module $module, Route $parent = null, $token = '', $vars = array())
    {
        $this->module = $module;
        $this->parent = $parent;
        $this->token = $token;

        $this->url = ($parent === null || $parent->url === '')
            ? $token
            : "{$parent->url}/{$token}";

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
     * Follow a (relative) URL, walking the path from this Route to a destination Route.
     *
     * @param $url string relative URL
     * @return Route|null returns the resolved Route, or null if no Route was matched
     * @throws RoutingException if a bad Route is encountered
     */
    public function resolve($url)
    {
        /**
         * @var $tokens string[] list of URL tokens
         * @var $matched bool indicates whether the tokens matched or not
         * @var $route Route the current route (switches as we walk the URL tokens)
         * @var $match int|bool result of preg_match() against a defined pattern
         * @var $ref ReflectionFunction reflection of the Route initialization-function
         * @var $mod_param ReflectionParameter reflection of Module-type to be injected
         * @var $class string intermediary variable holding the class-name of a Module-type to be injected
         * @var $named_vars int number of named substrings captured in regular expression
         * @var $values string[] list of nameless substrings captured in regular expression
         */

        $tokens = is_array($url)
            ? $url
            : array_filter(explode('/', rtrim($url, '/')), 'strlen');

        $matched = true;

        $route = $this; // track the current Route, starting from $this

        foreach ($tokens as $index => $token) {
            $this->log("* resolve token {$index}: {$token}");

            if ($token === '..') {
                if ($this->parent === null) {
                    throw new RoutingException("invalid relative URL: {$url} - token {$index} has no parent");
                }
                $route = $route->parent;
            } else if ($token === '.') {
                continue; // continue from current Route
            } elseif ($token === '') {
                $route = $route->module;
                continue; // continue from the root-Route of the Module
            }

            if (count($route->patterns) === 0) {
                $this->log("dead end");
                return null;
            }

            $matched = false;

            foreach ($route->patterns as $pattern => $init) {
                // apply pattern-substitutions:
                
                foreach ($this->module->substitutions as $subpattern => $sub) {
                    $pattern = preg_replace_callback($subpattern, $sub, $pattern);
                    
                    if ($pattern === null) {
                        throw new RoutingException('invalid substitution pattern: ' . $subpattern . ' (preg_replace_callback returned null)', $init);
                    }
                }
                
                $this->log("testing pattern: $pattern");
                
                $match = preg_match('/^' . $pattern . '$/i', $token, $matches);

                if ($match === false) {
                    throw new RoutingException('invalid pattern: ' . $pattern . ' (preg_match returned false)', $init);
                }

                if ($match === 1) {
                    $this->log("pattern matched");

                    $matched = true;

                    $ref = new ReflectionFunction($init);

                    $mod_param = null;

                    foreach ($ref->getParameters() as $param) {
                        if ($param->getClass() && $param->getClass()->isSubClassOf(__NAMESPACE__ . '\\Module')) {
                            $mod_param = $param;
                            break;
                        }
                    }

                    if ($mod_param) {
                        $class = $mod_param->getClass()->name;
                        $this->log("switching to Module: $class");
                        $route = new $class($route, $token);
                        $route->vars[$mod_param->name] = $route;
                    } else {
                        $route = new Route($route->module, $route, $token, $route->vars);
                    }
                    
                    array_shift($matches);
                    
                    $values = array();
                    
                    $named_vars = 0;
                    
                    foreach ($matches as $key => $value) {
                        if (is_int($key)) {
                            $values[] = $value;
                        } else {
                            $this->log('captured named subtring: ' . $key);
                            $route->vars[$key] = $value;
                            $named_vars += 1;
                        }
                    }
                    
                    if ($named_vars > 0) {
                        if ($named_vars !== count($values)) {
                            throw new RoutingException('invalid pattern: ' . $pattern . ' (mix of nameless and named substring captures)', $init);
                        }
                        
                        $values = array();
                    }

                    if ($route->invoke($init, $values) === false) {
                        $this->log("aborted");
                        return null;
                    }

                    break;
                }
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
                    var_dump(array_keys($this->vars), $param->name);
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
