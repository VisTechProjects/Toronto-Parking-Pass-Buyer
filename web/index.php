<?php
$permitFile = '/home/admin/Toronto-Parking-Pass-Buyer/permit.json';
$historyFile = '/home/admin/Toronto-Parking-Pass-Buyer/permits_history.json';
$carsFile = '/home/admin/Toronto-Parking-Pass-Buyer/config/info_cars.json';

// Load current permit to compare
$currentPermit = null;
if (file_exists($permitFile)) {
    $currentPermit = json_decode(file_get_contents($permitFile), true);
}

// Check if specific permit requested
$requestedPermit = isset($_GET['permit']) ? $_GET['permit'] : null;
$permit = null;
$isHistorical = false;

if ($requestedPermit && file_exists($historyFile)) {
    // Search history for specific permit
    $history = json_decode(file_get_contents($historyFile), true) ?: [];
    foreach ($history as $p) {
        if (isset($p['permitNumber']) && $p['permitNumber'] === $requestedPermit) {
            $permit = $p;
            // Only mark as historical if it's NOT the current permit
            if ($currentPermit && $currentPermit['permitNumber'] !== $requestedPermit) {
                $isHistorical = true;
            }
            break;
        }
    }
}

// Fall back to current permit if not found or not requested
if (!$permit && $currentPermit) {
    $permit = $currentPermit;
}

// Load car nicknames
$cars = [];
if (file_exists($carsFile)) {
    $cars = json_decode(file_get_contents($carsFile), true) ?: [];
}

// Find nickname for plate
$nickname = null;
if ($permit && isset($permit['plateNumber'])) {
    foreach ($cars as $car) {
        if (strtoupper($car['plate']) === strtoupper($permit['plateNumber'])) {
            $nickname = $car['name'];
            break;
        }
    }
}

// Parse dates and format with AM/PM
function formatDateTime($dateStr) {
    if (preg_match('/^(.+):\s*(\d{1,2}):(\d{2})$/', $dateStr, $m)) {
        $hour = (int)$m[2];
        $min = $m[3];
        $ampm = $hour < 12 ? 'AM' : 'PM';
        if ($hour == 0) $hour = 12;
        elseif ($hour > 12) $hour -= 12;
        return trim($m[1]) . ' ' . $hour . ':' . $min . ' ' . $ampm;
    }
    return $dateStr;
}

$daysRemaining = null;
$isExpired = false;
$expiresText = '';

if ($permit && isset($permit['validTo'])) {
    $dateStr = preg_replace('/:\s*\d{1,2}:\d{2}$/', '', $permit['validTo']);
    $validTo = DateTime::createFromFormat('M j, Y', trim($dateStr));

    if ($validTo) {
        $validTo->setTime(23, 59, 59);
        $now = new DateTime();
        $today = new DateTime('today');
        $expiryDay = new DateTime($validTo->format('Y-m-d'));

        $diff = $today->diff($expiryDay);
        $daysUntilExpiry = $diff->invert ? -1 : $diff->days;

        if ($now > $validTo) {
            $isExpired = true;
            $daysRemaining = 0;
            $expiresText = 'Expired';
        } elseif ($daysUntilExpiry == 0) {
            $daysRemaining = 0;
            $expiresText = 'Expires Today';
        } elseif ($daysUntilExpiry == 1) {
            $daysRemaining = 1;
            $expiresText = 'Expires Tomorrow';
        } else {
            $daysRemaining = $daysUntilExpiry;
            $expiresText = $daysRemaining . ' days remaining';
        }
    }
}

$statusColor = '#4caf50';
$statusText = 'Valid';
if ($isExpired) {
    $statusColor = '#f44336';
    $statusText = 'Expired';
} elseif ($daysRemaining !== null && $daysRemaining <= 1) {
    $statusColor = '#ff9800';
    $statusText = 'Expiring Soon';
}

// Historical badge overrides
if ($isHistorical) {
    $statusColor = '#607d8b';
    $statusText = 'Historical';
}

