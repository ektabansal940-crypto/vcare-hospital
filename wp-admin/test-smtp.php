<?php
/**
 * V Care Multispeciality Hospital - SMTP Diagnostic & Health Check Tool
 * Accessible locally at: http://localhost/V%20Care%20Multispeciality%20Hospital/wp-admin/test-smtp.php
 */

header('Content-Type: text/html; charset=UTF-8');

$config_file = __DIR__ . '/smtp-config.php';
$has_config = file_exists($config_file);
if ($has_config) {
    require_once $config_file;
}

$results = [];
$can_proceed = true;

// Step 1: Configuration check
if (!$has_config) {
    $results[] = ['step' => 'Configuration File', 'status' => 'FAIL', 'message' => 'wp-admin/smtp-config.php not found.'];
    $can_proceed = false;
} else {
    $results[] = ['step' => 'Configuration File', 'status' => 'PASS', 'message' => 'Found smtp-config.php'];
}

// Step 2: Constant validation
if ($can_proceed) {
    $missing = [];
    if (!defined('SMTP_HOST')) $missing[] = 'SMTP_HOST';
    if (!defined('SMTP_PORT')) $missing[] = 'SMTP_PORT';
    if (!defined('SMTP_USER')) $missing[] = 'SMTP_USER';
    if (!defined('SMTP_PASS')) $missing[] = 'SMTP_PASS';
    
    if (!empty($missing)) {
        $results[] = ['step' => 'SMTP Constants', 'status' => 'FAIL', 'message' => 'Missing definitions: ' . implode(', ', $missing)];
        $can_proceed = false;
    } else {
        $results[] = ['step' => 'SMTP Constants', 'status' => 'PASS', 'message' => 'Host: ' . SMTP_HOST . ' | Port: ' . SMTP_PORT . ' | User: ' . SMTP_USER];
    }
}

// Step 3: OpenSSL extension check
$has_openssl = extension_loaded('openssl');
$results[] = [
    'step' => 'PHP OpenSSL Extension', 
    'status' => $has_openssl ? 'PASS' : 'FAIL', 
    'message' => $has_openssl ? 'OpenSSL is enabled (' . OPENSSL_VERSION_TEXT . ')' : 'OpenSSL is disabled in php.ini'
];
if (!$has_openssl) $can_proceed = false;

// Step 4: Perform Live SMTP Dispatch if requested or automatically
$smtp_test_status = null;
$test_sent = false;
$transcript = [];

