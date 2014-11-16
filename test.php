<?php

header('Content-type: text/plain');

#define('NOISY', true); // uncomment to enable diagnostic messages

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require __DIR__ . '/vendor/autoload.php';
$autoloader->addPsr4('mindplay\walkway\\', __DIR__ . '/mindplay/walkway');

set_error_handler(
    function($errno, $errstr, $errfile, $errline) {
        $error = new ErrorException($errstr, 0, $errno, $errfile, $errline);

        if ($error->getSeverity() & error_reporting()) {
            throw $error;
        }
    }
);

use mindplay\walkway\Route;
use mindplay\walkway\Module;
use mindplay\walkway\RoutingException;

// Define a reusable Module:

class CommentModule extends Module
{
    public function init()
    {
        parent::init();

        $this['submit'] = function (Route $route) {
            $route->get = function (Route $route, Module $module) {
                $route->log("displaying comment submission form");
                $route->log("module: " . get_class($module));
                $route->log("module: " . get_class($route->module));
                $route->log("module parent: " . get_class($route->module->parent));
                $route->log("module parent path: " . $module->parent->path);
                $route->log("module path: " . $module->path);

                return 'comment form';
            };
        };

        $this->get = function (Route $route, Module $module) {
            $route->log("displaying comments!");
            $route->log("current module: " . get_class($module));
            $route->log("module path: " . $module->path);
            $route->log("module parent path: " . $module->parent->path);
            $route->log("route path: " . $route->path);
            $route->log("route parent path: " . $route->parent->path);

            return 'comments';
        };
    }
}

// Configure the main Module:

$module = new Module;

if (defined('NOISY')) {
    $module->onLog = function($message) {
        echo "- {$message}\n";
    };
}

$module['blog'] = function ($route) {
    $route['tags/<tag:slug>'] = function (Route $route) {
        $route->get = function ($tag) {
            return $tag;
        };
    };

    $route['posts'] = function ($route) {
        $route['<post_id:int>'] = function (Route $route, $post_id) {
            if ($post_id == 99) {
                $route->abort(); // access denied!
            }

            $route['edit'] = function (Route $route) {
                $route->get = function ($post_id, Route $route) {
                    $route->log("editing post {$post_id}!");
                    $route->log("display post path: {$route->parent->path}");

                    return compact('post_id');
                };
            };

            $route->get = function (Route $route, $post_id) {
                $route->log("displaying post number {$post_id}!");
            };

            $route['comments'] = function (Route $route) {
                $route->log("delegating control to CommentModule");
                $route->log("available vars: " . implode(', ', array_keys($route->vars)));

                $route->delegate(new CommentModule());
            };
        };

        $route['<year:int>-<month:int>'] = function (Route $route) {
            $route['page<page:int>'] = function (Route $route) {
                $route->get = function (Route $route, $page, $year, $month) {
                    $route->log("showing page {$page} of posts for year {$year} month {$month}");

                    return compact('year', 'month', 'page');
                };
            };

            $route->get = function (Route $route, $year, $month) {
                $route->log("showing posts from year {$year} month {$month}!");

                return compact('year', 'month');
            };
        };
    };
};

$module->get = function () {
    return 'hello';
};

// Configure code coverage:

if (coverage()) {
    $filter = coverage()->filter();

    $filter->addDirectoryToWhitelist(__DIR__ . '/mindplay/walkway');
    $filter->removeFileFromWhitelist(__DIR__ . '/mindplay/walkway/RoutingException.php');
    $filter->removeFileFromWhitelist(__DIR__ . '/mindplay/walkway/InvocationException.php');

    coverage()->start('test');
}

// Run tests:

test(
    'Can define routing functions',
    function () {
        $module = new Module();

        $fn = function () {};

        $module['foo'] = $fn;

        eq($module['foo'], $fn, 'routing function defined');

        ok(isset($module['foo']), 'routing function is set');

        ok(! isset($module['bar']), 'route function not set');

        unset($module['foo']);

        ok(! isset($module['foo']), 'routing function unset');

        ok($module->resolve('foo') === null, 'no routes to resolve');
    }
);

test(
    'Can resolve routes',
    function () use ($module) {
        $route = $module->resolve('/');

        ok($route instanceof Route, 'should resolve to a Route instance');

        eq($route, $module, 'resolves the root as the module itself');

        eq($route->execute('get'), 'hello', 'returns expected result');

        eq($module->resolve('foo'), null, 'returns null for undefined path');

        eq($module->resolve('foo/bar'), null, 'returns null for undefined nested path');

        $route = $module->resolve('blog/posts/42/edit');

        ok($route instanceof Route, 'resolves a valid multi-level path');

        eq($route->execute('get'), array('post_id' => '42'), 'returns expected result');

        $route = $module->resolve('blog/tags/foo-bar');

        ok($route instanceof Route, 'resolves a two-part path containing a slash');

        eq($route->execute('get'), 'foo-bar', 'returns the expected result');

    }
);

test(
    'Can capture substrings',
    function () use ($module) {
        $route = $module->resolve('blog/posts/2012-07');

        eq($route->execute('get'), array('year'=>'2012', 'month'=>'07'), 'returns multiple captured substrings');

        $route = $module->resolve('blog/posts/2012-07/page2');

        eq($route->execute('get'), array('year'=>'2012', 'month'=>'07', 'page'=>'2'), 'inherits captured substrings from parent routes');
    }
);

