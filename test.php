<?php

spl_autoload_register(function($class) {
  include __DIR__.'/'.$class.'.php';
});

use mindplay\walkway\Route;
use mindplay\walkway\Module;
use mindplay\walkway\Request;

header('Content-type: text/plain');

$module = new Module;

$module->route['blog'] = function ($route) {
  $route['posts'] = function ($route) {
    $route['(\d+)'] = function ($route, $post_id) {
      if ($post_id == 99) {
        return false; // access denied!
      }
      $route['edit'] = function ($route) {
        $route->get = function ($post_id, $route) {
          echo "editing post {$post_id}!\n";
          echo "display post url: {$route->parent->url}\n";
        };
      };
      $route->get = function ($post_id) {
        echo "displaying post number {$post_id}!\n";
      };
    };
    $route['(\d+)-(\d+)'] = function ($route, $year, $month) {
      $route['page(\d+)'] = function ($route, $page) {
        $route->get = function($page, $year, $month) {
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

foreach (array(
  'blog/posts/42/edit',
  'blog/posts/2012-07',
  'blog/posts/2012-07/page2',
  'blog/posts/42',
  'blog/posts/99', // this one will fail because the router is blocking post_id 99
  '/',
  'blog', // this will fail because there is no method
  'foo' // this will fail because there is no matching route
) as $url) {
  echo "\n----------------\n\nTESTING: {$url}\n\n";
  $request = new Request($module->route, $url);
  echo "RESULT: ".($request->execute() ? 'SUCCESS' : 'ERROR')."\n";
  if ($request->route) {
    echo "RESOLVED URL: /".($request->route->url)."\n";
  }
}
