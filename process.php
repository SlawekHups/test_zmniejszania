<?php
/**
 * Profesjonalny Procesor Zdjęć
 * Łączy konwersję JPG→JPEG i zmniejszanie obrazów z zachowaniem dat EXIF
 * 
 * @author Twój Zespół
 * @version 2.1 - Z rozszerzonym logowaniem
 */

// Wymagaj centralnej konfiguracji (automatycznie ustawi limity PHP)
require_once 'config.php';
require_once 'logger.php';

// Dodatkowa konfiguracja
error_reporting(E_ALL);

// Nagłówki HTTP
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Obsługa OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Tylko POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda nie dozwolona']);
    exit;
}

// Sprawdzenie rozszerzeń PHP
if (!extension_loaded('gd')) {
    echo json_encode(['success' => false, 'error' => 'Rozszerzenie GD nie jest dostępne']);
    exit;
}

$exif_available = extension_loaded('exif');

/**
 * Klasa do profesjonalnego przetwarzania obrazów
 */
class ImageProcessor {
    private $config;
    private $temp_dir;
    private $session_id;
    private $log = [];
    private $logger;
    
    public function __construct($config) {
        $this->config = array_merge([
            'max_size' => 800,
            'quality' => 85,
            'progressive' => true,
            'preserve_exif' => true,
            'sort_by' => 'exif_date',
            'sort_order' => 'asc',
            'auto_rotate' => true,
            'keep_original' => false,
            'output_format' => 'original'
        ], $config);
        
        $this->session_id = uniqid('img_', true);
        $this->temp_dir = sys_get_temp_dir() . '/image_processor_' . $this->session_id;
        
        // Inicjalizuj logger
        $this->logger = new DetailedLogger($this->session_id);
        $this->logger->step('ImageProcessor inicjalizowany', [
            'session_id' => $this->session_id,
            'temp_dir' => $this->temp_dir,
            'config' => $this->config
        ]);
        
        if (!is_dir($this->temp_dir)) {
            if (!mkdir($this->temp_dir, 0755, true)) {
                $this->logger->error('Nie można utworzyć katalogu tymczasowego', ['path' => $this->temp_dir]);
                throw new Exception('Nie można utworzyć katalogu tymczasowego');
            }
            $this->logger->step('Katalog tymczasowy utworzony', ['path' => $this->temp_dir]);
        }
    }
    
    /**
     * Przetwórz przesłane pliki
     */
    public function processUploadedFiles($files) {
        $this->logger->step('Rozpoczęcie przetwarzania przesłanych plików');
        
        if (empty($files['images']['name'][0])) {
            $this->logger->error('Nie przesłano żadnych plików');
            throw new Exception('Nie przesłano żadnych plików');
        }
        
        $this->logger->step('Przesłano pliki do przetworzenia', [
            'count' => count($files['images']['name'])
        ]);
        
        $uploaded_files = $this->handleUploads($files);
        $processed_files = $this->processImages($uploaded_files);
        
        // Finalizuj logowanie
        $stats = $this->logger->finalize();
        
        return [
            'success' => true,
            'session_id' => $this->session_id,
            'total' => count($uploaded_files),
            'processed' => count($processed_files),
            'errors' => count($uploaded_files) - count($processed_files),
            'files' => $processed_files,
            'zip_download_url' => $this->createZipArchive($processed_files),
            'log' => $this->log,
            'detailed_logs' => $this->logger->getAllLogs(),
            'stats' => $stats
        ];
    }
    
