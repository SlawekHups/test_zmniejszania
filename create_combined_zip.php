<?php
/**
 * Tworzenie ZIP z plików z wielu sesji (dla trybu progresywnego)
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
    // Pobierz listę session ID z POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['session_ids']) || !is_array($input['session_ids'])) {
        throw new Exception('Brak listy session_ids');
    }
    
    $session_ids = array_filter($input['session_ids'], function($id) {
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $id);
    });
    
    if (empty($session_ids)) {
        throw new Exception('Brak prawidłowych session ID');
    }
    
    // Stwórz nową sesję dla combined ZIP
    $combined_session_id = generateSessionId('combined_');
    $combined_temp_dir = getTempDir() . '/image_processor_' . $combined_session_id;
    
    if (!mkdir($combined_temp_dir, 0755, true)) {
        throw new Exception('Nie można utworzyć katalogu tymczasowego');
    }
    
    $collected_files = [];
    $total_files = 0;
    
    // Zbierz pliki z wszystkich sesji
    foreach ($session_ids as $session_id) {
        $session_dir = getTempDir() . '/image_processor_' . $session_id;
        $session_file = $session_dir . '/session.json';
        
        if (!file_exists($session_file)) {
            error_log("Session file not found: $session_file");
            continue;
        }
        
        $session_data = json_decode(file_get_contents($session_file), true);
        if (!$session_data || !isset($session_data['files'])) {
            error_log("Invalid session data: $session_id");
            continue;
        }
        
        // Skopiuj pliki do combined directory
        foreach ($session_data['files'] as $file_info) {
            // Pliki są w podkatalogu processed/
            $source_path = $session_dir . '/processed/' . $file_info['processed_name'];
            
            if (file_exists($source_path)) {
                // Unikalne nazwy plików (dodaj prefix sesji jeśli potrzeba)
                $target_name = $file_info['processed_name'];
                $target_path = $combined_temp_dir . '/' . $target_name;
                
                // Jeśli plik o tej nazwie już istnieje, dodaj suffix
                $counter = 1;
                while (file_exists($target_path)) {
                    $path_info = pathinfo($target_name);
                    $target_name = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
                    $target_path = $combined_temp_dir . '/' . $target_name;
                    $counter++;
                }
                
                if (copy($source_path, $target_path)) {
                    $collected_files[] = [
                        'original_name' => $file_info['original_name'],
                        'processed_name' => $target_name,
                        'source_session' => $session_id,
                        'file_size' => filesize($target_path),
                        'date_taken' => $file_info['date_taken'] ?? null
                    ];
                    $total_files++;
                } else {
                    error_log("Failed to copy file: $source_path to $target_path");
                }
            } else {
                error_log("Source file not found: $source_path");
            }
        }
    }
    
    if ($total_files === 0) {
        throw new Exception('Nie znaleziono plików do skopiowania');
    }
    
    // Sortuj pliki według daty EXIF (jak w oryginalnym processorze)
    usort($collected_files, function($a, $b) {
        $date_a = $a['date_taken'] ?? '9999-12-31 23:59:59';
        $date_b = $b['date_taken'] ?? '9999-12-31 23:59:59';
        return strcmp($date_a, $date_b);
    });
    
    // Stwórz session.json dla combined directory
    $combined_session_data = [
        'session_id' => $combined_session_id,
        'created_at' => date('Y-m-d H:i:s'),
        'type' => 'combined',
        'source_sessions' => $session_ids,
        'total_files' => $total_files,
        'files' => $collected_files,
        'stats' => [
            'total_size' => array_sum(array_column($collected_files, 'file_size')),
            'source_sessions_count' => count($session_ids)
        ]
    ];
    
    file_put_contents($combined_temp_dir . '/session.json', json_encode($combined_session_data, JSON_PRETTY_PRINT));
    
    // Stwórz ZIP z połączonych plików
    $zip_filename = 'processed_images_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = $combined_temp_dir . '/' . $zip_filename;
    
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            foreach ($collected_files as $file_info) {
                $file_path = $combined_temp_dir . '/' . $file_info['processed_name'];
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, $file_info['processed_name']);
                }
            }
            $zip->close();
        } else {
            throw new Exception('Nie można utworzyć archiwum ZIP');
        }
    } else {
        throw new Exception('Rozszerzenie ZIP nie jest dostępne');
    }
    
    // Zwróć informacje o combined session
    echo json_encode([
        'success' => true,
        'combined_session_id' => $combined_session_id,
        'total_files' => $total_files,
        'source_sessions' => count($session_ids),
        'download_url' => "download.php?session={$combined_session_id}&zip=1",
        'zip_size' => file_exists($zip_path) ? filesize($zip_path) : 0,
        'files' => $collected_files
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
