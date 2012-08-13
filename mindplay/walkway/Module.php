<?php

namespace mindplay\walkway;

/**
 * This class represents the root of an independent collection of Routes.
 */
class Module extends Route
{
  public function __construct(Module $parent = null)
  {
    parent::__construct($this);
  }
}