if ($can_proceed && (isset($_POST['send_test']) || isset($_GET['auto']))) {
    $to = defined('HOSPITAL_EMAIL') ? HOSPITAL_EMAIL : SMTP_USER;
    $subject = 'V Care Hospital - Interactive SMTP Verification (' . date('H:i:s') . ')';
    $body = "Congratulations!\n\nYour SMTP email system is working perfectly for V Care Multispeciality Hospital.\n\n"
          . "Time: " . date('Y-m-d H:i:s') . "\n"
          . "Host: " . SMTP_HOST . ":" . SMTP_PORT . "\n"
          . "Sender: " . SMTP_USER . "\n"
          . "Recipient: $to\n\n"
          . "Appointment and Contact form notifications will now deliver directly to your inbox.";
          
    $errno = 0;
    $errstr = '';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $transcript[] = "Connecting to tcp://" . SMTP_HOST . ":" . SMTP_PORT . "...";
    $socket = @stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
    
    if (!$socket) {
        $transcript[] = "ERROR: Connection failed: ($errno) $errstr";
        $results[] = ['step' => 'Socket Connection', 'status' => 'FAIL', 'message' => "Cannot connect to " . SMTP_HOST . ": $errstr"];
    } else {
        $banner = fgets($socket, 515);
        $transcript[] = "<- " . trim($banner);
        
        fputs($socket, "EHLO localhost\r\n");
        $transcript[] = "-> EHLO localhost";
        while ($line = fgets($socket, 515)) {
            $transcript[] = "<- " . trim($line);
            if (substr($line, 3, 1) == " ") break;
        }
        
        fputs($socket, "STARTTLS\r\n");
        $transcript[] = "-> STARTTLS";
        $tls = fgets($socket, 515);
        $transcript[] = "<- " . trim($tls);
        
        $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if (!$crypto) {
            $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        
        if (!$crypto) {
            $transcript[] = "ERROR: TLS negotiation failed.";
            $results[] = ['step' => 'TLS Handshake', 'status' => 'FAIL', 'message' => 'TLS encryption handshake failed'];
            fclose($socket);
        } else {
            $transcript[] = "TLS handshake established successfully.";
            
            fputs($socket, "EHLO localhost\r\n");
            $transcript[] = "-> EHLO localhost";
            while ($line = fgets($socket, 515)) {
                $transcript[] = "<- " . trim($line);
                if (substr($line, 3, 1) == " ") break;
            }
            
            fputs($socket, "AUTH LOGIN\r\n");
            $transcript[] = "-> AUTH LOGIN";
            $transcript[] = "<- " . trim(fgets($socket, 515));
            
            fputs($socket, base64_encode(SMTP_USER) . "\r\n");
            $transcript[] = "-> [BASE64_USER]";
            $transcript[] = "<- " . trim(fgets($socket, 515));
            
            fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
            $transcript[] = "-> [BASE64_PASS]";
            $auth_resp = fgets($socket, 515);
            $transcript[] = "<- " . trim($auth_resp);
            
            if (strpos($auth_resp, '235') === false) {
                $results[] = ['step' => 'Authentication', 'status' => 'FAIL', 'message' => 'Credentials rejected: ' . trim($auth_resp)];
                fclose($socket);
            } else {
                $results[] = ['step' => 'Authentication', 'status' => 'PASS', 'message' => 'Gmail Accepted Credentials (235 OK)'];
                
                fputs($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
                $transcript[] = "-> MAIL FROM: <" . SMTP_USER . ">";
                $transcript[] = "<- " . trim(fgets($socket, 515));
                
                fputs($socket, "RCPT TO: <$to>\r\n");
                $transcript[] = "-> RCPT TO: <$to>";
                $transcript[] = "<- " . trim(fgets($socket, 515));
                
                fputs($socket, "DATA\r\n");
                $transcript[] = "-> DATA";
                $transcript[] = "<- " . trim(fgets($socket, 515));
                
                $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'V Care Hospital';
                $msg = "From: $from_name <" . SMTP_USER . ">\r\n"
                     . "To: <$to>\r\n"
                     . "Subject: $subject\r\n"
                     . "MIME-Version: 1.0\r\n"
                     . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                     . $body . "\r\n.\r\n";
                     
                fputs($socket, $msg);
                $send_resp = fgets($socket, 515);
                $transcript[] = "<- " . trim($send_resp);
                
                fputs($socket, "QUIT\r\n");
                fclose($socket);
                
                if (strpos($send_resp, '250') !== false) {
                    $test_sent = true;
                    $results[] = ['step' => 'Email Dispatch', 'status' => 'PASS', 'message' => "Delivered to $to: " . trim($send_resp)];
                } else {
                    $results[] = ['step' => 'Email Dispatch', 'status' => 'FAIL', 'message' => 'Delivery failed: ' . trim($send_resp)];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>V Care Hospital - SMTP Diagnostics</title>
    <style>
        :root {
            --primary: #A886CD;
            --primary-dark: #8C62B6;
            --success: #10b981;
            --danger: #ef4444;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
            --muted: #94a3b8;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 30px 20px;
            display: flex;
            justify-content: center;
        }
        .container {
            max-width: 760px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 24px;
            color: #fff;
        }
        .header p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .step-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 14px;
        }
        .step-row:last-child { border-bottom: none; }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .badge-pass { background: rgba(16,185,129,0.2); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
        .badge-fail { background: rgba(239,68,68,0.2); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
        pre.transcript {
            background: #090d16;
            padding: 16px;
            border-radius: 8px;
            font-family: "Consolas", monospace;
            font-size: 12px;
            color: #38bdf8;
            overflow-x: auto;
            max-height: 280px;
            line-height: 1.5;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid var(--success);
            padding: 16px;
            border-radius: 8px;
            color: #d1fae5;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>V Care Multispeciality Hospital</h1>
        <p>SMTP Email Dispatch & Health Verification Console</p>
    </div>

    <?php if ($test_sent): ?>
    <div class="alert-success">
        <strong>✓ SMTP Verification Successful!</strong><br>
        A test message has been delivered directly to <strong><?= htmlspecialchars($to) ?></strong> via Gmail SMTP.
    </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:18px;margin-top:0;margin-bottom:16px;">System Readiness Checks</h2>
        <?php foreach ($results as $r): ?>
        <div class="step-row">
            <div>
                <strong><?= htmlspecialchars($r['step']) ?></strong>
                <div style="color:var(--muted);font-size:12px;margin-top:2px;"><?= htmlspecialchars($r['message']) ?></div>
            </div>
            <div>
                <span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:24px;display:flex;gap:12px;align-items:center;">
            <form method="POST" style="margin:0;">
                <button type="submit" name="send_test" value="1" class="btn">
                    <span>⚡ Send Test Email Now</span>
                </button>
            </form>
            <a href="../contact/" class="btn" style="background:transparent;border:1px solid rgba(255,255,255,0.2);">View Contact Page</a>
            <a href="../appointment/" class="btn" style="background:transparent;border:1px solid rgba(255,255,255,0.2);">View Appointment Page</a>
        </div>
    </div>

    <?php if (!empty($transcript)): ?>
    <div class="card">
        <h2 style="font-size:16px;margin-top:0;margin-bottom:12px;color:var(--muted);">Live SMTP Handshake Log</h2>
        <pre class="transcript"><?= htmlspecialchars(implode("\n", $transcript)) ?></pre>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