    /**
     * Obsługa uploadowanych plików
     */
    private function handleUploads($files) {
        $uploaded = [];
        $file_count = count($files['images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $this->log[] = "Błąd uploadu: {$files['images']['name'][$i]}";
                continue;
            }
            
            $original_name = $files['images']['name'][$i];
            $temp_path = $files['images']['tmp_name'][$i];
            $file_size = $files['images']['size'][$i];
            
            // Walidacja
            if (!$this->validateImageFile($temp_path, $original_name, $file_size)) {
                continue;
            }
            
            // Zapisz w katalogu tymczasowym
            $safe_name = $this->sanitizeFilename($original_name);
            $upload_path = $this->temp_dir . '/uploads/' . $safe_name;
            
            if (!is_dir(dirname($upload_path))) {
                mkdir(dirname($upload_path), 0755, true);
            }
            
            // Sprawdź czy to prawdziwy upload czy test lokalny
            $moved = false;
            if (is_uploaded_file($temp_path)) {
                // Prawdziwy upload przez HTTP
                $moved = move_uploaded_file($temp_path, $upload_path);
                $this->logger->fileInfo($original_name, 'Prawdziwy upload HTTP', ['from' => $temp_path, 'to' => $upload_path]);
            } else {
                // Test lokalny lub symulacja
                $moved = copy($temp_path, $upload_path);
                $this->logger->fileInfo($original_name, 'Symulacja/test lokalny', ['from' => $temp_path, 'to' => $upload_path]);
            }
            
            if ($moved) {
                $uploaded[] = [
                    'original_name' => $original_name,
                    'safe_name' => $safe_name,
                    'path' => $upload_path,
                    'size' => $file_size
                ];
                $this->log[] = "Upload OK: $original_name";
                $this->logger->fileInfo($original_name, 'Upload zakończony pomyślnie', ['size' => $file_size]);
            } else {
                $this->log[] = "Błąd zapisu: $original_name";
                $this->logger->error('Błąd zapisu pliku', [
                    'file' => $original_name,
                    'temp_path' => $temp_path,
                    'upload_path' => $upload_path,
                    'temp_exists' => file_exists($temp_path),
                    'temp_readable' => is_readable($temp_path),
                    'upload_dir_exists' => is_dir(dirname($upload_path)),
                    'upload_dir_writable' => is_writable(dirname($upload_path))
                ]);
            }
        }
        
        return $uploaded;
    }
    
    /**
     * Walidacja pliku obrazu
     */
    private function validateImageFile($temp_path, $name, $size) {
        // Sprawdzenie rozszerzenia
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg'])) {
            $this->log[] = "Nieprawidłowe rozszerzenie: $name";
            return false;
        }
        
        // Sprawdzenie rozmiaru (max 50MB)
        if ($size > 50 * 1024 * 1024) {
            $this->log[] = "Plik za duży: $name";
            return false;
        }
        
        // Sprawdzenie typu MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $temp_path);
        finfo_close($finfo);
        
        if (!in_array($mime, ['image/jpeg', 'image/jpg'])) {
            $this->log[] = "Nieprawidłowy typ MIME: $name ($mime)";
            return false;
        }
        
        // Sprawdzenie czy to faktycznie obraz
        $img_info = @getimagesize($temp_path);
        if (!$img_info) {
            $this->log[] = "Nieprawidłowy plik obrazu: $name";
            return false;
        }
        
