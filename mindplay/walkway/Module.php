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
        $module = $this;
        
        // define a default pattern-substitution with support for some common symbols:
        
        $this->substitutions['/(?<!\(\?)<([^\:]+)\:([^>]+)>/'] = function($matches) use ($module) {
            if (isset($module->symbols[$matches[2]])) {
                $matches[2] = $module->symbols[$matches[2]];
            }
            
            return "(?<{$matches[1]}>{$matches[2]})";
        };
        
        // define common symbols for the default pattern-substitution:
        
        $this->symbols = array(
            'int' => '\d+',
            'slug' => '[a-z0-9-]+',
        );
    }
    
    /**
     * @var Closure event-handler for diagnostic messages
     */
    public $onLog;
    
    /**
     * @var Closure[] hash where full regular expression => substitution closure
     * @see init()
     * @see preg_replace_callback()
     */
    public $substitutions = array();
    
    /**
     * @var string[] hash where symbol name => partial regular expression
     * @see init()
     */
    public $symbols = array();
}
