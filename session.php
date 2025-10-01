<?php
// session.php - Centralized Session Manager
header('Content-Type: text/html; charset=UTF-8');

$sessionFile = 'shared_session.txt';
$password = 'admin123'; // Change this to your preferred password

$showForm = false;
$error = '';
$success = '';

// Handle password verification
if ($_POST['action'] === 'verify_password') {
    if ($_POST['password'] !== $password) {
        $error = "❌ Invalid password!";
    } else {
        $showForm = true;
        $_SESSION['verified'] = true;
    }
}

// Handle form submission
if ($_POST['action'] === 'save_session' && $_SESSION['verified']) {
    $sessionId = trim($_POST['session_id']);
    if (empty($sessionId)) {
        $error = "❌ Session ID cannot be empty!";
    } else {
        file_put_contents($sessionFile, $sessionId);
        $success = "✅ Session saved successfully!";
        $showForm = true;
    }
}

// Handle session clearing
if ($_POST['action'] === 'clear_session' && $_SESSION['verified']) {
    if (file_exists($sessionFile)) {
        unlink($sessionFile);
    }
    $success = "✅ Session cleared!";
    $showForm = true;
}

$currentSession = file_exists($sessionFile) ? file_get_contents($sessionFile) : '';

// If already verified in this session, show form
if (isset($_SESSION['verified']) && $_SESSION['verified']) {
    $showForm = true;
}
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 500px; 
            width: 100%;
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
            font-size: 1.8rem;
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
            text-align: center;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
        }
        .status.success { background: rgba(0, 204, 102, 0.2); border: 1px solid #00cc66; }
        .status.error { background: rgba(255, 107, 107, 0.2); border: 1px solid #ff6b6b; }
        .hidden { display: none; }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #4a90e2;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Session Manager</h1>
        
        <?php if ($success): ?>
            <div class="status success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="status error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Password Verification Form -->
        <div id="passwordSection" class="<?php echo $showForm ? 'hidden' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="action" value="verify_password">
                
                <div class="form-group">
                    <label>Enter Admin Password:</label>
                    <input type="password" name="password" required 
                           placeholder="Enter password to continue...">
                </div>
                
                <button type="submit">🔓 Access Session Manager</button>
            </form>
        </div>

        <!-- Session Management Form -->
        <div id="sessionSection" class="<?php echo !$showForm ? 'hidden' : ''; ?>">
            <?php if ($currentSession): ?>
                <div class="session-display">
                    <strong>Current Session:</strong><br>
                    <code style="color: #2ad9b5;"><?php echo htmlspecialchars($currentSession); ?></code>
                </div>
            <?php else: ?>
                <div class="session-display" style="color: #ff6b6b;">
                    <strong>No session currently set</strong>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="save_session">
                
                <div class="form-group">
                    <label>PHPSESSID:</label>
                    <input type="text" name="session_id" required 
                           placeholder="Paste PHPSESSID here..."
                           value="<?php echo htmlspecialchars($currentSession); ?>">
                </div>
                
                <button type="submit">💾 Save Session</button>
            </form>

            <?php if ($currentSession): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_session">
                    <button type="submit" class="btn-clear">🗑️ Clear Session</button>
                </form>
            <?php endif; ?>

            <div class="back-link">
                <a href="/">← Back to Main Site</a>
            </div>
        </div>
    </div>
</body>
</html>
