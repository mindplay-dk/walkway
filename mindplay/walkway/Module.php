<?php

namespace mindplay\walkway;

/**
 * This class represents an independent collection of routes with a root.
 */
class Module // implements ArrayAccess
{
  /**
   * @var Route the root-Route of this Module
   */
  public $route;
  
  /**
   * @var Module|null the parent Module; or null if this is the root-Module
   */
  public $parent;
  
  /**
   * @var mixed[] map of parameter-names to values collected during traversal
   */
  #protected $vars = array();
  
  public function __construct(Module $parent = null)
  {
    $this->parent = $parent;
    $this->route = new Route($this);
  }
  /*
  public function offsetSet($name, $value) {
    $this->vars[$name] = $value;
  }

  public function offsetExists($name) {
    return array_key_exists($name, $this->vars);
  }

  public function offsetUnset($name) {
    unset($this->vars[$name]);
  }

  public function offsetGet($name) {
    return array_key_exists($name, $this->vars) ? $this->vars[$name] : null;
  }*/
}
