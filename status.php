<?php
/**
 * Kompleksowy status serwera i sesji przetwarzania
 * Pokazuje informacje o systemie, aktywnych sesjach i konfiguracji
 */

// Załaduj centralną konfigurację
require_once 'config.php';

// Sprawdź czy to żądanie HTML czy JSON
$is_api = isset($_GET['format']) && $_GET['format'] === 'json';
$show_sessions = isset($_GET['sessions']) && $_GET['sessions'] === '1';

if ($is_api) {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

// Funkcja sprawdzenia statusu konkretnej sesji
function checkJobStatus($job_id) {
    $job_id = preg_replace('/[^a-zA-Z0-9_.-]/', '', $job_id);
    $session_file = sys_get_temp_dir() . '/img_session_' . $job_id . '.json';
    
    if (!file_exists($session_file)) {
        return ['completed' => false, 'error' => 'Zadanie nie istnieje'];
    }
    
    $session_data = json_decode(file_get_contents($session_file), true);
    if (!$session_data) {
        return ['completed' => false, 'error' => 'Nieprawidłowe dane sesji'];
    }
    
    $completed = isset($session_data['success']) && $session_data['success'];
    
    if ($completed) {
        return array_merge($session_data, ['completed' => true]);
    } else {
        return ['completed' => false, 'message' => 'Przetwarzanie w toku...'];
    }
}

// Sprawdź konkretną sesję jeśli podano job_id
if (!empty($_GET['job'])) {
    $result = checkJobStatus($_GET['job']);
    if ($is_api) {
        echo json_encode($result);
    } else {
        echo "<h2>Status sesji: " . htmlspecialchars($_GET['job']) . "</h2>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    exit;
}

// Zbierz informacje o systemie
function getSystemStatus() {
    $php_limits = AppConfig::getPHPLimits();
    $temp_dir = sys_get_temp_dir();
    
    // Sprawdź dostępne rozszerzenia
    $extensions = [
        'gd' => extension_loaded('gd'),
        'exif' => extension_loaded('exif'),
        'zip' => extension_loaded('zip'),
        'json' => extension_loaded('json')
    ];
    
    // Sprawdź zapisy do katalogu tymczasowego
    $temp_writable = is_writable($temp_dir);
    $temp_free_space = disk_free_space($temp_dir);
    
    // Sprawdź aktywne sesje przetwarzania
    $active_sessions = [];
    $session_files = glob($temp_dir . '/img_session_*.json');
    
    foreach ($session_files as $file) {
        $session_id = basename($file, '.json');
        $session_id = str_replace('img_session_', '', $session_id);
        $data = json_decode(file_get_contents($file), true);
        $file_time = filemtime($file);
        
        $active_sessions[] = [
            'session_id' => $session_id,
            'created' => date('Y-m-d H:i:s', $file_time),
            'age_minutes' => round((time() - $file_time) / 60, 1),
            'completed' => isset($data['success']) && $data['success'],
            'files_count' => count($data['files'] ?? []),
            'file_size_kb' => round(filesize($file) / 1024, 2)
        ];
    }
    
    // Sprawdź pliki do cleanup
    $cleanup_files = glob($temp_dir . '/cleanup_*.scheduled');
    $scheduled_cleanups = [];
    
    foreach ($cleanup_files as $file) {
        $cleanup_time = file_get_contents($file);
        $session_id = basename($file, '.scheduled');
        $session_id = str_replace('cleanup_', '', $session_id);
        
        $scheduled_cleanups[] = [
            'session_id' => $session_id,
            'scheduled_time' => date('Y-m-d H:i:s', $cleanup_time),
            'minutes_left' => round(($cleanup_time - time()) / 60, 1)
        ];
    }
    
    // Sprawdź katalogi sesji progresywnych
    $progressive_dirs = glob($temp_dir . '/image_processor_*', GLOB_ONLYDIR);
    $progressive_sessions = [];
    
    foreach ($progressive_dirs as $dir) {
        $session_id = basename($dir);
        $session_id = str_replace('image_processor_', '', $session_id);
        $session_file = $dir . '/session.json';
        
        if (file_exists($session_file)) {
            $data = json_decode(file_get_contents($session_file), true);
            $progressive_sessions[] = [
                'session_id' => $session_id,
                'created' => $data['created_at'] ?? 'unknown',
                'files_count' => count($data['files'] ?? []),
                'dir_size_mb' => round(getDirSize($dir) / 1024 / 1024, 2)
            ];
        }
    }
    
    return [
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'current_time' => date('Y-m-d H:i:s'),
            'uptime_check' => file_exists('server.log') ? date('Y-m-d H:i:s', filemtime('server.log')) : 'Unknown'
        ],
        'php_limits' => $php_limits,
        'extensions' => $extensions,
        'storage' => [
            'temp_dir' => $temp_dir,
            'temp_writable' => $temp_writable,
            'free_space_gb' => round($temp_free_space / 1024 / 1024 / 1024, 2)
        ],
        'memory' => [
            'current_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit_mb' => round(parseSize($php_limits['memory_limit']) / 1024 / 1024, 2)
        ],
        'sessions' => [
            'active_count' => count($active_sessions),
            'progressive_count' => count($progressive_sessions),
            'cleanup_scheduled' => count($scheduled_cleanups),
            'details' => $active_sessions,
            'progressive_details' => $progressive_sessions,
            'cleanup_details' => $scheduled_cleanups
        ]
    ];
}

// Pomocnicza funkcja do obliczania rozmiaru katalogu
function getDirSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getDirSize($each);
    }
    return $size;
}

