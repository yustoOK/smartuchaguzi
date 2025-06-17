<?php
session_start();
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $user_id = $_POST['user_id'] ?? null;
    $wallet_address = $_POST['wallet_address'] ?? '';

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT wallet_address FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $db_wallet = $user['wallet_address'] ?? '';
    $is_valid = ($wallet_address === $db_wallet && !empty($db_wallet));

    if (!$is_valid) {
        error_log("Wallet mismatch for user_id: $user_id, session: $wallet_address, db: $db_wallet");
        echo json_encode(['success' => false, 'message' => 'Wallet address mismatch']);
    } else {
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    error_log("Wallet validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error during validation']);
}
$conn->close();
?>