// Get amount paid
$amountPaid = $permit['amountPaid'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Parking Permit Status</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1f2e;
            color: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }
        .card {
            background: #2a3142;
            border-radius: 16px;
            padding: 28px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: <?= $statusColor ?>;
            color: white;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #3a4255;
        }
        .info-row:last-child { border-bottom: none; }
        .label { color: #8892a6; font-size: 14px; }
        .value { font-weight: 500; color: #e2e8f0; text-align: right; }
        .value.price { color: #4caf50; }
        .days-remaining {
            text-align: center;
            margin-top: 20px;
            padding: 16px;
            background: #1e2433;
            border-radius: 12px;
        }
        .days-text {
            font-size: 22px;
            font-weight: 700;
            color: <?= $statusColor ?>;
        }
        .no-permit {
            text-align: center;
            color: #8892a6;
            padding: 40px;
        }
        .links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 14px;
        }
        .link {
            color: #64b5f6;
            text-decoration: none;
            font-size: 14px;
        }
        .link:hover { text-decoration: underline; }

        /* Mobile Responsive - fit without scrolling */
        @media (max-width: 400px) {
            body {
                padding: 10px;
            }
            .card {
                padding: 20px;
            }
            .header {
                margin-bottom: 16px;
            }
            .title {
                font-size: 20px;
            }
            .status {
                padding: 5px 10px;
                font-size: 11px;
            }
            .info-row {
                padding: 8px 0;
            }
            .label {
                font-size: 13px;
            }
            .value {
                font-size: 13px;
            }
            .days-remaining {
                margin-top: 16px;
                padding: 14px;
            }
            .days-text {
                font-size: 20px;
            }
            .links {
                margin-top: 12px;
            }
            .link {
                font-size: 13px;
            }
        }

        /* Extra small screens */
        @media (max-height: 600px) {
            .card {
                padding: 16px;
            }
            .header {
                margin-bottom: 12px;
            }
            .info-row {
                padding: 6px 0;
            }
            .days-remaining {
                margin-top: 12px;
                padding: 12px;
            }
            .days-text {
                font-size: 18px;
            }
            .links {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($permit): ?>
            <div class="header">
                <span class="title">Parking Permit</span>
                <span class="status"><?= $statusText ?></span>
            </div>
            <?php if ($nickname): ?>
            <div class="info-row">
                <span class="label">Vehicle</span>
                <span class="value"><?= htmlspecialchars($nickname) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="label">Plate</span>
                <span class="value"><?= htmlspecialchars($permit['plateNumber'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Permit #</span>
                <span class="value"><?= htmlspecialchars($permit['permitNumber'] ?? 'N/A') ?></span>
            </div>
            <?php if ($amountPaid): ?>
            <div class="info-row">
                <span class="label">Cost</span>
                <span class="value price"><?= htmlspecialchars($amountPaid) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="label">Valid From</span>
                <span class="value"><?= htmlspecialchars(formatDateTime($permit['validFrom'] ?? '')) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Valid To</span>
                <span class="value"><?= htmlspecialchars(formatDateTime($permit['validTo'] ?? '')) ?></span>
            </div>
            <div class="days-remaining">
                <div class="days-text"><?= $expiresText ?></div>
            </div>
            <div class="links">
                <?php if ($isHistorical): ?>
                    <a href="/parking/" class="link">View Current</a>
                <?php endif; ?>
                <a href="/parking/history/" class="link">View History</a>
            </div>
        <?php else: ?>
            <div class="no-permit">No permit data found</div>
            <div class="links">
                <a href="/parking/history/" class="link">View History</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        // A special message for those who look
        console.log(`%c
    ╭─────────────────────────────────────────╮
    │                                         │
    │      🖕 CITY OF TORONTO PARKING 🖕      │
    │                                         │
    │   "The only thing more expensive than   │
    │    Toronto parking is Toronto rent"     │
    │                                         │
    │   So I automated the whole thing...     │
    │                                         │
    ╰─────────────────────────────────────────╯

🖕  https://media.giphy.com/media/xndHaRIcvge5y/giphy.gif

If you're already here, check out my other automated bullshit:
🖕 Auto-buyer: https://github.com/VisTechProjects/Toronto-Parking-Pass-Buyer
🖕 E-ink display: https://github.com/VisTechProjects/parking_pass_display
        `, 'color: #4caf50; font-family: monospace;');
    </script>
</body>
</html>
