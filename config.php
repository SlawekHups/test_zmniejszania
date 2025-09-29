<?php
/**
 * CENTRALNY PLIK KONFIGURACYJNY
 * Wszystkie limity PHP i ustawienia aplikacji w jednym miejscu
 */

// ===============================================
// LIMITY PHP - AUTOMATYCZNE USTAWIENIE
// ===============================================

function setupPHPLimits() {
    // Limity dla dużej liczby plików (300+)
    $limits = [
        'upload_max_filesize' => '100M',        // Maksymalny rozmiar pojedynczego pliku
        'post_max_size' => '8000M',             // Maksymalny rozmiar całego POST (80 plików × 100MB)
        'max_input_vars' => '8000',             // Maksymalna liczba zmiennych POST/GET
        'max_execution_time' => '1200',         // 20 minut na przetwarzanie
        'memory_limit' => '4096M',              // 4GB pamięci
        'max_input_time' => '1200',             // 20 minut na upload
        'default_socket_timeout' => '1200'     // 20 minut timeout
    ];
    
    $applied = [];
    foreach ($limits as $setting => $value) {
        $old_value = ini_get($setting);
        $result = ini_set($setting, $value);
        $applied[$setting] = [
            'requested' => $value,
            'old' => $old_value,
            'current' => ini_get($setting),
            'success' => $result !== false
        ];
    }
    
    // max_file_uploads nie może być zmieniony przez ini_set()
    // Musi być ustawiony w php.ini lub przez parametr -d przy uruchomieniu
    $applied['max_file_uploads'] = [
        'requested' => '350',
        'current' => ini_get('max_file_uploads'),
        'note' => 'Nie można zmienić przez ini_set() - wymaga -d przy starcie serwera'
    ];
    
    return $applied;
}

// ===============================================
// KONFIGURACJA APLIKACJI
// ===============================================

class AppConfig {
    
    // Domyślne ustawienia przetwarzania obrazów
    const DEFAULT_SETTINGS = [
        'max_size' => 800,              // Maksymalny rozmiar w pikselach
        'quality' => 85,                // Jakość JPEG (70-95%)
        'progressive' => true,          // Progressive JPEG
        'preserve_exif' => true,        // Zachowaj dane EXIF
        'sort_by' => 'exif_date',      // Sortowanie według daty EXIF
        'sort_order' => 'asc',         // Kolejność sortowania
        'auto_rotate' => true,         // Automatyczna rotacja
        'keep_original' => false,      // Zachowaj oryginał
        'output_format' => 'original'  // Format wyjściowy
    ];
    
    // Limity wydajności
    const PERFORMANCE_LIMITS = [
        'recommended_batch_size' => 15,     // Zalecana liczba plików na raz
        'max_safe_batch_size' => 75,       // Maksymalna bezpieczna liczba
        'extreme_batch_size' => 300,       // Ekstremalny limit (zwiększony do 300)
        'processing_timeout' => 1200,      // Timeout przetwarzania (20 min)
        'cleanup_delay' => 2400            // Opóźnienie czyszczenia (40 min)
    ];
    
    // Ścieżki aplikacji
    const PATHS = [
        'temp_prefix' => 'image_processor_',
        'log_prefix' => 'image_processor_',
        'zip_prefix' => 'processed_images_'
    ];
    
    /**
     * Pobierz konfigurację z POST/GET lub użyj domyślnej
     */
    public static function getProcessingConfig() {
        return [
            'max_size' => (int)($_POST['maxSize'] ?? self::DEFAULT_SETTINGS['max_size']),
            'quality' => (int)($_POST['quality'] ?? self::DEFAULT_SETTINGS['quality']),
            'progressive' => ($_POST['progressive'] ?? '1') === '1',
            'preserve_exif' => ($_POST['preserveExif'] ?? '1') === '1',
            'sort_by' => $_POST['sortBy'] ?? self::DEFAULT_SETTINGS['sort_by'],
            'sort_order' => $_POST['sortOrder'] ?? self::DEFAULT_SETTINGS['sort_order'],
            'auto_rotate' => ($_POST['autoRotate'] ?? '1') === '1',
            'keep_original' => ($_POST['keepOriginal'] ?? '0') === '1',
            'output_format' => $_POST['outputFormat'] ?? self::DEFAULT_SETTINGS['output_format']
        ];
    }
    