        return true;
    }
    
    /**
     * Bezpieczna nazwa pliku
     */
    private function sanitizeFilename($filename) {
        $filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');
        $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        return trim($filename, '_');
    }
    
    /**
     * Główne przetwarzanie obrazów
     */
    private function processImages($uploaded_files) {
        if (empty($uploaded_files)) {
            return [];
        }
        
        // Sortowanie plików według wybranego kryterium
        $uploaded_files = $this->sortFiles($uploaded_files);
        
        $processed = [];
        $output_dir = $this->temp_dir . '/processed/';
        
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        foreach ($uploaded_files as $index => $file) {
            try {
                $result = $this->processImage($file, $output_dir, $index);
                if ($result) {
                    $processed[] = $result;
                }
            } catch (Exception $e) {
                $this->log[] = "Błąd przetwarzania {$file['original_name']}: " . $e->getMessage();
            }
        }
        
        return $processed;
    }
    
    /**
     * Sortowanie plików według wybranego kryterium
     */
    private function sortFiles($files) {
        // Dodaj metadane do sortowania
        foreach ($files as &$file) {
            $file['metadata'] = $this->getImageMetadata($file['path']);
        }
        
        usort($files, function($a, $b) {
            switch ($this->config['sort_by']) {
                case 'exif_date':
                    $a_date = $a['metadata']['exif_timestamp'] ?: $a['metadata']['file_timestamp'];
                    $b_date = $b['metadata']['exif_timestamp'] ?: $b['metadata']['file_timestamp'];
                    $result = $a_date <=> $b_date;
                    break;
                    
                case 'file_date':
                    $result = $a['metadata']['file_timestamp'] <=> $b['metadata']['file_timestamp'];
                    break;
                    
                case 'filename':
                default:
                    $result = strcasecmp($a['original_name'], $b['original_name']);
                    break;
            }
            
            return $this->config['sort_order'] === 'desc' ? -$result : $result;
        });
        
        return $files;
    }
    
    /**
     * Pobierz metadane obrazu (EXIF + podstawowe info)
     */
    private function getImageMetadata($path) {
        global $exif_available;
        
        $metadata = [
            'file_timestamp' => filemtime($path),
            'exif_timestamp' => null,
            'orientation' => null,
            'dimensions' => null
        ];
        
        // Podstawowe informacje
        $img_info = @getimagesize($path);
        if ($img_info) {
            $metadata['dimensions'] = [$img_info[0], $img_info[1]];
        }
        
        // EXIF jeśli dostępny
        if ($exif_available) {
            $exif = @exif_read_data($path, 'IFD0,EXIF', true);
            if (is_array($exif)) {
                // Data zdjęcia
                $date_fields = [
                    $exif['EXIF']['DateTimeOriginal'] ?? null,
                    $exif['EXIF']['DateTime'] ?? null,
                    $exif['IFD0']['DateTime'] ?? null
                ];
                
                foreach ($date_fields as $date_str) {
                    if ($date_str) {
                        $formatted_date = str_replace(':', '-', substr($date_str, 0, 10)) . substr($date_str, 10);
                        $timestamp = strtotime($formatted_date);
                        if ($timestamp) {
                            $metadata['exif_timestamp'] = $timestamp;
                            break;
                        }
                    }
                }
                
                // Orientacja
                $orientation = $exif['IFD0']['Orientation'] ?? null;
                if ($orientation) {
                    $metadata['orientation'] = is_string($orientation) ? (int)$orientation : $orientation;
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Przetwórz pojedynczy obraz
     */
    private function processImage($file, $output_dir, $index) {
        $src_path = $file['path'];
        $metadata = $file['metadata'];
        
        // Generuj nazwę wyjściową
        $output_name = $this->generateOutputName($file, $index, $metadata);
        $output_path = $output_dir . $output_name;
        
        // Konwertuj JPG na JPEG (jeśli potrzeba) i zmniejsz
        $image = @imagecreatefromjpeg($src_path);
        if (!$image) {
            throw new Exception('Nie można wczytać obrazu');
        }
        
        // Auto-rotacja według EXIF
        if ($this->config['auto_rotate'] && $metadata['orientation']) {
            $image = $this->autoRotateImage($image, $metadata['orientation']);
        }
        
        $original_width = imagesx($image);
        $original_height = imagesy($image);
        $original_size = max($original_width, $original_height);
        
        // Sprawdź czy trzeba zmniejszać
        if ($original_size > $this->config['max_size']) {
            $ratio = $this->config['max_size'] / $original_size;
            $new_width = (int) round($original_width * $ratio);
            $new_height = (int) round($original_height * $ratio);
            
            $resized = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Progressive JPEG
        if ($this->config['progressive']) {
            imageinterlace($image, true);
        }
        
        // Zapisz
        if (!imagejpeg($image, $output_path, $this->config['quality'])) {
            imagedestroy($image);
            throw new Exception('Nie można zapisać obrazu');
        }
        
        imagedestroy($image);
        
        // Ustaw datę modyfikacji
        if ($this->config['preserve_exif']) {
            $target_time = $metadata['exif_timestamp'] ?: $metadata['file_timestamp'];
            $access_time = fileatime($src_path) ?: $target_time;
            @touch($output_path, $target_time, $access_time);
        }
        
        $this->log[] = "Przetworzono: {$file['original_name']} → $output_name";
        
        return [
            'original_name' => $file['original_name'],
            'processed_name' => $output_name,
            'path' => $output_path,
            'original_size' => $this->formatFileSize($file['size']),
            'new_size' => $this->formatFileSize(filesize($output_path)),
            'date_taken' => $metadata['exif_timestamp'] ? date('Y-m-d H:i:s', $metadata['exif_timestamp']) : null,
            'dimensions_original' => $metadata['dimensions'],
            'dimensions_new' => [imagesx($image ?? imagecreatefromjpeg($output_path)), imagesy($image ?? imagecreatefromjpeg($output_path))],
            'download_url' => 'download.php?session=' . $this->session_id . '&file=' . urlencode($output_name)
        ];
    }
    
    /**
     * Generuj nazwę pliku wyjściowego
     */
    private function generateOutputName($file, $index, $metadata) {
        $base_name = pathinfo($file['original_name'], PATHINFO_FILENAME);
        $extension = '.jpeg';
        
        switch ($this->config['output_format']) {
            case 'numbered':
                return sprintf('%03d_%s%s', $index + 1, $base_name, $extension);
                
            case 'dated':
                if ($metadata['exif_timestamp']) {
                    $date_prefix = date('Y-m-d', $metadata['exif_timestamp']);
                    return $date_prefix . '_' . $base_name . $extension;
                }
                return $base_name . $extension;
                
            case 'original':
            default:
                return $base_name . $extension;
        }
    }
    
    /**
     * Auto-rotacja obrazu według EXIF
     */
    private function autoRotateImage($image, $orientation) {
        switch ($orientation) {
            case 3: // 180°
                $rotated = imagerotate($image, 180, 0);
                imagedestroy($image);
                return $rotated;
                
            case 6: // 90° CW
                $rotated = imagerotate($image, -90, 0);
                imagedestroy($image);
                return $rotated;
                
            case 8: // 270° CW
                $rotated = imagerotate($image, 90, 0);
                imagedestroy($image);
                return $rotated;
                
            default:
                return $image;
        }
    }
    
    /**
     * Formatuj rozmiar pliku
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Utwórz archiwum ZIP z przetworzonymi plikami
     */
    private function createZipArchive($processed_files) {
        if (empty($processed_files)) {
            return null;
        }
        
        $zip_path = $this->temp_dir . '/processed_images_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Nie można utworzyć archiwum ZIP');
        }
        
        foreach ($processed_files as $file) {
            if (file_exists($file['path'])) {
                $zip->addFile($file['path'], $file['processed_name']);
            }
        }
        
        $zip->close();
        
        return 'download.php?session=' . $this->session_id . '&zip=1';
    }
    
    /**
     * Cleanup - usuń pliki tymczasowe
     */
    public function cleanup() {
        if (is_dir($this->temp_dir)) {
            $this->deleteDirectory($this->temp_dir);
        }
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// === GŁÓWNE PRZETWARZANIE ===

try {
    // Sprawdź czy przesłano pliki
    if (empty($_FILES['images'])) {
        throw new Exception('Nie przesłano żadnych plików');
    }
    
    // Pobierz konfigurację z formularza
    $config = [
        'max_size' => (int)($_POST['maxSize'] ?? 800),
        'quality' => (int)($_POST['quality'] ?? 85),
        'progressive' => (bool)($_POST['progressive'] ?? true),
        'preserve_exif' => (bool)($_POST['preserveExif'] ?? true),
        'sort_by' => $_POST['sortBy'] ?? 'exif_date',
        'sort_order' => $_POST['sortOrder'] ?? 'asc',
        'auto_rotate' => (bool)($_POST['autoRotate'] ?? true),
        'keep_original' => (bool)($_POST['keepOriginal'] ?? false),
        'output_format' => $_POST['outputFormat'] ?? 'original'
    ];
    
    // Walidacja konfiguracji
    $config['max_size'] = max(100, min(4000, $config['max_size']));
    $config['quality'] = max(50, min(100, $config['quality']));
    
    if (!in_array($config['sort_by'], ['exif_date', 'file_date', 'filename'])) {
        $config['sort_by'] = 'exif_date';
    }
    
    if (!in_array($config['sort_order'], ['asc', 'desc'])) {
        $config['sort_order'] = 'asc';
    }
    
    if (!in_array($config['output_format'], ['original', 'numbered', 'dated'])) {
        $config['output_format'] = 'original';
    }
    
    // Przetwórz obrazy
    $processor = new ImageProcessor($config);
    $result = $processor->processUploadedFiles($_FILES);
    
    // Zapisz informacje o sesji (dla późniejszego pobierania)
    $session_file = sys_get_temp_dir() . '/img_session_' . $result['session_id'] . '.json';
    file_put_contents($session_file, json_encode($result));
    
    // Dodatkowo zapisz session.json w katalogu sesji (dla combined ZIP)
    $session_dir = sys_get_temp_dir() . '/image_processor_' . $result['session_id'];
    if (is_dir($session_dir)) {
        $session_data = [
            'session_id' => $result['session_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'files' => $result['files'] ?? [],
            'stats' => $result['stats'] ?? [],
            'config' => $config
        ];
        file_put_contents($session_dir . '/session.json', json_encode($session_data, JSON_PRETTY_PRINT));
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // Cleanup w przypadku błędu
    if (isset($processor)) {
        $processor->cleanup();
    }
}

// Ustawmy cleanup na późniejszy czas (30 minut) - tylko dla produkcji
if (isset($result['session_id']) && !defined('TEST_MODE')) {
    ignore_user_abort(true);
    register_shutdown_function(function() use ($result) {
        // W trybie produkcyjnym użyj cron job zamiast sleep
        // sleep(1800); // 30 minut - USUNIĘTE aby uniknąć timeout
        
        // Zapisz zadanie cleanup do wykonania później
        $cleanup_file = sys_get_temp_dir() . '/cleanup_' . $result['session_id'] . '.scheduled';
        file_put_contents($cleanup_file, time() + 1800); // Za 30 minut
    });
}
?>
