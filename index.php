<?php
// Zeabur detects PHP via index.php; serve the same homepage as index.htm
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.htm');
