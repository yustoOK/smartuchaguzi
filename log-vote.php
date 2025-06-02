<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    $user_id = $input['user_id'] ?? null;
    $voter_id = $input['voter_id'] ?? null;
    $ip_address = $input['ip_address'] ?? null;
    $election_id = $input['election_id'] ?? null;
    $is_fraudulent = $input['is_fraudulent'] ?? 0;
    $confidence = $input['confidence'] ?? 0.0;
    $details = $input['details'] ?? '{}';
    $action = $input['action'] ?? 'none';

    if (!$user_id || !$election_id) {
        throw new Exception("Missing required fields: user_id or election_id");
    }

    $details_array = json_decode($details, true);
    if (!$details_array) {
        throw new Exception("Invalid details JSON");
    }

    $stmt = $conn->prepare(
        "INSERT INTO frauddetectionlogs (
            user_id, election_id, is_fraudulent, confidence, details, 
            ip_history, vote_pattern, user_behavior, api_response, description, 
            action, time_diff, votes_per_user, session_duration, geo_location, 
            device_fingerprint, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    $description = $is_fraudulent ? "Fraud detected with confidence $confidence" : "No fraud detected";
    $ip_history = json_encode($details_array['ip_history'] ?? []);
    $vote_pattern = $details_array['vote_pattern'] ?? 0;
    $user_behavior = $details_array['user_behavior'] ?? 0;
    $api_response = json_encode($details_array['api_response'] ?? []);
    $time_diff = $details_array['time_diff'] ?? 0;
    $votes_per_user = $details_array['votes_per_user'] ?? 0;
    $session_duration = $details_array['session_duration'] ?? 0;
    $geo_location = $details_array['geo_location'] ?? 0;
    $device_fingerprint = $details_array['device_fingerprint'] ?? '';

    $stmt->bind_param(
        "iiidssdsssiiiiis",
        $user_id,
        $election_id,
        $is_fraudulent,
        $confidence,
        $details,
        $ip_history,
        $vote_pattern,
        $user_behavior,
        $api_response,
        $description,
        $action,
        $time_diff,
        $votes_per_user,
        $session_duration,
        $geo_location,
        $device_fingerprint
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to log fraud: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("log-fraud.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>