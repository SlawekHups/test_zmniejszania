<?php
/**
 * Router dla PHP Development Server z centralną konfiguracją
 */

// Załaduj centralną konfigurację (automatycznie ustawi limity)
require_once __DIR__ . '/config.php';

$request_uri = $_SERVER['REQUEST_URI'];

// Rozdziel URL od parametrów
$url_parts = parse_url($request_uri);
$path = $url_parts['path'] ?? '/';
$file_path = __DIR__ . $path;

// Router dla PHP Development Server
if ($path === '/') {
    $path = '/index.html';
    $file_path = __DIR__ . '/index.html';
}

// Sprawdź czy to plik statyczny (bez parametrów)
if (is_file($file_path) && $path !== '/server_200.php') {
    // Ustaw odpowiedni Content-Type
    $ext = pathinfo($file_path, PATHINFO_EXTENSION);
    $mime_types = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    
    return false; // Pozwól PHP Development Server obsłużyć plik
}

// Jeśli to skrypt PHP (może z parametrami)
if (strpos($path, '.php') !== false) {
    $script = ltrim($path, '/');
    if (file_exists($script)) {
        include $script;
        return true;
    }
}

// 404 dla nieistniejących plików
http_response_code(404);
echo "File not found: $request_uri";
return true;
?>