    /**
     * Sprawdź czy liczba plików jest bezpieczna
     */
    public static function validateFileCount($count) {
        if ($count <= self::PERFORMANCE_LIMITS['recommended_batch_size']) {
            return ['status' => 'optimal', 'message' => 'Optymalna liczba plików'];
        } elseif ($count <= self::PERFORMANCE_LIMITS['max_safe_batch_size']) {
            return ['status' => 'good', 'message' => 'Dobra liczba plików'];
        } elseif ($count <= self::PERFORMANCE_LIMITS['extreme_batch_size']) {
            return ['status' => 'warning', 'message' => 'Duża liczba plików - może trwać długo'];
        } else {
            return ['status' => 'error', 'message' => 'Za dużo plików - przekroczono limit'];
        }
    }
    
    /**
     * Pobierz informacje o aktualnych limitach PHP
     */
    public static function getPHPLimits() {
        return [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => (int)ini_get('max_file_uploads'),
            'max_input_vars' => (int)ini_get('max_input_vars'),
            'max_execution_time' => (int)ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_input_time' => (int)ini_get('max_input_time')
        ];
    }
    
    /**
     * Sprawdź czy limity PHP są wystarczające dla danej liczby plików
     */
    public static function checkPHPLimits($file_count, $estimated_size_mb = 0) {
        $limits = self::getPHPLimits();
        $issues = [];
        
        // Sprawdź max_file_uploads
        if ($file_count > $limits['max_file_uploads']) {
            $issues[] = "max_file_uploads ({$limits['max_file_uploads']}) < liczba plików ($file_count)";
        }
        
        // Sprawdź post_max_size (jeśli znamy rozmiar)
        if ($estimated_size_mb > 0) {
            $post_limit_mb = (int)$limits['post_max_size'];
            if ($estimated_size_mb > $post_limit_mb) {
                $issues[] = "post_max_size ({$post_limit_mb}M) < szacowany rozmiar ({$estimated_size_mb}M)";
            }
        }
        
        // Sprawdź max_input_vars (około 5 zmiennych na plik)
        $needed_vars = $file_count * 5;
        if ($needed_vars > $limits['max_input_vars']) {
            $issues[] = "max_input_vars ({$limits['max_input_vars']}) < potrzebne zmienne ($needed_vars)";
        }
        
        return [
            'ok' => empty($issues),
            'issues' => $issues,
            'limits' => $limits
        ];
    }
}

// ===============================================
// AUTO-SETUP PRZY INCLUDOWANIU
// ===============================================

// Automatycznie ustaw limity PHP gdy plik jest includowany
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
    
    // Ustaw limity PHP
    $applied_limits = setupPHPLimits();
    
    // Opcjonalnie: loguj informacje o limitach (tylko w trybie debug)
    if (defined('DEBUG_CONFIG') && DEBUG_CONFIG) {
        error_log("Config.php loaded - Applied limits: " . json_encode($applied_limits));
    }
}

// ===============================================
// FUNKCJE POMOCNICZE
// ===============================================

/**
 * Formatuj rozmiar w bajtach na czytelny format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Konwertuj rozmiar PHP (np. "100M") na bajty
 */
function parseSize($size) {
    $unit = strtoupper(substr($size, -1));
    $value = (int)$size;
    
    switch ($unit) {
        case 'G': return $value * 1024 * 1024 * 1024;
        case 'M': return $value * 1024 * 1024;
        case 'K': return $value * 1024;
        default: return $value;
    }
}

/**
 * Sprawdź czy aplikacja działa w trybie development
 */
function isDevelopment() {
    return php_sapi_name() === 'cli-server' || 
           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
}

/**
 * Pobierz katalog tymczasowy systemu
 */
function getTempDir() {
    return sys_get_temp_dir();
}

/**
 * Generuj unikalny ID sesji
 */
function generateSessionId($prefix = 'img_') {
    return $prefix . uniqid('', true);
}

?>
