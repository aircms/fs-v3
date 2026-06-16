<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo \Air\Core\Front::getInstance(require_once '../config/env.php')
  ->bootstrap()
  ->run();