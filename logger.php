<?php
/**
 * Szczegółowy system logowania dla Procesora Zdjęć
 * Umożliwia śledzenie każdego kroku przetwarzania
 */

class DetailedLogger {
    private $log_file;
    private $session_id;
    private $start_time;
    private $steps = [];
    
    public function __construct($session_id) {
        $this->session_id = $session_id;
        $this->start_time = microtime(true);
        $this->log_file = sys_get_temp_dir() . '/image_processor_' . $session_id . '.log';
        
        $this->log('SYSTEM', 'Logger inicjalizowany', [
            'session_id' => $session_id,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'temp_dir' => sys_get_temp_dir()
        ]);
    }
    
    /**
     * Główna metoda logowania
     */
    public function log($category, $message, $data = []) {
        $timestamp = date('Y-m-d H:i:s');
        $elapsed = round((microtime(true) - $this->start_time) * 1000, 2);
        $memory = $this->formatBytes(memory_get_usage(true));
        $peak_memory = $this->formatBytes(memory_get_peak_usage(true));
        
        $log_entry = [
            'timestamp' => $timestamp,
            'elapsed_ms' => $elapsed,
            'memory' => $memory,
            'peak_memory' => $peak_memory,
            'category' => $category,
            'message' => $message,
            'data' => $data
        ];
        
        $this->steps[] = $log_entry;
        
        // Zapisz do pliku
        $log_line = "[$timestamp] [{$elapsed}ms] [$memory] [$category] $message";
        if (!empty($data)) {
            $log_line .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $log_line .= "\n";
        
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        return $log_entry;
    }
    
    /**
     * Loguj krok przetwarzania
     */
    public function step($step_name, $details = []) {
        return $this->log('STEP', $step_name, $details);
    }
    
    /**
     * Loguj błąd
     */
    public function error($message, $details = []) {
        return $this->log('ERROR', $message, $details);
    }
    
    /**
     * Loguj ostrzeżenie  
     */
    public function warning($message, $details = []) {
        return $this->log('WARNING', $message, $details);
    }
    
    /**
     * Loguj informacje o pliku
     */
    public function fileInfo($filename, $action, $details = []) {
        return $this->log('FILE', "$action: $filename", $details);
    }
    
    /**
     * Loguj informacje o sesji
     */
    public function sessionInfo($action, $details = []) {
        return $this->log('SESSION', $action, $details);
    }
    
    /**
     * Pobierz wszystkie logi
     */
    public function getAllLogs() {
        return $this->steps;
    }
    
    /**
     * Pobierz statystyki
     */
    public function getStats() {
        $total_time = round((microtime(true) - $this->start_time) * 1000, 2);
        $categories = [];
        
        foreach ($this->steps as $step) {
            $cat = $step['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = 0;
            }
            $categories[$cat]++;
        }
        
        return [
            'total_time_ms' => $total_time,
            'total_steps' => count($this->steps),
            'categories' => $categories,
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'log_file' => $this->log_file
        ];
    }
    
    /**
     * Formatuj bajty
     */
    private function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($size) - 1) / 3);
        return sprintf("%.2f", $size / pow(1024, $factor)) . ' ' . $units[$factor];
    }
    
    /**
     * Sprawdź strukturę katalogów
     */
    public function debugDirectoryStructure($base_dir) {
        $this->step('Sprawdzanie struktury katalogów', ['base_dir' => $base_dir]);
        
        if (!is_dir($base_dir)) {
            $this->error('Katalog podstawowy nie istnieje', ['path' => $base_dir]);
            return false;
        }
        
        $structure = $this->scanDirRecursive($base_dir);
        $this->step('Struktura katalogów zeskanowana', [
            'total_items' => count($structure),
            'structure' => $structure
        ]);
        
        return $structure;
    }
    
    /**
     * Rekurencyjne skanowanie katalogów
     */
    private function scanDirRecursive($dir, $prefix = '') {
        $items = [];
        
        if (!is_dir($dir)) return $items;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            $relative_path = $prefix . $file;
            
            if (is_dir($path)) {
                $items[] = [
                    'type' => 'directory',
                    'name' => $relative_path,
                    'path' => $path,
                    'permissions' => substr(sprintf('%o', fileperms($path)), -4)
                ];
                $items = array_merge($items, $this->scanDirRecursive($path, $relative_path . '/'));
            } else {
                $items[] = [
                    'type' => 'file',
                    'name' => $relative_path,
                    'path' => $path,
                    'size' => filesize($path),
                    'size_formatted' => $this->formatBytes(filesize($path)),
                    'modified' => date('Y-m-d H:i:s', filemtime($path)),
                    'permissions' => substr(sprintf('%o', fileperms($path)), -4)
                ];
            }
        }
        
        return $items;
    }
    
    /**
     * Sprawdź status pliku
     */
    public function debugFileStatus($file_path, $description = '') {
        $desc = $description ? " ($description)" : '';
        
        if (!file_exists($file_path)) {
            $this->error("Plik nie istnieje$desc", ['path' => $file_path]);
            return false;
        }
        
        $info = [
            'path' => $file_path,
            'exists' => true,
            'readable' => is_readable($file_path),
            'writable' => is_writable($file_path),
            'size' => filesize($file_path),
            'size_formatted' => $this->formatBytes(filesize($file_path)),
            'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
            'permissions' => substr(sprintf('%o', fileperms($file_path)), -4)
        ];
        
        // Sprawdź typ pliku dla obrazów
        if (in_array(strtolower(pathinfo($file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg'])) {
            $image_info = @getimagesize($file_path);
            if ($image_info) {
                $info['image_width'] = $image_info[0];
                $info['image_height'] = $image_info[1];
                $info['image_type'] = $image_info[2];
                $info['image_mime'] = $image_info['mime'];
            }
            
            // EXIF info
            if (extension_loaded('exif')) {
                $exif = @exif_read_data($file_path, 'IFD0,EXIF', true);
                if ($exif) {
                    $info['exif_datetime'] = $exif['EXIF']['DateTimeOriginal'] ?? 
                                           $exif['EXIF']['DateTime'] ?? 
                                           $exif['IFD0']['DateTime'] ?? null;
                    $info['exif_orientation'] = $exif['IFD0']['Orientation'] ?? null;
                }
            }
        }
        
        $this->fileInfo(basename($file_path), "Status sprawdzony$desc", $info);
        return $info;
    }
    
    /**
     * Finalizuj logowanie
     */
    public function finalize() {
        $stats = $this->getStats();
        $this->log('SYSTEM', 'Logger finalizowany', $stats);
        
        // Zapisz podsumowanie do osobnego pliku
        $summary_file = str_replace('.log', '_summary.json', $this->log_file);
        file_put_contents($summary_file, json_encode([
            'session_id' => $this->session_id,
            'stats' => $stats,
            'all_logs' => $this->steps
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $stats;
    }
    
    /**
     * Cleanup - usuń stare logi
     */
    public static function cleanup($hours = 24) {
        $temp_dir = sys_get_temp_dir();
        $cutoff = time() - ($hours * 3600);
        
        $log_files = glob($temp_dir . '/image_processor_*.log');
        $summary_files = glob($temp_dir . '/image_processor_*_summary.json');
        $all_files = array_merge($log_files, $summary_files);
        
        $deleted = 0;
        foreach ($all_files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}
?>
