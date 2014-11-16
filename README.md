Walkway
-------

Elegant, modular routing for PHP - inspired by [vlucas][1]/[bulletphp][2].

[![Build Status](https://travis-ci.org/mindplay-dk/walkway.png)](https://travis-ci.org/mindplay-dk/walkway)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/walkway/badges/coverage.png?b=dev-2.0)](https://scrutinizer-ci.com/g/mindplay-dk/walkway/?branch=dev-2.0)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/walkway/badges/quality-score.png?b=dev-2.0)](https://scrutinizer-ci.com/g/mindplay-dk/walkway/?branch=dev-2.0)

Note that this is not a framework, and not a micro-framework - this library
exclusively deals with routing, and deliberately does not provide any kind
of front-controller, request/response or controller/action abstraction,
error-handling, or any other framework-like feature.

This makes the library very open-ended - you can use the routing facility
to route whatever you want (anything that resembles a path) to whatever you
want. (e.g. controllers, other scripts, another framework or CMS, anything.)

Unlike most routers using this style/approach, this router is functional -
which means the routes are actually being defined as the resolver "walks"
them, one level at a time, which is practically inifinitely scalable.

This router is also modular - which means that a set of routes can be
self-contained, and can be reused, which further helps with scalability
in applications with a large number of routes, since modules that aren't
visited while resolving a route, won't be loaded or initialized at all.

The codebase is very small, very simple, and very open-ended - you can do
both good and evil with this library.

To understand how to make the most of it, please read the documentation below.


Defining Routes
===============

This is the fun part!

You define patterns by using array-syntax to configure callback-functions,
which may define nested sub-patterns, and so on.

A collection of Routes is called a Module - you can create an instance and
configure it to handle a path like `'hello/world'` using code like this:

    $module = new Module;
    
    $module['hello'] = function (Route $route) {
        $route['world'] = function (Route $route) {
            $route->get = function() {
                echo '<h1>Hello, World!</h1>';
            };
        };
    };

Route patterns are (PCRE) regular expressions - you can use substring capture,
combined with a function, to define route parameters:

    $module['archive'] = function(Route $route) {
        $route['<year:int>-<month:int>'] = function (Route $route) {
            $route->get = function ($year, $month) {
                echo "<h1>Archive for $month / $year</h1>";
            };
        };
    };

Note that the expression `<year:int>-<month:int>` is pre-processed, and internally
is transformed into the PCRE regular expression `(?<year>\d+)-(?<month>\d+)`, which
isn't quite as legible.

Modules can (optionally) pre-process the patterns - the default patterns allow you to
use the simplified pattern syntax shown above, and recognizes a few symbols like `int`
and `slug`, which are just named abbreviations for regular expression patterns. You
can add or remove pre-processing functions, as needed.


Modules
=======

To make a reusable Module, you can derive your own specialized class from Module - which
also gives you a natural location for URL creation-functions:

    class HelloWorldModule extends Module
    {
        public function init()
        {
            parent::init();
            
            $this['hello'] = function (Route $route) {
                $route['world'] = function (Route $route) {
                    $route->get = function() {
                        echo '<h1>Hello, World!</h1>';
                    };
                };
            };
        }
        
        public function hello_url($world = 'world')
        {
            return "/hello/$world";
        }
    }

Encapsulating routes in a Module also provides modularity - to delegate control from
from one Module to another, call the delegate() method on the Route object:

    $route['comments'] = function (Route $route) {
        $route->delegate(new CommentModule());
    };

See the "test.php" script for an example of creating and routing to a nested Module.

Note that there's a good reason why URL-creation is not part of this library -
this is explained at the end of this document.


Evaluating Routes
=================

A Module is the root of a set of Routes.

To resolve a path and find the Route defined by your Module, do this:

    $route = $module->resolve('archive/2012-08');

Note that this would return `null` if the route was unresolved.

To execute an HTTP method-handler associated with the Route, do this:

    $result = $route->execute('GET');

The returned `$result` is whatever you choose to return in your handler,
which could be a Controller or HTML content, or nothing - if you prefer to
simply output your content directly, and you don't issue a return-statement,
the return-value is boolean `true` or `false`, indicating success or failure.


Model / View / Controller
=========================

The `execute()` method in the previous example returns `true` on success, unless
the HTTP method-handler itself returns something else. In the example above, the
HTTP method-handlers do not provide return values, but you can implement a
simple MVC-style controller/action-abstraction without using a framework:

    $module['posts'] = function ($route) {
        $controller = new PostsController();
          
        $route['<post_id:int>'] = function (Route $route) use ($controller) {
            
            $route->get = function ($post_id) use ($controller) {
                return $controller->showPost($post_id);
            };
            
            $route['edit'] = function (Route $route) use ($controller) {
                $route->get = function ($post_id) use ($controller) {
                    return $controller->editPost($post_id);
                };
                $route->post = function ($post_id) use ($controller) {
                    return $controller->updatePost($post_id);
                };
            };
        };
    };

    $result = $module->resolve('posts/42/edit')->execute('get');


Integration
===========

If you have a service-container or some other framework/application component
that needs to be easily accessible from within your routes, while avoiding the
need for `use()` clauses down through the hierarchy of functions, you can insert
values into `Route::$vars` during `init()` (or at any point) - this collection
stores values captured while resolving a route, and these values are used
to fill function-arguments for both route-definitions and action-methods.


IDE Support
===========

To get full IDE support with auto-complete and static analysis, make sure your
code uses type-hints - for example:

    $this['hello'] = function (Route $route) {
        $route->___ // <- auto-completes!
    };

This also provides extra safety from inadvertently getting your parameters/types
mixed up.


Creating URLs
=============

This library does not provide an abstraction for URL-creation (commonly referred
to as "named routes" in various frameworks) for a couple of reasons.

First off, "named routes" usually come at the cost of IDE support. It also forces
you to load and define all your routes in advance, which can be inefficient.

But most importantly, it has no real value - because the tasks of creating URLs
and resolving URLs only really have one thing in common: the name of the route.
Since you're never going to refer to the route itself by name, for any other
purpose besides generating a URL, having to refer to it by name isn't meaningful.
Typically, everything else about a URL is variable, and you end up having to
repeat parameter-names in a way that cannot be verified.

Contrast the following fictive syntax:

    $url = $module->create('show_archive', array('year' => '2013', 'month' => '04));

With the following real beauty:

    $url = $module->show_archive_url('2013', '04');

The latter is half the amount of typing, it's easier to read - an IDE can provide
auto-completions, and you can perform inspections (static analysis) on the code
if you have to change the name or parameters.

Also, because this is a real function, and not some kind of abstraction, you can
use whatever code is necessary to create URLs, create different URLs under different
circumstances, use arguments of different types (even entities, if needed), and so on.

The advantages of URL creation being free from the limitations of even the best, most
complex abstractions, are too numerous to ignore - plus, at the end of the day, try to
view URL creation for what it really is: a string template. You're creating a *string*.
Do you really need a framework for that? Simple solutions for simple problems, please!


Creating a simple Front Controller
==================================

To use Walkway as a front-controller, create an "index.php" file along the lines of:

    // get the path and HTTP request method:

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // create your module and resolve the path:

    $router = new YourAwesomeModule();

    $route = $router->resolve($path);

    // generate a 404 if the path did not resolve:

    if ($route->$method === null) {
        header("HTTP/1.0 404 No Route");
    }

    // dispatch the get/head/post/put/delete function:

    $result = $route->execute($method);

    // optionally do something clever with $result here...

Then create an ".htaccess" file to route incoming requests to your "index.php":

    RewriteEngine on

    # if a directory or a file exists, use it directly
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    # otherwise forward it to index.php
    RewriteRule . index.php

And you're set!


Enjoy!
======

Feedback and pull requests welcome :-)


[1]: https://github.com/vlucas
[2]: https://github.com/vlucas/bulletphp
