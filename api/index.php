<?php
/**
 * Vercel Serverless Entry Point & Router
 * Project: มีอะไรในครัว? (What's in My Kitchen)
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($requestUri, '/');

// Base directory (root of project)
$rootDir = realpath(__DIR__ . '/..');

// Default page
if ($path === '' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

// Check admin directory root (e.g. /admin -> /admin/index.php)
if ($path === 'admin') {
    require $rootDir . '/admin/index.php';
    exit;
}

// Check if exact file exists
$requestedFile = realpath($rootDir . '/' . $path);
if ($requestedFile && strpos($requestedFile, $rootDir) === 0 && file_exists($requestedFile) && is_file($requestedFile)) {
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        require $requestedFile;
        exit;
    }

    // Serve static files with proper MIME type
    $mimes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'json'  => 'application/json',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($requestedFile);
    exit;
}

// Check with .php extension appended (e.g. /about -> /about.php, /categories -> /categories.php)
$phpCandidate = realpath($rootDir . '/' . $path . '.php');
if ($phpCandidate && strpos($phpCandidate, $rootDir) === 0 && file_exists($phpCandidate) && is_file($phpCandidate)) {
    require $phpCandidate;
    exit;
}

// 404 fallback
http_response_code(404);
echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>404 Not Found</title></head><body style='font-family: sans-serif; text-align: center; padding: 50px;'><h1>404 ไม่พบหน้านี้</h1><p>หน้าที่คุณค้นหาไม่มีอยู่ในระบบ</p><a href='/'>กลับหน้าแรก</a></body></html>";
