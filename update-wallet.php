<?php
session_start();
header('Content-Type: application/json');

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if (!isset($_POST['wallet_address']) || empty($_POST['wallet_address'])) {
    echo json_encode(['success' => false, 'error' => 'No wallet address provided']);
    exit;
}

$wallet_address = filter_var($_POST['wallet_address'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$wallet_address = trim($wallet_address);
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address format']);
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Updating session
    $_SESSION['wallet_address'] = $wallet_address;

    // Update wallet address in users table
    $stmt = $pdo->prepare("UPDATE users SET wallet_address = ? WHERE user_id = ?");
    $stmt->execute([$wallet_address, $_SESSION['user_id']]);

    // Log the action
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $action = "Wallet address updated to: $wallet_address";
    $stmt = $pdo->prepare("INSERT INTO auditlogs (user_id, action, ip_address, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $action, $ip_address]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Database error in update-wallet: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in update-wallet: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>