$status = getSystemStatus();

if ($is_api) {
    // Zwróć JSON
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Pokaż HTML dashboard
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Serwera - Procesor Zdjęć</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .status-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        .status-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .status-value {
            font-weight: bold;
            color: #667eea;
        }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sessions-table th,
        .sessions-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .sessions-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .nav-buttons {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-buttons a {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            margin: 0 10px;
            display: inline-block;
        }
        .nav-buttons a:hover {
            background: #5a6fd8;
        }
        .refresh-info {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Status Serwera - Procesor Zdjęć</h1>
            <p>Monitorowanie systemu i aktywnych sesji przetwarzania</p>
        </div>

        <div class="nav-buttons">
            <a href="/">🏠 Strona główna</a>
            <a href="check.php">🔧 Diagnostyka</a>
            <a href="status.php?format=json">📄 JSON API</a>
            <a href="javascript:location.reload()">🔄 Odśwież</a>
        </div>

        <div class="status-grid">
            <!-- Informacje o serwerze -->
            <div class="status-card">
                <h3>🖥️ Serwer</h3>
                <div class="status-item">
                    <span>PHP:</span>
                    <span class="status-value"><?= $status['server_info']['php_version'] ?></span>
                </div>
                <div class="status-item">
                    <span>Serwer:</span>
                    <span class="status-value"><?= htmlspecialchars($status['server_info']['server_software']) ?></span>
                </div>
                <div class="status-item">
                    <span>Czas:</span>
                    <span class="status-value"><?= $status['server_info']['current_time'] ?></span>
                </div>
                <div class="status-item">
                    <span>Ostatni start:</span>
                    <span class="status-value"><?= $status['server_info']['uptime_check'] ?></span>
                </div>
            </div>

            <!-- Limity PHP -->
            <div class="status-card">
                <h3>⚙️ Limity PHP</h3>
                <div class="status-item">
                    <span>Max plików:</span>
                    <span class="status-value status-good"><?= $status['php_limits']['max_file_uploads'] ?></span>
                </div>
                <div class="status-item">
                    <span>Pamięć:</span>
                    <span class="status-value status-good"><?= $status['php_limits']['memory_limit'] ?></span>
                </div>
                <div class="status-item">
                    <span>POST size:</span>
                    <span class="status-value status-good"><?= $status['php_limits']['post_max_size'] ?></span>
                </div>
                <div class="status-item">
                    <span>Czas wykonania:</span>
                    <span class="status-value status-good"><?= $status['php_limits']['max_execution_time'] ?>s</span>
                </div>
            </div>

            <!-- Rozszerzenia -->
            <div class="status-card">
                <h3>🔌 Rozszerzenia</h3>
                <?php foreach ($status['extensions'] as $ext => $loaded): ?>
                <div class="status-item">
                    <span><?= strtoupper($ext) ?>:</span>
                    <span class="status-value <?= $loaded ? 'status-good' : 'status-error' ?>">
                        <?= $loaded ? '✅ Dostępne' : '❌ Brak' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pamięć -->
            <div class="status-card">
                <h3>💾 Pamięć</h3>
                <div class="status-item">
                    <span>Aktualne:</span>
                    <span class="status-value"><?= $status['memory']['current_usage_mb'] ?> MB</span>
                </div>
                <div class="status-item">
                    <span>Szczyt:</span>
                    <span class="status-value"><?= $status['memory']['peak_usage_mb'] ?> MB</span>
                </div>
                <div class="status-item">
                    <span>Limit:</span>
                    <span class="status-value"><?= $status['memory']['limit_mb'] ?> MB</span>
                </div>
                <div class="status-item">
                    <span>Wykorzystanie:</span>
                    <span class="status-value">
                        <?= round(($status['memory']['current_usage_mb'] / $status['memory']['limit_mb']) * 100, 1) ?>%
                    </span>
                </div>
            </div>

            <!-- Przechowywanie -->
            <div class="status-card">
                <h3>💿 Przechowywanie</h3>
                <div class="status-item">
                    <span>Katalog temp:</span>
                    <span class="status-value <?= $status['storage']['temp_writable'] ? 'status-good' : 'status-error' ?>">
                        <?= $status['storage']['temp_writable'] ? '✅ Zapisywalny' : '❌ Błąd zapisu' ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Wolne miejsce:</span>
                    <span class="status-value"><?= $status['storage']['free_space_gb'] ?> GB</span>
                </div>
            </div>

            <!-- Sesje -->
            <div class="status-card">
                <h3>📋 Sesje</h3>
                <div class="status-item">
                    <span>Aktywne:</span>
                    <span class="status-value"><?= $status['sessions']['active_count'] ?></span>
                </div>
                <div class="status-item">
                    <span>Progresywne:</span>
                    <span class="status-value"><?= $status['sessions']['progressive_count'] ?></span>
                </div>
                <div class="status-item">
                    <span>Do cleanup:</span>
                    <span class="status-value"><?= $status['sessions']['cleanup_scheduled'] ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($status['sessions']['details'])): ?>
        <div class="status-card">
            <h3>📄 Aktywne sesje przetwarzania</h3>
            <table class="sessions-table">
                <thead>
                    <tr>
                        <th>ID Sesji</th>
                        <th>Utworzona</th>
                        <th>Wiek (min)</th>
                        <th>Status</th>
                        <th>Plików</th>
                        <th>Rozmiar (KB)</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['sessions']['details'] as $session): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($session['session_id']) ?></code></td>
                        <td><?= $session['created'] ?></td>
                        <td><?= $session['age_minutes'] ?></td>
                        <td>
                            <span class="<?= $session['completed'] ? 'status-good' : 'status-warning' ?>">
                                <?= $session['completed'] ? '✅ Zakończona' : '⏳ W toku' ?>
                            </span>
                        </td>
                        <td><?= $session['files_count'] ?></td>
                        <td><?= $session['file_size_kb'] ?></td>
                        <td>
                            <a href="status.php?job=<?= urlencode($session['session_id']) ?>" 
                               style="color: #667eea; text-decoration: none;">🔍 Szczegóły</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="refresh-info">
            <p>⏰ Ostatnie odświeżenie: <?= date('H:i:s') ?> | 
               🔄 Odświeżaj regularnie aby śledzić aktywne sesje</p>
        </div>
    </div>

    <script>
        // Auto-refresh co 30 sekund jeśli są aktywne sesje
        <?php if ($status['sessions']['active_count'] > 0): ?>
        setTimeout(() => {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
}
?>
