<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    $user_id = $_SESSION['user_id'];

    // Checking if already voted
    $check_stmt = $conn->prepare("SELECT has_voted FROM users WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($result && $result['has_voted']) {
        throw new Exception("User has already voted");
    }

    $stmt = $conn->prepare("UPDATE users SET has_voted = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to set voted flag: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("set-voted-flag.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>