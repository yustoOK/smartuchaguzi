<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: " . print_r($_POST, true));
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Update wallet address in session
if (isset($_POST['wallet_address'])) {
    $_SESSION['wallet_address'] = $_POST['wallet_address'];
    error_log("Session wallet address updated to: " . $_POST['wallet_address'] . " for user_id: " . ($_SESSION['user_id'] ?? 'unset'));
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No wallet address provided']);
}
?>