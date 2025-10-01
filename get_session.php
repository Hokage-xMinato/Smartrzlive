<?php
// get_session.php - API endpoint to get current session
header('Content-Type: application/json');

$sessionFile = 'shared_session.txt';

if (file_exists($sessionFile)) {
    $sessionId = trim(file_get_contents($sessionFile));
    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'timestamp' => time()
    ]);
} else {
    echo json_encode([
        'success' => false,
        'session_id' => null,
        'timestamp' => time()
    ]);
}
?>
