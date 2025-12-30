<?php
$settingsFile = '/home/admin/Toronto-Parking-Pass-Buyer/config/settings.json';
$envFile = '/home/admin/Toronto-Parking-Pass-Buyer/.env';

// Load settings
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Load auth credentials from .env
$authUser = null;
$authPass = null;
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/^SETTINGS_USER=(.+)$/m', $envContent, $m)) {
        $authUser = trim($m[1]);
    }
    if (preg_match('/^SETTINGS_PASS=(.+)$/m', $envContent, $m)) {
        $authPass = trim($m[1]);
    }
}

// Check if auth is configured
$authConfigured = $authUser && $authPass;

// Handle form submission (requires auth)
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authenticated = false;

    if ($authConfigured) {
        // Check HTTP Basic Auth
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if ($_SERVER['PHP_AUTH_USER'] === $authUser && $_SERVER['PHP_AUTH_PW'] === $authPass) {
                $authenticated = true;
            }
        }

        if (!$authenticated) {
            header('WWW-Authenticate: Basic realm="Parking Settings"');
            header('HTTP/1.0 401 Unauthorized');
            $message = 'Authentication required to change settings.';
            $messageType = 'error';
        }
    } else {
        // No auth configured - allow changes (for initial setup)
        $authenticated = true;
    }

    if ($authenticated && isset($_POST['action'])) {
        if ($_POST['action'] === 'toggle_autobuyer') {
            $currentEnabled = $settings['autobuyer']['enabled'] ?? true;
            $settings['autobuyer'] = $settings['autobuyer'] ?? [];
            $settings['autobuyer']['enabled'] = !$currentEnabled;

            if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
                $message = 'Auto-buyer ' . ($settings['autobuyer']['enabled'] ? 'enabled' : 'disabled') . '.';
                $messageType = 'success';
            } else {
                $message = 'Failed to save settings.';
                $messageType = 'error';
            }
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
            background: #4caf50;
            color: white;
        }
        .toggle-btn.enabled:hover {
            background: #43a047;
        }
        .toggle-btn.disabled {
            background: #f44336;
            color: white;
        }
        .toggle-btn.disabled:hover {
            background: #e53935;
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
                <form method="POST" style="margin-left: 16px;">
                    <input type="hidden" name="action" value="toggle_autobuyer">
                    <button type="submit" class="toggle-btn <?= $autobuyerEnabled ? 'enabled' : 'disabled' ?>">
                        <?= $autobuyerEnabled ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
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

            <div class="info-row">
                <span class="info-label">Auth Protection</span>
                <span class="info-value"><?= $authConfigured ? 'Enabled' : 'Not configured' ?></span>
            </div>
        </div>

        <div class="links">
            <a href="/parking/" class="link">View Current Permit</a>
            <a href="/parking/history/" class="link">View History</a>
        </div>
    </div>

    <script>
        console.log('%c Settings Page ', 'background: #2a3142; color: #64b5f6; font-size: 14px; padding: 4px 8px; border-radius: 4px;');
    </script>
</body>
</html>
