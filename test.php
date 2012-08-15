<?php

spl_autoload_register(function($class) {
  include __DIR__.'/'.$class.'.php';
});

use mindplay\walkway\Route;
use mindplay\walkway\Module;

class CommentModule extends Module
{
  public function init() {
    $this['submit'] = function($route) {
      $route->get = function($route, $module) {
        echo "displaying comment submission form\n";
        echo "module: " . get_class($module) . "\n";
        echo "module: " . get_class($route->module) . "\n";
        echo "module parent: " . get_class($route->module->parent) . "\n";
        echo "module parent url: " . $module->parent->url . "\n";
        echo "module url: ".$module->url."\n";
      };
    };
    $this->get = function($route, $module) {
      echo "displaying comments!\n";
      echo "current module: ".get_class($module)."\n";
      echo "module url: ".$module->url."\n";
      echo "module parent url: ".$module->parent->url."\n";
      echo "route url: ".$route->url."\n";
      echo "route parent url: ".$route->parent->url."\n";
    };
  }
}

header('Content-type: text/plain');

$module = new Module;

$module['blog'] = function ($route) {
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
      $route['(comments)'] = function(CommentModule $comments, $foo) {
        echo "delegating control to ".get_class($comments)."\n";
        echo "captured: $foo\n";
        echo "available vars: ".implode(', ', array_keys($comments->vars))."\n";
      };
    };
    $route['(\d+)-(\d+)'] = function ($route, $year, $month) {
      $route['page(\d+)'] = function ($route, $page) {
        $route->get = function($route, $page, $year, $month) {
          echo "showing page {$page} of posts for year {$year} month {$month}\n";
          echo "page 1 url: " . $route->resolve('../page1')->url . "\n";
        };
      };
      $route->get = function ($year, $month) {
        echo "showing posts from year {$year} month {$month}!\n";
      };
    };
  };
};

$module->get = function () {
  echo "hello from the root URL!\n";
};

foreach (array(
  'blog/posts/42/edit',
  'blog/posts/2012-07',
  'blog/posts/2012-07/page2',
  'blog/posts/42',
  'blog/posts/88/comments', // this will dispatch the CommentModule
  'blog/posts/66/comments/submit', // this will dispatch inside the CommentModule
  'blog/posts/99', // this one will fail because the router is blocking post_id 99
  '/',
  'blog', // this will fail because there is no method
  'foo' // this will fail because there is no matching route
) as $url) {
  echo "\n----------------\n\nTESTING: {$url}\n\n";
  $route = $module->resolve($url);
  if ($route) {
    if ($route->url) {
      echo "RESOLVED URL: /".($route->url)."\n";
    }
    echo "RESULT: ".($route->execute() ? 'SUCCESS' : 'ERROR (no GET-method)')."\n";
  } else {
    echo "UNRESOLVED URL: $url\n";
  }
}
