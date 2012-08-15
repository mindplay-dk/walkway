<?php

namespace mindplay\walkway;

/**
 * This class represents the root of an independent collection of Routes.
 */
class Module extends Route
{
  public function __construct(Route $parent = null)
  {
    if ($parent === null) {
      parent::__construct($this);
    } else {
      parent::__construct($this, $parent->parent, $parent->token);
    }

    $this->init();
  }

  public function init()
  {}
}
