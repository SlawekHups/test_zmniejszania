<?php
/**
 * Kompleksowa diagnostyka systemu Procesora Zdjęć
 * Sprawdza wszystkie funkcjonalności, przeprowadza testy i daje rekomendacje
 */

// Załaduj centralną konfigurację
require_once 'config.php';

// Sprawdź czy to żądanie HTML czy JSON
$is_api = isset($_GET['format']) && $_GET['format'] === 'json';
$run_tests = isset($_GET['test']) && $_GET['test'] === '1';

if ($is_api) {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

// Funkcja do testowania podstawowych funkcjonalności
function runFunctionalityTests() {
    $tests = [];
    $temp_dir = sys_get_temp_dir();
    
    // Test 1: Utworzenie pliku tymczasowego
    $tests['temp_file_creation'] = [
        'name' => 'Tworzenie plików tymczasowych',
        'status' => 'pending',
        'message' => '',
        'details' => ''
    ];
    
    try {
        $test_file = $temp_dir . '/img_test_' . uniqid() . '.tmp';
        file_put_contents($test_file, 'test content');
        
        if (file_exists($test_file) && is_readable($test_file)) {
            unlink($test_file);
            $tests['temp_file_creation']['status'] = 'success';
            $tests['temp_file_creation']['message'] = 'Pliki tymczasowe działają poprawnie';
        } else {
            $tests['temp_file_creation']['status'] = 'error';
            $tests['temp_file_creation']['message'] = 'Nie można odczytać pliku tymczasowego';
        }
    } catch (Exception $e) {
        $tests['temp_file_creation']['status'] = 'error';
        $tests['temp_file_creation']['message'] = 'Błąd: ' . $e->getMessage();
    }
    
    // Test 2: Tworzenie obrazu testowego GD
    $tests['gd_image_creation'] = [
        'name' => 'Tworzenie obrazów GD',
        'status' => 'pending',
        'message' => '',
        'details' => ''
    ];
    
    if (extension_loaded('gd')) {
        try {
            $img = imagecreatetruecolor(100, 100);
            $white = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $white);
            
            if ($img) {
                $test_file = $temp_dir . '/test_image_' . uniqid() . '.jpg';
                $success = imagejpeg($img, $test_file, 85);
                imagedestroy($img);
                
                if ($success && file_exists($test_file)) {
                    $file_size = filesize($test_file);
                    unlink($test_file);
                    $tests['gd_image_creation']['status'] = 'success';
                    $tests['gd_image_creation']['message'] = "Obraz utworzony pomyślnie ({$file_size} bajtów)";
                } else {
                    $tests['gd_image_creation']['status'] = 'error';
                    $tests['gd_image_creation']['message'] = 'Nie można zapisać obrazu JPEG';
                }
            } else {
                $tests['gd_image_creation']['status'] = 'error';
                $tests['gd_image_creation']['message'] = 'Nie można utworzyć obrazu GD';
            }
        } catch (Exception $e) {
            $tests['gd_image_creation']['status'] = 'error';
            $tests['gd_image_creation']['message'] = 'Błąd GD: ' . $e->getMessage();
        }
    } else {
        $tests['gd_image_creation']['status'] = 'error';
        $tests['gd_image_creation']['message'] = 'Rozszerzenie GD nie jest dostępne';
    }
    
    // Test 3: Tworzenie archiwum ZIP
    $tests['zip_creation'] = [
        'name' => 'Tworzenie archiwów ZIP',
        'status' => 'pending',
        'message' => '',
        'details' => ''
    ];
    
    if (extension_loaded('zip')) {
        try {
            $zip_file = $temp_dir . '/test_archive_' . uniqid() . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                $zip->addFromString('test.txt', 'Test content for ZIP archive');
                $zip->close();
                
                if (file_exists($zip_file)) {
                    $file_size = filesize($zip_file);
                    unlink($zip_file);
                    $tests['zip_creation']['status'] = 'success';
                    $tests['zip_creation']['message'] = "Archiwum ZIP utworzone ({$file_size} bajtów)";
                } else {
                    $tests['zip_creation']['status'] = 'error';
                    $tests['zip_creation']['message'] = 'Plik ZIP nie został utworzony';
                }
            } else {
                $tests['zip_creation']['status'] = 'error';
                $tests['zip_creation']['message'] = 'Nie można otworzyć pliku ZIP do zapisu';
            }
        } catch (Exception $e) {
            $tests['zip_creation']['status'] = 'error';
            $tests['zip_creation']['message'] = 'Błąd ZIP: ' . $e->getMessage();
        }
    } else {
        $tests['zip_creation']['status'] = 'error';
        $tests['zip_creation']['message'] = 'Rozszerzenie ZIP nie jest dostępne';
    }
    
    // Test 4: Test konfiguracji aplikacji
    $tests['app_config'] = [
        'name' => 'Konfiguracja aplikacji',
        'status' => 'pending',
        'message' => '',
        'details' => ''
    ];
    
    try {
        $limits = AppConfig::getPHPLimits();
        $config = AppConfig::getProcessingConfig();
        
        if (!empty($limits) && !empty($config)) {
            $tests['app_config']['status'] = 'success';
            $tests['app_config']['message'] = 'Konfiguracja załadowana poprawnie';
            $tests['app_config']['details'] = "Max plików: {$limits['max_file_uploads']}, Pamięć: {$limits['memory_limit']}";
        } else {
            $tests['app_config']['status'] = 'error';
            $tests['app_config']['message'] = 'Błąd ładowania konfiguracji';
        }
    } catch (Exception $e) {
        $tests['app_config']['status'] = 'error';
        $tests['app_config']['message'] = 'Błąd config: ' . $e->getMessage();
    }
    
    // Test 5: Test EXIF (opcjonalny)
    $tests['exif_reading'] = [
        'name' => 'Odczyt danych EXIF',
        'status' => 'pending',
        'message' => '',
        'details' => ''
    ];
    
    if (extension_loaded('exif')) {
        try {
            // Utworz prosty obraz z podstawowymi danymi EXIF (symulacja)
            $test_functions = get_extension_funcs('exif');
            if (in_array('exif_read_data', $test_functions)) {
                $tests['exif_reading']['status'] = 'success';
                $tests['exif_reading']['message'] = 'Funkcje EXIF dostępne';
                $tests['exif_reading']['details'] = 'Funkcje: ' . implode(', ', array_slice($test_functions, 0, 3));
            } else {
                $tests['exif_reading']['status'] = 'warning';
                $tests['exif_reading']['message'] = 'EXIF zainstalowane, ale funkcje niedostępne';
            }
        } catch (Exception $e) {
            $tests['exif_reading']['status'] = 'warning';
            $tests['exif_reading']['message'] = 'EXIF częściowo dostępne: ' . $e->getMessage();
        }
    } else {
        $tests['exif_reading']['status'] = 'warning';
        $tests['exif_reading']['message'] = 'Rozszerzenie EXIF nie jest dostępne (opcjonalne)';
    }
    
    return $tests;
}

