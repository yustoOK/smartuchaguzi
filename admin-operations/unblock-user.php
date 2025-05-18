<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $fraud_log_id = isset($data['fraud_log_id']) ? (int)$data['fraud_log_id'] : 0;

    if (!$user_id || !$fraud_log_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or fraud log ID']);
        exit;
    }

    // Update user status
    $stmt = $conn->prepare("UPDATE users SET active = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Log the action in audit logs
    $stmt = $conn->prepare("INSERT INTO auditlogs (user_id, action, details, ip_address, timestamp) 
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], 'Unblock User', "Unblocked user ID $user_id", $_SERVER['REMOTE_ADDR']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>