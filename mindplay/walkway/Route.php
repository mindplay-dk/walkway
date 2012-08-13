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

/**
 * This class represents an individual Route: a URL token, and a set of URL-patterns
 * mapped to nested Route definition-functions.
 *
 * It implements ArrayAccess as the method for defining patterns/functions.
 *
 * It also implements a collection of HTTP method-handlers (e.g. GET, PUT, POST, DELETE)
 * which can be defined and accessed using get/set magic methods.
 *
 * @property Closure get
 * @property Closure head
 * @property Closure post
 * @property Closure put
 * @property Closure delete
 */
class Route implements ArrayAccess
{
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
   * @var mixed[] map of parameter-names to values collected during traversal
   */
  protected $vars;
  
  /**
   * @param $module Module owner Module
   * @param $parent Route|null parent Route; or null if this is a root-Route.
   * @param $token string the URL token that was matched when this Route was constructed.
   * @param $vars mixed[] list of named values
   */
  public function __construct(Module $module, Route $parent=null, $token='', $vars=array())
  {
    $this->module = $module;
    $this->parent = $parent;
    $this->token = $token;
    
    $this->url = ($parent === null || $parent->url === '')
      ? $token
      : "{$parent->url}/{$token}";
    
    $this->vars = $vars;
    $this->vars['route'] = $this;
  }

  public function offsetSet($pattern, $init) {
    echo "define pattern: {$pattern}\n";
    $this->patterns[$pattern] = $init;
  }

  public function offsetExists($pattern) {
    return isset($this->patterns[$pattern]);
  }

  public function offsetUnset($pattern) {
    unset($this->patterns[$pattern]);
  }

  public function offsetGet($pattern) {
    return $this->patterns[$pattern];
  }
  
  public function __get($name)
  {
    $name = strtolower($name);
    return isset($this->methods[$name]) ? $this->methods[$name] : null;
  }

  public function __set($name, $value)
  {
    echo "define method: {$name}\n";
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
    $tokens = is_array($url)
      ? $url
      : array_filter(explode('/', trim($url,'/')), 'strlen');

    $matched = true;

    $route = $this;

    foreach ($tokens as $index => $token) {
      echo "* resolve token {$index}: {$token}\n";

      $patterns = $route->patterns;

      if (count($patterns) === 0) {
        echo "dead end\n";
        return null;
      }

      $matched = false;

      foreach ($patterns as $pattern => $init) {
        echo "testing pattern: $pattern\n";
        $match = preg_match('/^'.$pattern.'$/i', $token, $values);

        if ($match === false) {
          throw new RoutingException('invalid pattern "' . $pattern, $init);
        }

        if ($match === 1) {
          echo "pattern matched\n";

          $matched = true;
          
          $route = new Route($this->module, $route, $token, $route->vars);
          
          array_shift($values);
          
          if ($route->invoke($init, $values) === false) {
            echo "aborted\n";
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
  public function execute($method='get')
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
  protected function invoke($func, $values=array())
  {
    /**
     * @var $value_index int the index of the next nameless value to use from $values
     * @var $params mixed[] the list of parameters to be applied to $func
     * @var $last_index int the index of the last nameless value
     */

    $fn = new ReflectionFunction($func);

    $value_index = -1;

    $params = array();

    $last_index = count($values)-1;

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
      throw new InvocationException('wrong parameter-count: ' . abs($error) . ' too ' . ($error>0 ? 'many' : 'few'), $func);
    }

    return call_user_func_array($func, $params);
  }
}
