<?php
/**
 * Local AJAX endpoint for V Care Multispeciality Hospital
 * Handles appointment submissions, contact inquiries, and direct Gmail SMTP email dispatch.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if (file_exists(__DIR__ . '/smtp-config.php')) {
    require_once __DIR__ . '/smtp-config.php';
}

function send_gmail_smtp($to, $subject, $body, $reply_to = '') {
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        return false;
    }
    
    $socket = @stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 12);
    if (!$socket) return false;
    
    fgets($socket, 515);
    fputs($socket, "EHLO localhost\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) == " ") break;
    }
    
    fputs($socket, "STARTTLS\r\n");
    fgets($socket, 515);
    if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
        fclose($socket);
        return false;
    }
    
    fputs($socket, "EHLO localhost\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) == " ") break;
    }
    
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode(SMTP_USER) . "\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
    $auth = fgets($socket, 515);
    if (strpos($auth, '235') === false) {
        fclose($socket);
        return false;
    }
    
    fputs($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
    fgets($socket, 515);
    fputs($socket, "RCPT TO: <$to>\r\n");
    fgets($socket, 515);
    
    fputs($socket, "DATA\r\n");
    fgets($socket, 515);
    
    $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'V Care Multispeciality Hospital';
    $headers  = "From: $from_name <" . SMTP_USER . ">\r\n";
    $headers .= "To: <$to>\r\n";
    if ($reply_to) {
        $headers .= "Reply-To: <$reply_to>\r\n";
    }
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $message = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($socket, $message);
    $sent = fgets($socket, 515);
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return (strpos($sent, '250') !== false);
}

// Simple log directory
$log_dir = __DIR__ . '/../data';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. Appointment Form (Royal Elementor Addons or custom)
if ($action === 'wpr_form_builder_email' || $action === 'wpr_form_submission' || isset($_POST['form_fields']) || isset($_POST['form_content'])) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'Appointment',
        'data' => $_POST
    ];
    @file_put_contents($log_dir . '/appointments.log', json_encode($entry) . "\n", FILE_APPEND);
    
    $to = defined('HOSPITAL_EMAIL') ? HOSPITAL_EMAIL : 'rb887087@gmail.com';
    $subject = 'New Appointment Request - V Care Hospital';
    $date = $_POST['date'] ?? $_POST['form_fields']['f7bddbf'] ?? '';
    $time = $_POST['time'] ?? $_POST['form_fields']['name'] ?? '';
    $patient = $_POST['message'] ?? $_POST['form_fields']['message'] ?? '';

    // If sent via Royal Elementor form_content
    if (isset($_POST['form_content']) && is_array($_POST['form_content'])) {
        foreach ($_POST['form_content'] as $k => $f) {
            if (!is_array($f)) continue;
            $ftype = $f[0] ?? '';
            $fval = $f[1] ?? '';
            if ($ftype === 'date' || strpos($k, 'f7bddbf') !== false) {
                if (!$date) $date = $fval;
            } elseif ($ftype === 'time' || strpos($k, 'name') !== false) {
                if (!$time) $time = $fval;
            } elseif ($ftype === 'textarea' || strpos($k, 'message') !== false) {
                if (!$patient) $patient = $fval;
            }
        }
    }
    
    if (!$date) $date = 'Not specified';
    if (!$time) $time = 'Not specified';
    if (!$patient) $patient = 'Not specified';
    
    $email_body = "New Appointment Request Received!\n\n"
        . "Date: $date\n"
        . "Time: $time\n"
        . "Patient Details:\n$patient\n\n"
        . "Received: " . date('Y-m-d H:i:s');
        
    $smtp_sent = send_gmail_smtp($to, $subject, $email_body);
    
    $msg = 'Thank you! Your appointment request has been submitted successfully to V Care Multispeciality Hospital. Our reception desk will contact you shortly.';
    
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'smtp_sent' => $smtp_sent,
        'message' => $msg,
        'data' => [
            'action' => 'wpr_form_builder_email',
            'status' => 'success',
            'message' => $msg
        ]
    ]);
    exit;
}

// 2. Contact Form (Formidable Forms)
if ($action === 'frm_entries_create' || isset($_POST['frm_action']) || isset($_POST['item_meta'])) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'Contact Inquiry',
        'data' => $_POST
    ];
    @file_put_contents($log_dir . '/contacts.log', json_encode($entry) . "\n", FILE_APPEND);
    
    $to = defined('HOSPITAL_EMAIL') ? HOSPITAL_EMAIL : 'rb887087@gmail.com';
    $firstName = $_POST['item_meta'][1] ?? '';
    $lastName = $_POST['item_meta'][2] ?? '';
    $senderName = trim("$firstName $lastName") ?: 'Website Visitor';
    $senderEmail = $_POST['item_meta'][3] ?? '';
    $subject = !empty($_POST['item_meta'][4]) ? $_POST['item_meta'][4] : 'New Contact Inquiry - V Care Hospital';
    $message = $_POST['item_meta'][5] ?? '';
    
    $email_body = "New Contact Inquiry Received!\n\n"
        . "From: $senderName\n"
        . "Email: $senderEmail\n"
        . "Subject: $subject\n\n"
        . "Message:\n$message\n\n"
        . "Received: " . date('Y-m-d H:i:s');
        
    $smtp_sent = send_gmail_smtp($to, "Contact Form: $subject", $email_body, $senderEmail);
    
    $success_html = '<div class="frm_message frm_success_style" role="status"><p><strong>Thank you!</strong> Your message has been sent successfully to V Care Multispeciality Hospital desk. We will respond promptly.</p></div>';
    
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'smtp_sent' => $smtp_sent,
        'message' => 'Contact message received successfully.',
        'content' => $success_html,
        'errors' => new stdClass(),
        'data' => $entry
    ]);
    exit;
}

// Default response
echo json_encode([
    'status' => 200,
    'success' => true,
    'message' => 'V Care Hospital Local API active.'
]);
exit;

