<?php
/**
 * Sprawdzenie dostępności wymaganych rozszerzeń PHP
 */

header('Content-Type: application/json; charset=utf-8');

$status = [
    'php_version' => PHP_VERSION,
    'gd_available' => extension_loaded('gd'),
    'exif_available' => extension_loaded('exif'),
    'zip_available' => extension_loaded('zip'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => (int)ini_get('max_file_uploads'),
    'max_input_vars' => (int)ini_get('max_input_vars'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'temp_dir_writable' => is_writable(sys_get_temp_dir())
];

// Dodatkowe informacje o GD
if ($status['gd_available']) {
    $gd_info = gd_info();
    $status['gd_version'] = $gd_info['GD Version'] ?? 'Unknown';
    $status['jpeg_support'] = $gd_info['JPEG Support'] ?? false;
}

// Sprawdź czy katalogi robocze istnieją i są zapisywalne
$status['working_dirs'] = [
    'temp_accessible' => is_dir(sys_get_temp_dir()),
    'temp_writable' => is_writable(sys_get_temp_dir())
];

echo json_encode($status, JSON_PRETTY_PRINT);
?>
