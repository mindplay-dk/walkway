<?php

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
     * A map of regular expression pattern substitutions to apply to every
     * pattern encountered, as a means fo pre-processing patterns. Provides a
     * useful means of adding your own custom patterns for convenient reuse.
     *
     * @var Closure[] map where full regular expression => substitution closure
     *
     * @see $symbols
     * @see preparePattern()
     * @see init()
     */
    public $substitutions = array();

    /**
     * Symbols used by the built-in standard substitution pattern, which provides
     * a convenient short-hand syntax for placeholder tokens. The built-in standard
     * symbols are:
     *
     * <pre>
     *     'int'  => '\d+'
     *     'slug' => '[a-z0-9-]+'
     * </pre>
     *
     * Which provides support for simplified named routes, such as:
     *
     * <pre>
     *     'user/<user_id:int>'
     *     'tags/<tag:slug>'
     * </pre>
     *
     * For which the resulting patterns would be:
     *
     * <pre>
     *     'user/(?<user_id:\d+>)'
     *     'tags/(?<slug:[a-z0-9-]>)'
     * </pre>
     *
     * @var string[] map where symbol name => partial regular expression
     *
     * @see init()
     */
    public $symbols = array();

    /**
     * @var Closure|null event-handler for diagnostic messages
     */
    public $onLog = null;

    /**
     * @var InvokerInterface
     */
    public $invoker;

    /**
     * @param InvokerInterface $invoker
     */
    public function __construct(InvokerInterface $invoker = null)
    {
        $this->module = $this;
        $this->invoker = $invoker ?: $this->createDefaultInvoker();

        $this->init();
    }

    /**
     * Prepares a regular expression pattern by applying the patterns and callbacks
     * defined by {@link $substitutions} to it.
     *
     * @param string $pattern unprocessed pattern
     *
     * @return string pre-processed pattern
     *
     * @throws RoutingException if the regular expression fails to execute
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
     * Initialize the Module upon construction - override as needed.
     */
    protected function init()
    {
        $module = $this;

        $this->vars['route'] = $this;
        $this->vars['module'] = $this;

        // define a default pattern-substitution with support for some common symbols:

        $this->substitutions['/(?<!\(\?)<([^\:]+)\:([^>]+)>/'] = function ($matches) use ($module) {
            if (isset($module->symbols[$matches[2]])) {
                $matches[2] = $module->symbols[$matches[2]];
            }

            return "(?<{$matches[1]}>{$matches[2]})";
        };

        // define common symbols for the default pattern-substitution:

        $this->symbols = array(
            'int'  => '\d+',
            'slug' => '[a-z0-9-]+',
        );
    }

    /**
     * @return InvokerInterface
     */
    protected function createDefaultInvoker()
    {
        return new Invoker();
    }
}