// Zbierz podstawowe informacje
function getSystemDiagnostics() {
    $php_limits = AppConfig::getPHPLimits();
    $temp_dir = sys_get_temp_dir();
    
    // Sprawdź dostępne rozszerzenia
    $extensions = [
        'gd' => [
            'loaded' => extension_loaded('gd'),
            'required' => true,
            'description' => 'Przetwarzanie obrazów'
        ],
        'exif' => [
            'loaded' => extension_loaded('exif'),
            'required' => false,
            'description' => 'Odczyt metadanych zdjęć'
        ],
        'zip' => [
            'loaded' => extension_loaded('zip'),
            'required' => true,
            'description' => 'Tworzenie archiwów'
        ],
        'json' => [
            'loaded' => extension_loaded('json'),
            'required' => true,
            'description' => 'Obsługa JSON'
        ],
        'fileinfo' => [
            'loaded' => extension_loaded('fileinfo'),
            'required' => false,
            'description' => 'Wykrywanie typów plików'
        ]
    ];
    
    // Dodatkowe informacje o GD
    $gd_details = [];
    if (extension_loaded('gd')) {
        $gd_info = gd_info();
        $gd_details = [
            'version' => $gd_info['GD Version'] ?? 'Unknown',
            'jpeg_support' => $gd_info['JPEG Support'] ?? false,
            'png_support' => $gd_info['PNG Support'] ?? false,
            'gif_support' => $gd_info['GIF Create Support'] ?? false,
            'freetype_support' => $gd_info['FreeType Support'] ?? false
        ];
    }
    
    // Sprawdź limity i czy są wystarczające
    $limits_analysis = [];
    foreach ($php_limits as $setting => $value) {
        $status = 'good';
        $recommendation = '';
        
        switch ($setting) {
            case 'max_file_uploads':
                if ($value < 50) {
                    $status = 'warning';
                    $recommendation = 'Zalecane minimum: 50 plików';
                } elseif ($value < 250) {
                    $status = 'good';
                    $recommendation = 'Wystarczające dla większości przypadków';
                } else {
                    $status = 'excellent';
                    $recommendation = 'Optymalne dla dużych partii';
                }
                break;
                
            case 'memory_limit':
                $memory_mb = parseSize($value) / 1024 / 1024;
                if ($memory_mb < 512) {
                    $status = 'error';
                    $recommendation = 'Za mało pamięci, minimum 512MB';
                } elseif ($memory_mb < 2048) {
                    $status = 'warning';
                    $recommendation = 'Zalecane minimum: 2048MB';
                } else {
                    $status = 'good';
                    $recommendation = 'Wystarczająca ilość pamięci';
                }
                break;
                
            case 'max_execution_time':
                if ($value < 300) {
                    $status = 'warning';
                    $recommendation = 'Za krótki czas dla większych partii';
                } elseif ($value < 900) {
                    $status = 'good';
                    $recommendation = 'Wystarczający dla większości przypadków';
                } else {
                    $status = 'excellent';
                    $recommendation = 'Optymalny czas wykonania';
                }
                break;
        }
        
        $limits_analysis[$setting] = [
            'value' => $value,
            'status' => $status,
            'recommendation' => $recommendation
        ];
    }
    
    // Sprawdź katalogi i permissions
    $directories = [
        'temp' => [
            'path' => $temp_dir,
            'exists' => is_dir($temp_dir),
            'readable' => is_readable($temp_dir),
            'writable' => is_writable($temp_dir),
            'free_space_gb' => round(disk_free_space($temp_dir) / 1024 / 1024 / 1024, 2)
        ]
    ];
    
    // Sprawdź obecność plików aplikacji
    $app_files = [
        'config.php' => file_exists('config.php'),
        'process.php' => file_exists('process.php'),
        'download.php' => file_exists('download.php'),
        'validate_capacity.php' => file_exists('validate_capacity.php'),
        'logger.php' => file_exists('logger.php'),
        'index.html' => file_exists('index.html'),
        'klasyczna.html' => file_exists('klasyczna.html'),
        'progressive_simple_fix.html' => file_exists('progressive_simple_fix.html')
    ];
    
    return [
        'server_info' => [
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'current_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ],
        'extensions' => $extensions,
        'gd_details' => $gd_details,
        'php_limits' => $limits_analysis,
        'directories' => $directories,
        'app_files' => $app_files,
        'memory' => [
            'current_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ]
    ];
}

$diagnostics = getSystemDiagnostics();
$tests = $run_tests ? runFunctionalityTests() : [];

// Dla kompatybilności wstecznej - jeśli nie ma parametrów, zwróć podstawowe JSON
if ($is_api || (!isset($_GET['format']) && !isset($_GET['test']) && empty($_GET))) {
    // Stary format dla kompatybilności
    if (empty($_GET)) {
        $legacy_format = [
            'php_version' => $diagnostics['server_info']['php_version'],
            'gd_available' => $diagnostics['extensions']['gd']['loaded'],
            'exif_available' => $diagnostics['extensions']['exif']['loaded'],
            'zip_available' => $diagnostics['extensions']['zip']['loaded'],
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => (int)ini_get('max_file_uploads'),
            'max_input_vars' => (int)ini_get('max_input_vars'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'temp_dir_writable' => $diagnostics['directories']['temp']['writable'],
            'gd_version' => $diagnostics['gd_details']['version'] ?? 'Unknown',
            'jpeg_support' => $diagnostics['gd_details']['jpeg_support'] ?? false,
            'working_dirs' => [
                'temp_accessible' => $diagnostics['directories']['temp']['exists'],
                'temp_writable' => $diagnostics['directories']['temp']['writable']
            ]
        ];
        echo json_encode($legacy_format, JSON_PRETTY_PRINT);
    } else {
        // Nowy format JSON
        echo json_encode([
            'diagnostics' => $diagnostics,
            'tests' => $tests
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} else {
    // HTML Dashboard
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostyka Systemu - Procesor Zdjęć</title>
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
        .nav-buttons a.active {
            background: #28a745;
        }
        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .diagnostic-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        .diagnostic-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
        }
        .diagnostic-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .diagnostic-value {
            font-weight: bold;
        }
        .status-excellent { color: #007bff; }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .test-result {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ddd;
        }
        .test-result.success {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .test-result.error {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        .test-result.warning {
            border-left-color: #ffc107;
            background: #fffdf8;
        }
        .recommendation {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        .overall-status {
            text-align: center;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: bold;
        }
        .overall-status.ready {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .overall-status.issues {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .overall-status.critical {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Diagnostyka Systemu - Procesor Zdjęć</h1>
            <p>Sprawdzanie konfiguracji, rozszerzeń i funkcjonalności</p>
        </div>

        <div class="nav-buttons">
            <a href="/">🏠 Strona główna</a>
            <a href="status.php">📊 Status serwera</a>
            <a href="check.php" class="active">🔧 Diagnostyka</a>
            <a href="check.php?test=1">🧪 Uruchom testy</a>
            <a href="check.php?format=json">📄 JSON API</a>
        </div>

        <?php
        // Określ ogólny status systemu
        $critical_issues = 0;
        $warnings = 0;
        
        foreach ($diagnostics['extensions'] as $ext => $info) {
            if ($info['required'] && !$info['loaded']) {
                $critical_issues++;
            }
        }
        
        foreach ($diagnostics['php_limits'] as $limit => $info) {
            if ($info['status'] === 'error') {
                $critical_issues++;
            } elseif ($info['status'] === 'warning') {
                $warnings++;
            }
        }
        
        if (!$diagnostics['directories']['temp']['writable']) {
            $critical_issues++;
        }
        
        $overall_class = 'ready';
        $overall_message = '✅ System gotowy do pracy!';
        
        if ($critical_issues > 0) {
            $overall_class = 'critical';
            $overall_message = "❌ Wykryto {$critical_issues} problemów krytycznych";
        } elseif ($warnings > 0) {
            $overall_class = 'issues';
            $overall_message = "⚠️ Wykryto {$warnings} ostrzeżeń";
        }
        ?>

        <div class="overall-status <?= $overall_class ?>">
            <?= $overall_message ?>
        </div>

        <div class="diagnostic-grid">
            <!-- Informacje o serwerze -->
            <div class="diagnostic-card">
                <h3>🖥️ Informacje o serwerze</h3>
                <div class="diagnostic-item">
                    <span>PHP:</span>
                    <span class="diagnostic-value status-good"><?= $diagnostics['server_info']['php_version'] ?></span>
                </div>
                <div class="diagnostic-item">
                    <span>SAPI:</span>
                    <span class="diagnostic-value"><?= $diagnostics['server_info']['php_sapi'] ?></span>
                </div>
                <div class="diagnostic-item">
                    <span>Serwer:</span>
                    <span class="diagnostic-value"><?= htmlspecialchars($diagnostics['server_info']['server_software']) ?></span>
                </div>
                <div class="diagnostic-item">
                    <span>Strefa czasowa:</span>
                    <span class="diagnostic-value"><?= $diagnostics['server_info']['timezone'] ?></span>
                </div>
            </div>

            <!-- Rozszerzenia PHP -->
            <div class="diagnostic-card">
                <h3>🔌 Rozszerzenia PHP</h3>
                <?php foreach ($diagnostics['extensions'] as $ext => $info): ?>
                <div class="diagnostic-item">
                    <span><?= strtoupper($ext) ?> <?= $info['required'] ? '(wymagane)' : '(opcjonalne)' ?>:</span>
                    <span class="diagnostic-value <?= $info['loaded'] ? 'status-good' : ($info['required'] ? 'status-error' : 'status-warning') ?>">
                        <?= $info['loaded'] ? '✅ Dostępne' : '❌ Brak' ?>
                    </span>
                </div>
                <div class="recommendation"><?= $info['description'] ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Szczegóły GD -->
            <?php if (!empty($diagnostics['gd_details'])): ?>
            <div class="diagnostic-card">
                <h3>🎨 Szczegóły GD</h3>
                <div class="diagnostic-item">
                    <span>Wersja:</span>
                    <span class="diagnostic-value"><?= $diagnostics['gd_details']['version'] ?></span>
                </div>
                <div class="diagnostic-item">
                    <span>JPEG:</span>
                    <span class="diagnostic-value <?= $diagnostics['gd_details']['jpeg_support'] ? 'status-good' : 'status-error' ?>">
                        <?= $diagnostics['gd_details']['jpeg_support'] ? '✅ Tak' : '❌ Nie' ?>
                    </span>
                </div>
                <div class="diagnostic-item">
                    <span>PNG:</span>
                    <span class="diagnostic-value <?= $diagnostics['gd_details']['png_support'] ? 'status-good' : 'status-warning' ?>">
                        <?= $diagnostics['gd_details']['png_support'] ? '✅ Tak' : '❌ Nie' ?>
                    </span>
                </div>
                <div class="diagnostic-item">
                    <span>GIF:</span>
                    <span class="diagnostic-value <?= $diagnostics['gd_details']['gif_support'] ? 'status-good' : 'status-warning' ?>">
                        <?= $diagnostics['gd_details']['gif_support'] ? '✅ Tak' : '❌ Nie' ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Limity PHP -->
            <div class="diagnostic-card">
                <h3>⚙️ Analiza limitów PHP</h3>
                <?php foreach ($diagnostics['php_limits'] as $setting => $info): ?>
                <div class="diagnostic-item">
                    <span><?= str_replace('_', ' ', ucfirst($setting)) ?>:</span>
                    <span class="diagnostic-value status-<?= $info['status'] ?>"><?= $info['value'] ?></span>
                </div>
                <div class="recommendation"><?= $info['recommendation'] ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Katalogi i uprawnienia -->
            <div class="diagnostic-card">
                <h3>📁 Katalogi systemu</h3>
                <?php foreach ($diagnostics['directories'] as $name => $dir): ?>
                <div class="diagnostic-item">
                    <span>Katalog <?= $name ?>:</span>
                    <span class="diagnostic-value <?= $dir['writable'] ? 'status-good' : 'status-error' ?>">
                        <?= $dir['writable'] ? '✅ Zapisywalny' : '❌ Błąd zapisu' ?>
                    </span>
                </div>
                <div class="recommendation">
                    Ścieżka: <?= $dir['path'] ?><br>
                    Wolne miejsce: <?= $dir['free_space_gb'] ?> GB
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pliki aplikacji -->
            <div class="diagnostic-card">
                <h3>📄 Pliki aplikacji</h3>
                <?php foreach ($diagnostics['app_files'] as $file => $exists): ?>
                <div class="diagnostic-item">
                    <span><?= $file ?>:</span>
                    <span class="diagnostic-value <?= $exists ? 'status-good' : 'status-error' ?>">
                        <?= $exists ? '✅ Istnieje' : '❌ Brak' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pamięć -->
            <div class="diagnostic-card">
                <h3>💾 Zużycie pamięci</h3>
                <div class="diagnostic-item">
                    <span>Aktualne:</span>
                    <span class="diagnostic-value"><?= $diagnostics['memory']['current_usage_mb'] ?> MB</span>
                </div>
                <div class="diagnostic-item">
                    <span>Szczyt:</span>
                    <span class="diagnostic-value"><?= $diagnostics['memory']['peak_usage_mb'] ?> MB</span>
                </div>
            </div>
        </div>

        <?php if (!empty($tests)): ?>
        <div class="diagnostic-card">
            <h3>🧪 Wyniki testów funkcjonalności</h3>
            <?php foreach ($tests as $test_id => $test): ?>
            <div class="test-result <?= $test['status'] ?>">
                <h4><?= $test['name'] ?></h4>
                <p><strong>Status:</strong> 
                    <?php if ($test['status'] === 'success'): ?>
                        ✅ Sukces
                    <?php elseif ($test['status'] === 'error'): ?>
                        ❌ Błąd
                    <?php else: ?>
                        ⚠️ Ostrzeżenie
                    <?php endif; ?>
                </p>
                <p><?= $test['message'] ?></p>
                <?php if (!empty($test['details'])): ?>
                <div class="recommendation"><?= $test['details'] ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em;">
            ⏰ Ostatnie sprawdzenie: <?= date('H:i:s') ?> | 
            🔄 <a href="javascript:location.reload()" style="color: #667eea;">Odśwież</a>
        </div>
    </div>
</body>
</html>
<?php
}
?>
