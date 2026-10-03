<?php
// View screenshot from command result
session_start();

if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$screenshot_data = isset($_GET['data']) ? $_GET['data'] : '';

if (empty($screenshot_data)) {
    die("No screenshot data provided");
}

// Check if it starts with SCREENSHOT: prefix
if (strpos($screenshot_data, 'SCREENSHOT:') === 0) {
    $screenshot_data = substr($screenshot_data, 11); // Remove "SCREENSHOT:" prefix
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Screenshot Viewer</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0e27; color: #eee; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #3282b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .screenshot-container { background: #16213e; padding: 20px; border-radius: 10px; text-align: center; }
        .screenshot-container img { max-width: 100%; height: auto; border: 2px solid #0f4c75; border-radius: 5px; }
        .download-btn { background: #51cf66; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; }
        .info { background: #0f4c75; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📸 Screenshot Viewer</h1>
        <a href="javascript:history.back()" class="back-btn">← Back</a>
    </div>
    
    <div class="screenshot-container">
        <div class="info">
            <strong>Screenshot captured successfully</strong><br>
            Size: <?= number_format(strlen($screenshot_data) * 3 / 4) ?> bytes
        </div>
        
        <img src="data:image/bmp;base64,<?= htmlspecialchars($screenshot_data) ?>" alt="Screenshot">
        
        <a href="data:image/bmp;base64,<?= htmlspecialchars($screenshot_data) ?>" download="screenshot.bmp" class="download-btn">
            💾 Download Screenshot
        </a>
    </div>
</body>
</html>
