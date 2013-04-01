<?php

namespace mindplay\walkway;

/**
 * This class represents the root of an independent collection of Routes.
 *
 * @see Route
 */
class Module extends Route
{
    public function __construct(Route $parent = null, $token = '')
    {
        parent::__construct($this, $parent, $token);

        $this->init();
    }

    /**
     * Initialize Routes after construction - override as needed.
     */
    public function init()
    {
    }
    
    /**
     * @var Closure event-handler for diagnostic messages
     */
    public $onLog;
}
