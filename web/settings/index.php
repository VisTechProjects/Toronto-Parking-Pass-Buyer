<?php
$settingsFile = '/home/admin/Toronto-Parking-Pass-Buyer/config/settings.json';
$envFile = '/home/admin/Toronto-Parking-Pass-Buyer/.env';

// Load settings
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Load credentials from .env
$authUser = null;
$authPass = null;
$emailFrom = null;
$emailTo = null;
$emailAppPassword = null;

if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/^SETTINGS_USER=(.+)$/m', $envContent, $m)) {
        $authUser = trim($m[1]);
    }
    if (preg_match('/^SETTINGS_PASS=(.+)$/m', $envContent, $m)) {
        $authPass = trim($m[1]);
    }
    if (preg_match('/^EMAIL_FROM="?([^"\n]+)"?$/m', $envContent, $m)) {
        $emailFrom = trim($m[1]);
    }
    if (preg_match('/^EMAIL_TO="?([^"\n]+)"?$/m', $envContent, $m)) {
        $emailTo = trim($m[1]);
    }
    if (preg_match('/^EMAIL_APP_PASSWORD="?([^"\n]+)"?$/m', $envContent, $m)) {
        $emailAppPassword = trim($m[1]);
    }
}

function sendSettingsEmail($to, $from, $password, $subject, $body) {
    $headers = [
        "From: $from",
        "Reply-To: $from",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "X-Priority: 1",
        "X-MSMail-Priority: High",
        "Importance: High"
    ];

    // Use PHP mail() - server should have sendmail configured
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// Check if auth is configured
$authConfigured = $authUser && $authPass;

// Handle form submission (requires auth via POST password field)
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $authenticated = false;

    if ($authConfigured) {
        // Check password from modal
        if (isset($_POST['password']) && $_POST['password'] === $authPass) {
            $authenticated = true;
        }

        if (!$authenticated) {
            $message = 'Incorrect password.';
            $messageType = 'error';
        }
    } else {
        // No auth configured - allow changes (for initial setup)
        $authenticated = true;
    }

    if ($authenticated && $_POST['action'] === 'toggle_autobuyer') {
        $currentEnabled = $settings['autobuyer']['enabled'] ?? true;
        $settings['autobuyer'] = $settings['autobuyer'] ?? [];
        $settings['autobuyer']['enabled'] = !$currentEnabled;
        $newState = $settings['autobuyer']['enabled'];

        if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
            $message = 'Auto-buyer ' . ($newState ? 'enabled' : 'disabled') . '.';
            $messageType = 'success';

            // Send email notification
            if ($emailFrom && $emailTo) {
                $stateText = $newState ? 'ENABLED' : 'DISABLED';
                $stateColor = $newState ? '#4caf50' : '#f44336';
                $emailSubject = "Parking Auto-buyer $stateText";
                $emailBody = "
                <html>
                <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
                    <div style='max-width: 400px; margin: 0 auto; background: white; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                        <h2 style='margin: 0 0 16px; color: #333;'>Auto-buyer Setting Changed</h2>
                        <p style='margin: 0 0 16px; color: #666;'>The parking permit auto-buyer has been <strong style='color: $stateColor;'>$stateText</strong>.</p>
                        <p style='margin: 0; font-size: 12px; color: #999;'>Changed at: " . date('M j, Y g:i A') . "<br>From: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
                    </div>
                </body>
                </html>";
                sendSettingsEmail($emailTo, $emailFrom, $emailAppPassword, $emailSubject, $emailBody);
            }
        } else {
            $message = 'Failed to save settings.';
            $messageType = 'error';
        }
    }
}

