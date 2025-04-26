<?php
header('Content-Type: application/json');
include '../../db.php'; // Database connection

function preprocessData($data, $db, $user_id) {
    $time_diff = isset($data['time_diff']) ? floatval($data['time_diff']) : 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM votes WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $votes_per_user = $result->fetch_row()[0];
    $stmt->close();

    $voter_id_numeric = preg_replace('/[^0-9]/', '', $data['voter_id']);
    $voter_id = floatval($voter_id_numeric) / 1000000;

    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, v1.vote_timestamp, v2.vote_timestamp)) AS avg_time
         FROM votes v1
         JOIN votes v2 ON v1.user_id = v2.user_id AND v1.vote_timestamp < v2.vote_timestamp
         WHERE v1.user_id = ?
         AND NOT EXISTS (
             SELECT 1 FROM votes v3
             WHERE v3.user_id = v1.user_id
             AND v3.vote_timestamp > v1.vote_timestamp
             AND v3.vote_timestamp < v2.vote_timestamp
         )"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $avg_time_between_votes = $result->fetch_assoc()['avg_time'] ?: 0;
    $stmt->close();

    $time_diff_seconds = max(1, time() - strtotime($data['vote_timestamp']));
    $vote_frequency = isset($data['vote_frequency']) ? floatval($data['vote_frequency']) : ($votes_per_user / $time_diff_seconds) * 86400;

    $vpn_usage = isset($data['vpn_usage']) ? ($data['vpn_usage'] ? 1 : 0) : 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE user_id = ? AND active = 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $multiple_logins = $result->fetch_row()[0] > 1 ? 1 : 0;
    $stmt->close();

    return [
        $time_diff,
        $votes_per_user,
        $voter_id,
        $avg_time_between_votes,
        $vote_frequency,
        $vpn_usage,
        $multiple_logins
    ];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['user_id']) || !isset($input['voter_id']) || !isset($input['vote_timestamp'])) {
        throw new Exception('Invalid input data');
    }

    $user_id = (int)$input['user_id'];
    $vote_id = $input['vote_id'] ?? null;
    $election_id = $input['election_id'] ?? null;

    // Validate vote_id and election_id if provided
    if ($vote_id !== null) {
        $stmt = $db->prepare("SELECT 1 FROM votes WHERE vote_id = ?");
        $stmt->bind_param('i', $vote_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Invalid vote_id');
        }
        $stmt->close();
    }

    if ($election_id !== null) {
        $stmt = $db->prepare("SELECT 1 FROM elections WHERE id = ?");
        $stmt->bind_param('i', $election_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('Invalid election_id');
        }
        $stmt->close();
    }

    $features = preprocessData($input, $db, $user_id);

    $model_path = './neuralnet/fraud_model.keras';
    if (!file_exists($model_path)) {
        throw new Exception('Model file not found');
    }

    $command = escapeshellcmd("python3 ./predict_fraud.py " . escapeshellarg(json_encode($features)));
    $output = shell_exec($command . ' 2>&1');
    $prediction = json_decode($output, true);

    if ($prediction === null || isset($prediction['error'])) {
        throw new Exception('Prediction failed: ' . ($prediction['error'] ?? $output));
    }

    $is_fraud = $prediction['label'] == 1;
    $confidence = $prediction['confidence'];

    $action = $is_fraud ? 'block' : 'allow';
    $details = $is_fraud ? "Fraud detected with confidence $confidence. Features: " . json_encode($features) : "No fraud detected.";
    if ($is_fraud && $confidence < 0.7) {
        $action = 'flag';
        $details = "Potential fraud flagged with confidence $confidence. Features: " . json_encode($features);
    }

    $stmt = $db->prepare(
        "INSERT INTO frauddetectionlogs (user_id, vote_id, election_id, is_fraudulent, confidence, action, details, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('iiisidss', $user_id, $vote_id, $election_id, $is_fraud, $confidence, $action, $details);
    $stmt->execute();
    $stmt->close();

    if ($action == 'block') {
        $stmt = $db->prepare("UPDATE users SET status = 'blocked' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("UPDATE sessions SET active = 0 WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action == 'flag') {
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, title, content, type, sent_at, created_at) 
             VALUES (?, 'Potential Fraud Detected', 'Your voting activity has been flagged for review.', 'fraud_alert', NOW(), NOW())"
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'is_fraud' => $is_fraud,
        'action' => $action,
        'confidence' => $confidence
    ]);
} catch (Exception $e) {
    error_log("Fraud detection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process fraud detection']);
}
?>