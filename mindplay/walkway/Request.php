<?php

namespace mindplay\walkway;

/**
 * This class represents a Request for a URL from a (root) Route.
 */
class Request
{
  /**
   * @var Route|null the resolved Route; or null if the URL was unresolved
   */
  public $route;

  /**
   * @var string the resolved URL
   */
  public $url;

  /**
   * @var Closure|null the resolved HTTP method-handler (as provided by a Route)
   * @see execute()
   * @see Route::$methods
   */
  public $method;

  /**
   * @param Route $route
   * @param string $url
   * @param string $method
   */
  public function __construct(Route $route, $url, $method='get')
  {
    $this->url = $url;

    $route = $route->resolve($url);

    if ($route) {
      $this->method = $route->__get($method);
      if ($this->method) {
        echo "request resolved\n";
        $this->route = $route;
        $this->url = $route->url;
      } else {
        echo "undefined method: {$method}\n";
      }
    } else {
      echo "no match\n";
    }
  }

  /**
   * @return bool true if an HTTP method-handler is present and was executed; otherwise false.
   */
  public function execute()
  {
    if ($this->method) {
      $this->route->invoke($this->method);
      return true;
    }

    return false;
  }
}