// Get current state
$autobuyerEnabled = $settings['autobuyer']['enabled'] ?? true;
$expectedPrice = $settings['pricing']['expected_weekly_price'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Parking Settings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1f2e;
            color: #e2e8f0;
            min-height: 100vh;
            padding: 16px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding-top: 20px;
        }
        .card {
            background: #2a3142;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
        }
        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #3a4255;
        }
        .setting-row:last-child {
            border-bottom: none;
        }
        .setting-info {
            flex: 1;
            margin-right: 20px;
        }
        .setting-label {
            font-size: 16px;
            font-weight: 500;
            color: #e2e8f0;
            margin-bottom: 4px;
        }
        .setting-desc {
            font-size: 13px;
            color: #8892a6;
        }
        .toggle-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .toggle-btn.enabled {
            background: #f44336;
            color: white;
        }
        .toggle-btn.enabled:hover {
            background: #e53935;
        }
        .toggle-btn.disabled {
            background: #4caf50;
            color: white;
        }
        .toggle-btn.disabled:hover {
            background: #43a047;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #3a4255;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #8892a6;
            font-size: 14px;
        }
        .info-value {
            color: #e2e8f0;
            font-weight: 500;
        }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .message.success {
            background: #1b5e20;
            color: #a5d6a7;
            border-left: 4px solid #4caf50;
        }
        .message.error {
            background: #b71c1c;
            color: #ef9a9a;
            border-left: 4px solid #f44336;
        }
        .links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 16px;
        }
        .link {
            color: #64b5f6;
            text-decoration: none;
            font-size: 14px;
        }
        .link:hover {
            text-decoration: underline;
        }
        .warning {
            background: #1e2433;
            border-left: 4px solid #ff9800;
            padding: 12px 16px;
            margin-top: 16px;
            border-radius: 0 8px 8px 0;
            font-size: 13px;
            color: #ffb74d;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.on {
            background: #1b5e20;
            color: #a5d6a7;
        }
        .status-badge.off {
            background: #b71c1c;
            color: #ef9a9a;
        }

        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #2a3142;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 360px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        .modal-desc {
            font-size: 14px;
            color: #8892a6;
            margin-bottom: 20px;
        }
        .password-wrapper {
            position: relative;
            margin-bottom: 16px;
        }
        .modal-input {
            width: 100%;
            padding: 12px 16px;
            padding-right: 48px;
            border: 1px solid #3a4255;
            border-radius: 8px;
            background: #1a1f2e;
            color: #e2e8f0;
            font-size: 16px;
        }
        .modal-input:focus {
            outline: none;
            border-color: #64b5f6;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #8892a6;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover {
            color: #e2e8f0;
        }
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
        }
        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .modal-btn.cancel {
            background: #3a4255;
            color: #e2e8f0;
        }
        .modal-btn.confirm.enable {
            background: #4caf50;
            color: white;
        }
        .modal-btn.confirm.disable {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header">
                <span class="title">Settings</span>
                <span class="status-badge <?= $autobuyerEnabled ? 'on' : 'off' ?>">
                    Auto-buyer: <?= $autobuyerEnabled ? 'ON' : 'OFF' ?>
                </span>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <div class="setting-label">Automatic Permit Buying</div>
                    <div class="setting-desc">When enabled, permits are purchased automatically when the current one expires.</div>
                </div>
                <button type="button" class="toggle-btn <?= $autobuyerEnabled ? 'enabled' : 'disabled' ?>" onclick="showModal()">
                    <?= $autobuyerEnabled ? 'Disable' : 'Enable' ?>
                </button>
            </div>

            <?php if (!$autobuyerEnabled): ?>
                <div class="warning">
                    Auto-buyer is disabled. Permits will NOT be purchased automatically until re-enabled.
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="header">
                <span class="title">Info</span>
            </div>

            <?php if ($expectedPrice): ?>
            <div class="info-row">
                <span class="info-label">Expected Weekly Price</span>
                <span class="info-value">$<?= number_format($expectedPrice, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="links">
            <a href="/parking/" class="link">View Current Permit</a>
            <a href="/parking/history/" class="link">View History</a>
        </div>
    </div>

    <!-- Password Modal -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal-title"><?= $autobuyerEnabled ? 'Disable' : 'Enable' ?> Auto-buyer</div>
            <div class="modal-desc">Enter password to confirm this change.</div>
            <form method="POST" id="toggleForm">
                <input type="hidden" name="action" value="toggle_autobuyer">
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" class="modal-input" placeholder="Password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn cancel" onclick="hideModal()">Cancel</button>
                    <button type="submit" class="modal-btn confirm <?= $autobuyerEnabled ? 'disable' : 'enable' ?>">
                        <?= $autobuyerEnabled ? 'Disable' : 'Enable' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showModal() {
            document.getElementById('modalOverlay').classList.add('active');
            document.getElementById('passwordInput').focus();
        }

        function hideModal() {
            document.getElementById('modalOverlay').classList.remove('active');
            // Reset password visibility when closing
            const input = document.getElementById('passwordInput');
            input.type = 'password';
            updateEyeIcon(false);
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('passwordInput');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            updateEyeIcon(isPassword);
        }

        function updateEyeIcon(visible) {
            const icon = document.getElementById('eyeIcon');
            if (visible) {
                // Eye with slash (hidden)
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                // Normal eye (showing)
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

        // Close modal on overlay click
        document.getElementById('modalOverlay').addEventListener('click', function(e) {
            if (e.target === this) hideModal();
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideModal();
        });

        console.log('%c Settings Page ', 'background: #2a3142; color: #64b5f6; font-size: 14px; padding: 4px 8px; border-radius: 4px;');
    </script>
</body>
</html>
