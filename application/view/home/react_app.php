<?php
$indexPath = ROOT . 'public/react-app/index.html';
if (file_exists($indexPath)) {
    $html = file_get_contents($indexPath);
    $base = rtrim(URL, '/');
    $html = preg_replace('/(href|src)="\/([^"]*)"/', "$1=\"$base/$2\"", $html);
    echo $html;
} else {
    echo '<div id="root"></div>';
}
