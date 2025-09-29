<?php
/**
 * Endpoint do walidacji pojemności przed przetwarzaniem
 */

// Załaduj centralną konfigurację
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Tylko metoda POST']);
    exit;
}

try {
    // Pobierz dane z POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['files']) || !is_array($input['files'])) {
        throw new Exception('Brak informacji o plikach');
    }
    
    $files = $input['files'];
    $file_count = count($files);
    
    // Oblicz całkowity rozmiar
    $total_size_bytes = 0;
    $file_details = [];
    
    foreach ($files as $file) {
        $size = $file['size'] ?? 0;
        $name = $file['name'] ?? 'unknown';
        $type = $file['type'] ?? '';
        
        $total_size_bytes += $size;
        $file_details[] = [
            'name' => $name,
            'size' => $size,
            'size_formatted' => formatBytes($size),
            'type' => $type,
            'valid_type' => isValidImageType($name, $type)
        ];
    }
    
    $total_size_mb = round($total_size_bytes / (1024 * 1024), 2);
    
    // Sprawdź limity PHP
    $php_limits = AppConfig::getPHPLimits();
    $capacity_check = AppConfig::checkPHPLimits($file_count, $total_size_mb);
    $file_validation = AppConfig::validateFileCount($file_count);
    
    // Oszacuj czas przetwarzania (około 0.5-2 sekundy na plik)
    $estimated_time_min = round(($file_count * 0.5) / 60, 1);
    $estimated_time_max = round(($file_count * 2) / 60, 1);
    
    // Oszacuj zużycie pamięci (około 10-50MB na plik w zależności od rozmiaru)
    $avg_file_size_mb = $file_count > 0 ? $total_size_mb / $file_count : 0;
    $estimated_memory_mb = $file_count * max(10, min(50, $avg_file_size_mb * 2));
    $memory_limit_mb = parseSize($php_limits['memory_limit']) / (1024 * 1024);
    
    // Sprawdź czy wszystkie pliki to obrazy
    $invalid_files = array_filter($file_details, function($file) {
        return !$file['valid_type'];
    });
    
    // Określ status walidacji
    $validation_status = 'ok';
    $warnings = [];
    $errors = [];
    
    // Błędy krytyczne
    if (!$capacity_check['ok']) {
        $validation_status = 'error';
        $errors = array_merge($errors, $capacity_check['issues']);
    }
    
    if (!empty($invalid_files)) {
        $validation_status = 'error';
        $errors[] = 'Niektóre pliki nie są obrazami JPEG: ' . implode(', ', array_column($invalid_files, 'name'));
    }
    
    if ($estimated_memory_mb > $memory_limit_mb * 0.9) {
        $validation_status = 'error';
        $errors[] = "Szacowane zużycie pamięci ({$estimated_memory_mb}MB) przekracza limit ({$memory_limit_mb}MB)";
    }
    
    // Ostrzeżenia
    if ($file_validation['status'] === 'warning') {
        $warnings[] = $file_validation['message'];
    }
    
    if ($total_size_mb > 500) {
        $warnings[] = "Duży rozmiar plików ({$total_size_mb}MB) - przetwarzanie może trwać długo";
    }
    
    if ($estimated_time_max > 5) {
        $warnings[] = "Szacowany czas przetwarzania: {$estimated_time_min}-{$estimated_time_max} minut";
    }
    
    if ($estimated_memory_mb > $memory_limit_mb * 0.7) {
        $warnings[] = "Wysokie zużycie pamięci - może być potrzebne przetwarzanie partiami";
    }
    
    // Rekomendacje
    $recommendations = [];
    
    if ($file_count > AppConfig::PERFORMANCE_LIMITS['max_safe_batch_size']) {
        $recommendations[] = "Zalecane: użyj trybu progresywnego dla lepszej wydajności";
    }
    
    if ($file_count > AppConfig::PERFORMANCE_LIMITS['recommended_batch_size'] && $file_count <= AppConfig::PERFORMANCE_LIMITS['max_safe_batch_size']) {
        $recommendations[] = "Możesz użyć trybu 'Wszystkie naraz' lub 'Po partiach'";
    }
    
    if ($total_size_mb > 1000) {
        $recommendations[] = "Duże pliki - rozważ przetwarzanie w mniejszych partiach";
    }
    
    // Zwróć wyniki walidacji
    echo json_encode([
        'success' => true,
        'validation_status' => $validation_status,
        'can_process' => $validation_status !== 'error',
        'summary' => [
            'file_count' => $file_count,
            'total_size' => $total_size_mb . 'MB',
            'estimated_time' => $estimated_time_min === $estimated_time_max ? 
                "{$estimated_time_min} min" : 
                "{$estimated_time_min}-{$estimated_time_max} min",
            'estimated_memory' => round($estimated_memory_mb) . 'MB'
        ],
        'php_limits' => $php_limits,
        'file_validation' => $file_validation,
        'capacity_check' => $capacity_check,
        'errors' => $errors,
        'warnings' => $warnings,
        'recommendations' => $recommendations,
        'file_details' => $file_details
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Sprawdź czy plik to prawidłowy obraz JPEG
 */
function isValidImageType($filename, $mime_type) {
    $filename_lower = strtolower($filename);
    $valid_extensions = $filename_lower && (
        str_ends_with($filename_lower, '.jpg') || 
        str_ends_with($filename_lower, '.jpeg')
    );
    
    $valid_mime = empty($mime_type) || 
                  $mime_type === 'image/jpeg' || 
                  $mime_type === 'image/jpg' ||
                  $mime_type === 'application/octet-stream'; // Czasem przy drag&drop
    
    return $valid_extensions && $valid_mime;
}
?>