test(
    'Can handle Modules',
    function () use ($module) {
        $route = $module->resolve('blog/posts/88/comments');

        ok($route instanceof CommentModule, 'returns the expected module type', get_class($route));

        eq($route->execute('get'), 'comments', 'returns the expected result');

        $route = $module->resolve('blog/posts/66/comments/submit');

        ok($route->module instanceof CommentModule, 'returns the expected module type');

        eq($route->execute('get'), 'comment form', 'returns the expected result');
    }
);

test(
    'Can control routing behavior',
    function () use ($module) {
        ok($module->resolve('blog/posts/99') === null, 'can abort routing by returning false');

        ok($module->resolve('blog/posts/88') instanceof Route, 'correctly routes in other cases');
    }
);

test(
    'Can dispatch (http) methods',
    function () use ($module) {
        $route = $module->resolve('blog');

        ok($route instanceof Route, 'resolves the route');

        ok($route->execute('get') === false, 'but returns FALSE when no action is defined');
    }
);

test(
    'Log messages triggered',
    function () {
        $got_message = null;

        $route = new Module();
        $route->onLog = function ($message) use (&$got_message) {
            $got_message = $message;
        };

        $route->log('test');

        eq($got_message, 'test', 'log closure was triggered');
    }
);

test(
    'Throws exceptions',
    function () use ($module) {
        $route = new Module();
        $route->substitutions['///'] = function() {};
        $route['foo'] = function (Route $route) {};

        expect(
            'mindplay\walkway\RoutingException',
            'on invalid substitution pattern',
            function () use ($route) {
                $route->resolve('foo');
            }
        );

        $route = new Module();

        $route['foo/(\w+)'] = function (Route $route, $bar) {
            return $bar;
        };

        expect(
            'mindplay\walkway\RoutingException',
            'on attempted nameless substring capture',
            function () use ($route) {
                $result = $route->resolve('foo/bar');
            }
        );

        $route = new Module();

        $route['((('] = function (Route $route) {};

        expect(
            'mindplay\walkway\RoutingException',
            'when using a malformed regular expression',
            function () use ($route) {
                $result = $route->resolve('foo/bar');
            }
        );

        $route = new Module();

        $route['foo'] = function (Route $route, $bar) {};

        expect(
            'mindplay\walkway\InvocationException',
            'when a parameter cannot be satisfied',
            function () use ($route) {
                $route->resolve('foo');
            }
        );
    }
);

if (coverage()) {
    coverage()->stop();

    // output code coverage report to console:

    $report = new PHP_CodeCoverage_Report_Text(10, 90, false, false);

    echo $report->process(coverage(), false);

    // output code coverage report for integration with CI tools:

    $report = new PHP_CodeCoverage_Report_Clover();

    $report->process(coverage(), 'build/logs/clover.xml');
}

exit(status());

// https://gist.github.com/mindplay-dk/4260582

/**
 * @param string   $name     test description
 * @param callable $function test implementation
 */
function test($name, $function)
{
    echo "\n=== $name ===\n\n";

    try {
        call_user_func($function);
    } catch (Exception $e) {
        ok(false, "UNEXPECTED EXCEPTION", $e);
    }
}

/**
 * @param bool   $result result of assertion
 * @param string $why    description of assertion
 * @param mixed  $value  optional value (displays on failure)
 */
function ok($result, $why = null, $value = null)
{
    if ($result === true) {
        echo "- PASS: " . ($why === null ? 'OK' : $why) . ($value === null ? '' : ' (' . format($value) . ')') . "\n";
    } else {
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value, true)) . "\n";
        status(false);
    }
}

/**
 * @param mixed  $value    value
 * @param mixed  $expected expected value
 * @param string $why      description of assertion
 */
function eq($value, $expected, $why = null)
{
    $result = $value === $expected;

    $info = $result
        ? format($value)
        : "expected: " . format($expected, true) . ", got: " . format($value, true);

    ok($result, ($why === null ? $info : "$why ($info)"));
}

/**
 * @param string   $exception_type Exception type name
 * @param string   $why            description of assertion
 * @param callable $function       function expected to throw
 */
function expect($exception_type, $why, $function)
{
    try {
        call_user_func($function);
    } catch (Exception $e) {
        if ($e instanceof $exception_type) {
            ok(true, $why, $e);
            return;
        } else {
            $actual_type = get_class($e);
            ok(false, "$why (expected $exception_type but $actual_type was thrown)");
            return;
        }
    }

    ok(false, "$why (expected exception $exception_type was NOT thrown)");
}

/**
 * @param mixed $value
 * @param bool  $verbose
 *
 * @return string
 */
function format($value, $verbose = false)
{
    if ($value instanceof Exception) {
        return get_class($value) . ": \"" . $value->getMessage() . "\"";
    }

    if (! $verbose && is_array($value)) {
        return 'array[' . count($value) . ']';
    }

    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    if (is_object($value) && !$verbose) {
        return get_class($value);
    }

    return print_r($value, true);
}

/**
 * @param bool|null $status test status
 *
 * @return int number of failures
 */
function status($status = null)
{
    static $failures = 0;

    if ($status === false) {
        $failures += 1;
    }

    return $failures;
}

/**
 * @return PHP_CodeCoverage|null
 */
function coverage()
{
    static $coverage = null;

    if ($coverage === false) {
        return null; // code coverage unavailable
    }

    if ($coverage === null) {
        try {
            $coverage = new PHP_CodeCoverage;
        } catch (PHP_CodeCoverage_Exception $e) {
            echo "# Notice: no code coverage run-time available\n";
            $coverage = false;
            return null;
        }
    }

    return $coverage;
}
