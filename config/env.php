<?php

use Air\Config;

return Config::defaults(
  reportErrors: false,
  extensions: [
    'locale' => [
      'ua' => require_once 'locale/ua.php',
      'en' => require_once 'locale/en.php',
    ],
    'key'    => getenv('AIR_FS_KEY'),
    'fs'     => [
      'path'      => realpath(dirname(__FILE__) . '/../www/storage'),
      'url'       => 'https://' . $_SERVER['HTTP_HOST'] . '/storage',
      'host'      => 'https://' . $_SERVER['HTTP_HOST'],
      'thumbnail' => [
        'width'  => 300,
        'height' => 180,
      ]
    ],
  ],
  ui: fn() => Config::ui(
    domain: 'fs',
    strictRoutes: false,
  ),
);
