Walkway
-------

Elegant, modular routing for PHP - inspired by [vlucas][1]/[bulletphp][2].

Note that this is not a framework, nor a micro-framework - just a router.

This project is under development - the API demonstrated below is still
subject to change: class-names, namespaces, everything is unstable.


1. Define your routes
=====================

This is the fun part!

Define patterns simply by using array-syntax, passing in callback-functions
which define sub-patterns, and so on:

    $module = new Module;
    
    $module->map['blog'] = function ($map) {
      $map['posts'] = function ($map) {
        $map['(\d+)'] = function ($map, $post_id) {
          // the \d+ pattern is captured and used to define the $post_id variable
          if ($post_id == 99) {
            return false; // access denied!
          }
          $map['edit'] = function ($map) {
            $map->get = function ($post_id, $map) {
              // $post_id was captured by a parent route and can be injected here
              echo "editing post {$post_id}!\n";
              echo "display post url: {$map->parent->url}\n";
            };
          };
          $map->get = function ($post_id) {
            echo "displaying post number {$post_id}!\n";
          };
        };
        $map['(\d+)-(\d+)'] = function ($map, $year, $month) {
          // two more variables captured here
          $map['page(\d+)'] = function ($map, $page) {
            $map->get = function($page, $year, $month) {
              // year, month and page-number from parent routes injected here!
              echo "showing page {$page} of posts for year {$year} month {$month}\n";
            };
          };
          $map->get = function ($year, $month) {
            echo "showing posts from year {$year} month {$month}!\n";
          };
        };
      };
    };
    
    $module->map->get = function () {
      echo "hello from the root URL!\n";
    };

2. Evaluate your routes
=======================

This part of the API will change very soon, but currently works like this:

    $request = new Request($module->map, 'blog/posts/2012-08/page2');
    
    $request->execute();


[1]: https://github.com/vlucas
[2]: https://github.com/vlucas/bulletphp
