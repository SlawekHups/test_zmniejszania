<?php
/**
 * Sprawdzenie statusu zadania przetwarzania
 */

header('Content-Type: application/json; charset=utf-8');

if (empty($_GET['job'])) {
    echo json_encode(['error' => 'Brak identyfikatora zadania']);
    exit;
}

$job_id = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_GET['job']);
$session_file = sys_get_temp_dir() . '/img_session_' . $job_id . '.json';

if (!file_exists($session_file)) {
    echo json_encode(['completed' => false, 'error' => 'Zadanie nie istnieje']);
    exit;
}

$session_data = json_decode(file_get_contents($session_file), true);
if (!$session_data) {
    echo json_encode(['completed' => false, 'error' => 'Nieprawidłowe dane sesji']);
    exit;
}

// Sprawdź czy przetwarzanie zostało zakończone
$completed = isset($session_data['success']) && $session_data['success'];

if ($completed) {
    echo json_encode(array_merge($session_data, ['completed' => true]));
} else {
    echo json_encode(['completed' => false, 'message' => 'Przetwarzanie w toku...']);
}
?>
