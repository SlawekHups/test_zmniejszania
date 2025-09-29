<?php
/**
 * Skrypt do pobierania przetworzonych plików
 */

// Załaduj centralną konfigurację
require_once 'config.php';

// Sprawdź parametry - obsługa zarówno session jak i batch
if (empty($_GET['session']) && empty($_GET['batch'])) {
    http_response_code(400);
    die('Brak identyfikatora sesji lub batch');
}

// Określ typ i identyfikator
if (!empty($_GET['batch'])) {
    // Batch processing
    $batch_id = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_GET['batch']);
    $temp_dir = sys_get_temp_dir() . '/image_processor_' . $batch_id;
} else {
    // Zwykła sesja
    $session_id = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_GET['session']);
    $temp_dir = sys_get_temp_dir() . '/image_processor_' . $session_id;
}

if (!is_dir($temp_dir)) {
    http_response_code(404);
    die('Sesja nie istnieje lub wygasła');
}

// Pobieranie archiwum ZIP
if (isset($_GET['zip'])) {
    $zip_files = glob($temp_dir . '/*.zip');
    if (empty($zip_files)) {
        http_response_code(404);
        die('Archiwum nie zostało znalezione');
    }
    
    $zip_path = $zip_files[0];
    $filename = 'processed_images_' . date('Y-m-d_H-i-s') . '.zip';
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: no-cache');
    
    readfile($zip_path);
    exit;
}

// Pobieranie pojedynczego pliku
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $file_path = $temp_dir . '/processed/' . $filename;
    
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('Plik nie został znaleziony');
    }
    
    $mime_type = 'image/jpeg';
    $download_name = $filename;
    
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache');
    
    readfile($file_path);
    exit;
}

http_response_code(400);
die('Nieprawidłowe parametry');
?>
