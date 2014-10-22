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
    /**
     * @var Closure[] map where full regular expression => substitution closure
     * @see preparePattern()
     * @see init()
     */
    public $substitutions = array();

    /**
     * @var string[] map where symbol name => partial regular expression
     * @see init()
     */
    public $symbols = array();

    /**
     * @var Closure event-handler for diagnostic messages
     */
    public $onLog;

    public function __construct()
    {
        $this->module = $this;

        $this->init();
    }

    /**
     * @param string $pattern unprocessed pattern
     *
     * @return string pre-processed pattern
     *
     * @throws RoutingException
     */
    public function preparePattern($pattern)
    {
        foreach ($this->substitutions as $subpattern => $fn) {
            $pattern = @preg_replace_callback($subpattern, $fn, $pattern);

            if ($pattern === null) {
                throw new RoutingException("invalid substitution pattern: {$subpattern}");
            }
        }

        return $pattern;
    }

    /**
     * Initialize Routes after construction - override as needed.
     */
    protected function init()
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
}
