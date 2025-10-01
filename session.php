<?php
// session.php - Centralized Session Manager
header('Content-Type: text/html; charset=UTF-8');

$sessionFile = 'shared_session.txt';
$password = 'admin123'; // Change this to your preferred password

// Handle form submission
if ($_POST['action'] === 'save_session') {
    if ($_POST['password'] !== $password) {
        $error = "❌ Invalid password!";
    } else {
        $sessionId = trim($_POST['session_id']);
        if (empty($sessionId)) {
            $error = "❌ Session ID cannot be empty!";
        } else {
            file_put_contents($sessionFile, $sessionId);
            $success = "✅ Session saved successfully!";
            
            // Test the session immediately
            header("Location: /?test_session=1&session_id=" . urlencode($sessionId));
            exit;
        }
    }
}

// Handle session clearing
if ($_POST['action'] === 'clear_session') {
    if ($_POST['password'] !== $password) {
        $error = "❌ Invalid password!";
    } else {
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
        $success = "✅ Session cleared!";
    }
}

$currentSession = file_exists($sessionFile) ? file_get_contents($sessionFile) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Smartrz Session Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0d0d0d; 
            color: #ffffff; 
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: rgba(255,255,255,0.05);
            padding: 30px; 
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        h1 { 
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 30px;
            font-family: 'JetBrains Mono', monospace;
        }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: rgba(255,255,255,0.8); }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            font-size: 16px;
        }
        input:focus { 
            outline: none;
            border-color: #4a90e2;
        }
        button { 
            background: linear-gradient(135deg, #4a90e2, #2ad9b5);
            color: white; 
            padding: 12px 30px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin: 5px 0;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        .btn-clear { 
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
        }
        .session-display {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            word-break: break-all;
            border: 1px dashed rgba(255,255,255,0.2);
        }
        .instructions {
            background: rgba(74, 144, 226, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #4a90e2;
        }
        .instructions ol { padding-left: 20px; }
        .instructions li { margin-bottom: 10px; }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
        }
        .status.success { background: rgba(0, 204, 102, 0.2); border: 1px solid #00cc66; }
        .status.error { background: rgba(255, 107, 107, 0.2); border: 1px solid #ff6b6b; }
        .status.info { background: rgba(74, 144, 226, 0.2); border: 1px solid #4a90e2; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Smartrz Session Manager</h1>
        
        <?php if (isset($success)): ?>
            <div class="status success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="status error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="session-display">
            <strong>Current Shared Session:</strong><br>
            <?php if ($currentSession): ?>
                <code style="color: #2ad9b5;"><?php echo htmlspecialchars($currentSession); ?></code>
                <div style="margin-top: 10px;">
                    <a href="/?test_session=1&session_id=<?php echo urlencode($currentSession); ?>" 
                       target="_blank" style="color: #4a90e2; text-decoration: none;">
                       🧪 Test This Session
                    </a>
                </div>
            <?php else: ?>
                <span style="color: #ff6b6b;">No session set</span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_session">
            
            <div class="form-group">
                <label>🔑 Admin Password:</label>
                <input type="password" name="password" required 
                       placeholder="Enter admin password...">
            </div>
            
            <div class="form-group">
                <label>🆔 PHPSESSID:</label>
                <input type="text" name="session_id" required 
                       placeholder="Paste PHPSESSID here..."
                       value="<?php echo htmlspecialchars($currentSession); ?>">
            </div>
            
            <button type="submit">💾 Save Shared Session</button>
        </form>

        <form method="POST">
            <input type="hidden" name="action" value="clear_session">
            <div class="form-group">
                <label>🔑 Admin Password to Clear:</label>
                <input type="password" name="password" required 
                       placeholder="Enter admin password...">
            </div>
            <button type="submit" class="btn-clear">🗑️ Clear Session</button>
        </form>

        <div class="instructions">
            <h3>📖 How to Get PHPSESSID:</h3>
            <ol>
                <li>Visit <code>rolexcoderz.live</code> in your browser</li>
                <li>Complete the CAPTCHA verification</li>
                <li>Press <kbd>F12</kbd> to open Developer Tools</li>
                <li>Go to <strong>Application</strong> → <strong>Storage</strong> → <strong>Cookies</strong></li>
                <li>Find <code>PHPSESSID</code> and copy its value</li>
                <li>Paste it above and save (password: <code>admin123</code>)</li>
            </ol>
            <p><strong>💡 Tip:</strong> This session will be shared with all users of your Smartrz site!</p>
        </div>

        <div class="status info">
            <strong>🔗 Quick Links:</strong><br>
            <a href="/" style="color: #4a90e2;">🏠 Main Site</a> | 
            <a href="/?test_all_sessions=1" style="color: #4a90e2;">🧪 Test All Features</a>
        </div>
    </div>
</body>
</html>
