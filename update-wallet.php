<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log("No authenticated user for wallet update");
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['wallet_address']) || empty($_POST['wallet_address'])) {
    error_log("No wallet address provided");
    echo json_encode(['success' => false, 'error' => 'No wallet address provided']);
    exit;
}

$wallet_address = strip_tags($_POST['wallet_address']);
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
    error_log("Invalid wallet address format: " . $wallet_address);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address format']);
    exit;
}

$_SESSION['wallet_address'] = $wallet_address;

try {
    $pdo = new PDO("mysql:host=localhost;dbname=smartuchaguzi_db", "root", "Leonida1972@@@@");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("UPDATE users SET wallet_address = ? WHERE user_id = ?");
    $stmt->execute([$wallet_address, $_SESSION['user_id']]);
    error_log("Wallet address updated in database for user_id: {$_SESSION['user_id']}");
} catch (PDOException $e) {
    error_log("Database update failed: " . $e->getMessage());
    
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
error_log("Session wallet address updated to: $wallet_address for user_id: {$_SESSION['user_id']} from IP: $ip_address at " . date('Y-m-d H:i:s'));

echo json_encode(['success' => true, 'message' => 'Wallet address updated']);
?>