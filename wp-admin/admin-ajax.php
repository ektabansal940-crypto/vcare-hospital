<?php
/**
 * Local AJAX endpoint for V Care Multispeciality Hospital
 * Handles appointment submissions, contact inquiries, and plugin heartbeats cleanly.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Simple log directory
$log_dir = __DIR__ . '/../data';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

if ($action === 'wpr_form_submission' || isset($_POST['form_fields'])) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'Appointment',
        'data' => $_POST
    ];
    @file_put_contents($log_dir . '/appointments.log', json_encode($entry) . "\n", FILE_APPEND);
    
    // Optional PHP mail() attempt if SMTP configured in php.ini
    $to = 'hospitalvcaremultispeciality@gmail.com';
    $subject = 'New Appointment Request - V Care Hospital';
    $message = "New appointment booking received:\n" . print_r($_POST, true);
    $headers = "From: webmaster@vcaremultispecialityhospital.in\r\nReply-To: hospitalvcaremultispeciality@gmail.com\r\n";
    @mail($to, $subject, $message, $headers);
    
    echo json_encode([
        'status' => 'success',
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
    
    // Optional PHP mail() attempt if SMTP configured in php.ini
    $to = 'hospitalvcaremultispeciality@gmail.com';
    $subject = 'New Contact Message - V Care Hospital';
    $message = "New contact message received:\n" . print_r($_POST, true);
    $headers = "From: webmaster@vcaremultispecialityhospital.in\r\nReply-To: hospitalvcaremultispeciality@gmail.com\r\n";
    @mail($to, $subject, $message, $headers);
    
    echo json_encode([
        'status' => 'success',
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
