<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['election_id'], $input['hash'], $input['voter'], $input['position_id'], $input['candidate_id'])) {
        throw new Exception("Invalid vote data");
    }

    $election_id = $input['election_id'];
    $hash = $input['hash'];
    $voter = $input['voter']; // Should match users.eth_address
    $position_id = $input['position_id'];
    $candidate_id = $input['candidate_id'];
    $timestamp = $input['timestamp'] ?? time();
    $data = $input['data'] ?? json_encode(['candidate_name' => '', 'position_name' => '']);

    $stmt = $conn->prepare(
        "INSERT INTO blockchainrecords (election_id, hash, data, timestamp, voter, position_id, candidate_id) 
         VALUES (?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?)"
    );
    $stmt->bind_param("issisi", $election_id, $hash, $data, $timestamp, $voter, $position_id, $candidate_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to log vote: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("log-vote.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>