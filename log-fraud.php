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
    $election_id = $data['election_id'];
    $is_fraudulent = $data['is_fraudulent'];
    $confidence = $data['confidence'];
    $details = $data['details'];
    $action = $data['action'];
    $description = $is_fraudulent ? "Fraud detected with confidence $confidence" : "No fraud detected";

    $stmt = $conn->prepare(
        "INSERT INTO frauddetectionlogs (user_id, election_id, is_fraudulent, confidence, details, description, action, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("iiidsss", $user_id, $election_id, $is_fraudulent, $confidence, $details, $description, $action);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>