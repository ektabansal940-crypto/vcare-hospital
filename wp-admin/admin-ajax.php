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

if ($action === 'wpr_form_submission' || isset($_POST['form_fields'])) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'Appointment',
        'data' => $_POST
    ];
    @file_put_contents($log_dir . '/appointments.log', json_encode($entry) . "\n", FILE_APPEND);
    
    $to = defined('HOSPITAL_EMAIL') ? HOSPITAL_EMAIL : 'hospitalvcaremultispeciality@gmail.com';
    $subject = 'New Appointment Request - V Care Hospital';
    $date = $_POST['date'] ?? $_POST['form_fields']['f7bddbf'] ?? 'N/A';
    $time = $_POST['time'] ?? $_POST['form_fields']['name'] ?? 'N/A';
    $patient = $_POST['message'] ?? $_POST['form_fields']['message'] ?? 'N/A';
    
    $email_body = "New Appointment Request Received!\n\n"
        . "Date: $date\n"
        . "Time: $time\n"
        . "Patient Details:\n$patient\n\n"
        . "Received: " . date('Y-m-d H:i:s');
        
    $smtp_sent = send_gmail_smtp($to, $subject, $email_body);
    
    echo json_encode([
        'status' => 'success',
        'smtp_sent' => $smtp_sent,
        'message' => 'Appointment request received successfully.',
        'data' => $entry
    ]);
    exit;
}

if (isset($_POST['frm_action']) || isset($_POST['item_meta'])) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'Contact Inquiry',
        'data' => $_POST
    ];
    @file_put_contents($log_dir . '/contacts.log', json_encode($entry) . "\n", FILE_APPEND);
    
    $to = defined('HOSPITAL_EMAIL') ? HOSPITAL_EMAIL : 'hospitalvcaremultispeciality@gmail.com';
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
    
    echo json_encode([
        'status' => 'success',
        'smtp_sent' => $smtp_sent,
        'message' => 'Contact message received successfully.',
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

