Walkway
-------

Elegant, modular routing for PHP - inspired by [vlucas][1]/[bulletphp][2].

Note that this is not a framework, nor a micro-framework - just a router.

This project is under development - the API demonstrated below is still
subject to change: class-names, namespaces, everything is unstable.


1. Define your Routes
=====================

This is the fun part!

Define patterns simply by using array-syntax, passing in callback-functions
which define sub-patterns, and so on:

    $module = new Module;
    
    $module->route['blog'] = function ($route) {
      $route['posts'] = function ($route) {
        $route['(\d+)'] = function ($route, $post_id) {
          // the \d+ pattern is captured and used to define the $post_id variable
          if ($post_id == 99) {
            return false; // access denied!
          }
          $route['edit'] = function ($route) {
            $route->get = function ($post_id, $route) {
              // $post_id was captured by a parent route and can be injected here
              echo "editing post {$post_id}!\n";
              echo "display post url: {$route->parent->url}\n";
            };
          };
          $route->get = function ($post_id) {
            echo "displaying post number {$post_id}!\n";
          };
        };
        $route['(\d+)-(\d+)'] = function ($route, $year, $month) {
          // two more variables captured here
          $route['page(\d+)'] = function ($route, $page) {
            $route->get = function($page, $year, $month) {
              // year, month and page-number from parent routes injected here!
              echo "showing page {$page} of posts for year {$year} month {$month}\n";
            };
          };
          $route->get = function ($year, $month) {
            echo "showing posts from year {$year} month {$month}!\n";
          };
        };
      };
    };
    
    $module->route->get = function () {
      echo "hello from the root URL!\n";
    };

2. Evaluate your Routes
=======================

This part of the API will change very soon, but currently works like this:

    $request = new Request($module->route, 'blog/posts/2012-08/page2');
    
    $request->execute();


[1]: https://github.com/vlucas
[2]: https://github.com/vlucas/bulletphp
