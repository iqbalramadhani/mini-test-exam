<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$docroot = __DIR__;

// Static files and directories — serve directly
if (is_file($docroot . $path) || is_dir($docroot . $path)) {
    return false;
}

// API routes — dispatch via index.php
if (str_starts_with($path, '/api/')) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_GET['url'] = ltrim($path, '/');
    include $docroot . '/index.php';
    exit;
}

// SPA fallback — serve react-app/index.html
if (file_exists($docroot . '/react-app/index.html')) {
    header('Content-Type: text/html');
    readfile($docroot . '/react-app/index.html');
    exit;
}

return false;