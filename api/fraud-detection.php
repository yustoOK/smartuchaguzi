<?php
header('Content-Type: application/json');
require_once '../vendor/autoload.php';
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Exception\InvalidArgumentException;
use Phpml\Exception\RuntimeException;

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function preprocessData($data, $pdo, $user_id) {
    $time_diff = isset($data['time_diff']) ? floatval($data['time_diff']) : 0;
    $votes_per_user = 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $votes_per_user = $stmt->fetchColumn();

    $voter_id_numeric = preg_replace('/[^0-9]/', '', $data['voter_id']);
    $voter_id = floatval($voter_id_numeric) / 1000000;

    $avg_time_stmt = $pdo->prepare("SELECT AVG(UNIX_TIMESTAMP(vote_timestamp) - UNIX_TIMESTAMP(LAG(vote_timestamp) OVER (PARTITION BY user_id ORDER BY vote_timestamp))) AS avg_time FROM votes WHERE user_id = ?");
    $avg_time_stmt->execute([$user_id]);
    $avg_time_between_votes = $avg_time_stmt->fetchColumn() ?: 0;

    $vote_frequency = isset($data['vote_frequency']) ? floatval($data['vote_frequency']) : ($votes_per_user / (time() - strtotime($data['vote_timestamp']))) * 86400;

    $vpn_usage = isset($data['vpn_usage']) ? ($data['vpn_usage'] ? 1 : 0) : 0;

    $multiple_logins = 0;
    $session_stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE user_id = ? AND active = 1");
    $session_stmt->execute([$user_id]);
    $multiple_logins = $session_stmt->fetchColumn() > 1 ? 1 : 0;

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
    $features = preprocessData($input, $pdo, $user_id);

    $model_path = './neuralnet/fraud_model.keras';
    if (!file_exists($model_path)) {
        throw new Exception('Model file not found');
    }

    $command = escapeshellcmd("python3 ./predict_fraud.py " . escapeshellarg(json_encode($features)));
    $output = shell_exec($command . ' 2>&1');
    $prediction = json_decode($output, true);

    if ($prediction === null) {
        throw new Exception('Prediction failed: ' . $output);
    }

    $is_fraud = $prediction['label'] == 1;
    $confidence = $prediction['confidence'];

    $action = $is_fraud ? 'block' : 'allow';
    $details = $is_fraud ? "Fraud detected with confidence $confidence. Features: " . json_encode($features) : "No fraud detected.";
    if ($is_fraud && $confidence < 0.7) {
        $action = 'flag';
        $details = "Potential fraud flagged with confidence $confidence. Features: " . json_encode($features);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO frauddetectionlogs (user_id, vote_id, election_id, is_fraudulent, confidence, action, details, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $user_id,
        $input['vote_id'] ?? null,
        $input['election_id'] ?? null,
        $is_fraud ? 1 : 0,
        $confidence,
        $action,
        $details
    ]);

    if ($action == 'block') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("UPDATE sessions SET active = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } elseif ($action == 'flag') {
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, content, type, sent_at, created_at) 
             VALUES (?, 'Potential Fraud Detected', 'Your voting activity has been flagged for review.', 'fraud_alert', NOW(), NOW())"
        );
        $stmt->execute([$user_id]); // Notify the flagged user
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