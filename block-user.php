<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['user_id'])) {
        throw new Exception("Missing user_id");
    }

    $user_id = $data['user_id'];

    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to block user: " . $stmt->error);
    }
    $stmt->close();

    error_log("User $user_id blocked successfully");
    $conn->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("block-user.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>