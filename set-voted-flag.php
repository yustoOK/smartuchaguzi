<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    $user_id = $_SESSION['user_id'];

    // Update the has_voted flag for the user
    $stmt = $conn->prepare("UPDATE users SET has_voted = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to set voted flag: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // Return success response
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Log the error and return failure response
    error_log("set-voted-flag.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>