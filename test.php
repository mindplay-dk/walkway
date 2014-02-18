<?php

header('Content-type: text/plain');

#define('NOISY', true); // uncomment to enable diagnostic messages

spl_autoload_register(
    function ($class) {
        include __DIR__ . '/' . $class . '.php';
    }
);

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler("exception_error_handler");

use mindplay\walkway\Route;
use mindplay\walkway\Module;

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
                return false; // access denied!
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

            $route['comments'] = function (Route $route, CommentModule $comments) {
                $route->log("delegating control to " . get_class($comments));
                $route->log("available vars: " . implode(', ', array_keys($comments->vars)));
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

// Run tests:

$tests = array(
    'Resolves root path' => function() use ($module) {
        if ($route = $module->resolve('/')) {
            if ($route === $module) {
                if ($route->execute('get') === 'hello') {
                    return true;
                }
            }
        }
    },

    'Does not resolve invalid root path' => function () use ($module) {
        return $module->resolve('foo') === null;
    },

    'Does not resolve invalid nested path' => function () use ($module) {
        return $module->resolve('blog/foo') === null;
    },

    'Resolves a basic path' => function() use ($module) {
        if ($route = $module->resolve('blog/posts/42/edit')) {
            if ($route instanceof Route) {
                if ($route->execute('get') === array('post_id' => '42')) {
                    return true;
                }
            }
        }
    },

    'Captures multiple named substrings' => function() use ($module) {
        if ($route = $module->resolve('blog/posts/2012-07')) {
            return $route->execute('get') === array('year'=>'2012', 'month'=>'07');
        }
    },

    'Inherits captured substrings from parent routes' => function() use ($module) {
        if ($route = $module->resolve('blog/posts/2012-07/page2')) {
            return $route->execute('get') === array('year'=>'2012', 'month'=>'07', 'page'=>'2');
        }
    },

    'Correctly resolves a sub-module' => function() use ($module) {
        if ($route = $module->resolve('blog/posts/88/comments')) {
            if ($route instanceof CommentModule) {
                if ($route->execute('get') === 'comments') {
                    return true;
                }
            }
        }
    },

    'Correctly resolves a route inside a sub-module' => function() use ($module) {
        if ($route = $module->resolve('blog/posts/66/comments/submit')) {
            if ($route->module instanceof CommentModule) {
                if ($route->execute('get') === 'comment form') {
                    return true;
                }
            }
        }
    },

    'Does not resolve when the route actively blocks the request (by returning false)' => function() use ($module) {
        return ($module->resolve('blog/posts/99') === null)
            && ($module->resolve('blog/posts/88') instanceof Route);
    },

    'Routes, but does not execute when there is no action-method' => function() use ($module) {
        if ($route = $module->resolve('blog')) {
            return $route->execute('get') === false;
        }
    },

    'Resolves path with more than one part' => function () use ($module) {
        $route = $module->resolve('blog/tags/foo-bar');

        $result = $route->execute('get');

        return $result === 'foo-bar';
    },

);

// Display test results:

$passed = 0;
$failed = 0;
$number = 0;

foreach ($tests as $name => $test) {
    $pass = $test() === true;

    echo "  " . ($pass ? 'PASS: ' : 'FAIL: ') . $name . "\n";

    if ($pass) {
        $passed += 1;
    } else {
        $failed += 1;
    }
}

$total = $passed + $failed;

echo "\n";

if ($failed > 0) {
    echo "*** $passed of $total tests passed, $failed tests failed!";
    exit(1);
} else {
    echo "* $passed tests passed.";
    exit(0);
}
