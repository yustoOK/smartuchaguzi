<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'];

    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>