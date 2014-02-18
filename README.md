Walkway
-------

Elegant, modular routing for PHP - inspired by [vlucas][1]/[bulletphp][2].

Note that this is not a framework, and not a micro-framework - this library
exclusively deals with routing, and deliberately does not provide any kind
of front-controller, request/response or controller/action abstraction,
error-handling, or any other framework-like feature.

This makes the library very open-ended - you can using the routing facility
to route whatever you want (anything that resembles a path) to whatever you
want. (e.g. controllers, other scripts, another framework or CMS, anything.)

Unlike most routers using this style/approach, this router is functional -
which means the routes are actually being defined as the resolver "walks"
them, one level at a time, which is practically inifinitely scalable.

This router is also modular - which means that a set of routes can be
self-contained, and can be reused, which further helps with scalability
in applications with a large number of routes, as modules that aren't
visited while resolving a route, don't need to be loaded at all.

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
    
    $module['hello'] = function ($route) {
        $route['world'] = function ($route) {
            $route->get = function() {
                echo '<h1>Hello, World!</h1>';
            };
        };
    };

Route patterns are (PCRE) regular expressions - you can use substring capture,
combined with a function, to define route parameters.

To help you understand how this works, I'm going to show you the simplest way
to do this first:

    $module['archive'] = function($route) {
        $route['<year:int>-<month:int>'] = function ($route) {
            $route->get = function ($year, $month) {
                echo "<h1>Archive for $month / $year</h1>";
            };
        };
    };

I told you Route patterns are regular expression, but `<year:int>-<month:int>`
doesn't look like a regular expression. Work backwards with me to understand
how or why this works, starting with a much less elegant version of the above:

    $module['archive'] = function ($route) {
        $route['(\d+)-(\d+)'] = function ($route, $year, $month) {
            $route->get = function ($year, $month) {
                echo "<h1>Archive for Year $year month $month</h1>";
            };
        };
    };

This is the most basic way to capture and name a parameter, though probably
not the most obvious. The resolver is pretty lax, and it will guess that
the two numerical substrings you captured must be intended for use with the
`$year` and `$month` variables, so it captures those and hangs on to them,
which means they are now available as arguments to any functions being
invoked below that route - in the `$route->get` function, the `$year` and
`$month` arguments will be filled with the captured values.

We didn't really need the `$year` and `$month` variables inside the first
`$route['(\d+)-(\d+)']` function - however, we had to give names to the
nameless substrings captured by the pattern.

We don't have to though - PCRE expressions can define named subpatterns:

    $module['archive'] = function ($route) {
        $route['(?<year>\d+)-(?<month>\d+)'] = function ($route) {
            $route->get = function ($year, $month) {
                echo "<h1>Archive for Year $year month $month</h1>";
            };
        };
    };

Notice how we got rid of the extra `$year` and `$month` arguments - the
names are now defined by the regular expression, instead of by the closure.

That's not very easy on the eyes though - but Modules can also (optionally)
pre-process the patterns, and the built-in pre-processor lets you use the
following simplified pattern syntax:

    $route['<year:\d+>-<month:\d+>'] = function ($route) {
        ...
    };

The built-in pre-processor also recognizes a few symbols like `int` and `slug`,
which you can use to abbreviate patterns commonly used in your routes - this
lets us rewrite the above as follows:

    $route['<year:int>-<month:int>'] = function ($route) {
        ...
    };

The `int` symbol is substituted for the `\d+` expression - you can add your
own symbols to your Module and reuse them throughout your Module's patterns,
or you could start with a Module base-class for all your Modules, enabling you
to share any custom symbols and pre-processing functions across Modules.

Since version 1.0.0, it's possible to match multi-part paths, e.g. patterns
that have a "/" in them.


Modules
=======

To make a reusable Module, you can choose to extend Module instead - which
also gives you a natural place for URL creation-functions:

    class HelloWorldModule extends Module
    {
        public function init()
        {
            parent::init();
            
            $this['hello'] = function ($route) {
                $route['world'] = function ($route) {
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

Encapsulating routes in a Module also has the advantage of being able to
route from one Module to another - see the "test.php" script for an example
of creating and routing to a nested Module.

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
