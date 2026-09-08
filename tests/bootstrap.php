<?php

define('ROOT', dirname(__DIR__));
define('APP', ROOT . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR);

// Load env loader before anything else
require APP . 'libs/env.php';

// Define DB constants for tests if not already set
if (!defined('DB_TYPE')) define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql');
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'mini_test');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

if (!defined('URL')) define('URL', 'http://localhost/');
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'testing');

// Load classes needed for tests
require_once APP . 'libs/security.php';
require_once APP . 'model/model.php';
