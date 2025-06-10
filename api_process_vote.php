<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once 'config.php';

// Database connection
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    ob_end_flush();
    exit;
}

// Include helper functions (copied from your original file)
function logUserActivity($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

function getUserVoteCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM blockchainrecords WHERE voter = ? AND timestamp >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['vote_count'] ?: 0;
}

function checkMultipleLogins($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as login_count FROM sessions WHERE user_id = ? AND login_time >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['login_count'] > 1 ? 1 : 0;
}

function getGeoLocation($ip) {
    $ipParts = explode('.', $ip);
    if (count($ipParts) < 4) return 4;
    if ($ipParts[0] == 41 || $ipParts[0] == 102) return 0; // Tanzania
    if ($ipParts[0] == 105 || $ipParts[0] == 197) return 1; // Kenya
    if ($ipParts[0] == 154) return 2; // Uganda
    if ($ipParts[0] == 168) return 3; // Rwanda
    return 4; // Other
}

function detectVPN($geo_location, $ip) {
    $ipParts = explode('.', $ip);
    $isNonLocal = ($geo_location > 0 || (count($ipParts) >= 1 && $ipParts[0] > 200));
    $randomFactor = random_int(0, 100);
    return $isNonLocal ? ($randomFactor < 80 ? 1 : 0) : ($randomFactor < 5 ? 1 : 0);
}

function logFraud($conn, $user_id, $voter_id, $ip_address, $election_id, $is_fraudulent, $confidence, $details, $action) {
    $description = $is_fraudulent ? "Fraud detected with confidence $confidence" : "No fraud detected";
    $details_array = json_decode($details, true) ?: [];
    $stmt = $conn->prepare(
        "INSERT INTO frauddetectionlogs (user_id, election_id, is_fraudulent, confidence, details, ip_history, vote_pattern, user_behavior, api_response, description, action, time_diff, votes_per_user, session_duration, geo_location, device_fingerprint, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        "iiidssdsssiiiiis",
        $user_id,
        $election_id,
        $is_fraudulent,
        $confidence,
        $details,
        json_encode($details_array['ip_history'] ?? []),
        $details_array['vote_pattern'] ?? 600,
        $details_array['user_behavior'] ?? 0,
        json_encode($details_array['api_response'] ?? []),
        $description,
        $action,
        $details_array['time_diff'] ?? 0,
        $details_array['votes_per_user'] ?? 0,
        $details_array['session_duration'] ?? 0,
        $details_array['geo_location'] ?? 0,
        $details_array['device_fingerprint'] ?? ''
    );
    $stmt->execute();
    $stmt->close();
}

function blockUser($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

function checkRateLimit($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM blockchainrecords WHERE voter = ? AND timestamp >= NOW() - INTERVAL 1 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['attempts'] < 5;
}

function getFraudHistory($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as fraud_count FROM frauddetectionlogs WHERE user_id = ? AND is_fraudulent = 1 AND confidence > 0.9 AND created_at >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['fraud_count'];
}

function getVotePattern($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT AVG(UNIX_TIMESTAMP(timestamp) - UNIX_TIMESTAMP(LAG(timestamp) OVER (ORDER BY timestamp))) as avg_interval 
         FROM blockchainrecords 
         WHERE voter = ? AND timestamp >= NOW() - INTERVAL 24 HOUR"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['avg_interval'] ?: 600;
}

function getIpHistory($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT DISTINCT ip_address 
         FROM sessions 
         WHERE user_id = ? AND login_time >= NOW() - INTERVAL 24 HOUR"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $ip_list = array_column($result, 'ip_address');
    return json_encode($ip_list ?: [$_SERVER['REMOTE_ADDR']]);
}

function getUserActivityCount($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as activity_count 
         FROM user_activity 
         WHERE user_id = ? AND timestamp >= NOW() - INTERVAL 24 HOUR"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['activity_count'] ?: 0;
}

// Session validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    error_log("Session validation failed for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    ob_end_flush();
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    echo json_encode(['success' => false, 'message' => '2FA not verified']);
    ob_end_flush();
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'Session validation failed']);
    ob_end_flush();
    exit;
}

$user_id = $_SESSION['user_id'];
$wallet_address = $_SESSION['wallet_address'] ?? null;

if (!$wallet_address) {
    error_log("No wallet address in session for user_id: " . $user_id);
    echo json_encode(['success' => false, 'message' => 'Wallet address not set']);
    ob_end_flush();
    exit;
}

// Process vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($input['vote_data'])) {
        error_log("Invalid vote data for user_id: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Invalid vote data']);
        ob_end_flush();
        exit;
    }

    $vote_data = $input['vote_data'];
    if (!isset($vote_data['election_id'], $vote_data['position_id'], $vote_data['candidate_id'], $vote_data['candidate_name'], $vote_data['position_name'])) {
        error_log("Incomplete vote data for user_id: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Incomplete vote data']);
        ob_end_flush();
        exit;
    }

    $election_id = (int)$vote_data['election_id'];
    $position_id = (int)$vote_data['position_id'];
    $candidate_id = (string)$vote_data['candidate_id']; // Ensure string
    $candidate_name = $vote_data['candidate_name'];
    $position_name = $vote_data['position_name'];

    // Validate candidate and position
    $stmt = $conn->prepare("SELECT id, firstname, lastname FROM candidates WHERE id = ? AND election_id = ?");
    $stmt->bind_param("ii", $candidate_id, $election_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $stmt->close();
    if ($candidate) {
        $candidate_name = $candidate['firstname'] . ' ' . $candidate['lastname'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid candidate']);
        ob_end_flush();
        exit;
    }

    $stmt = $conn->prepare("SELECT name FROM electionpositions WHERE position_id = ? AND election_id = ?");
    $stmt->bind_param("ii", $position_id, $election_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = $result->fetch_assoc();
    $stmt->close();
    if ($position) {
        $position_name = $position['name'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid position']);
        ob_end_flush();
        exit;
    }

    logUserActivity($conn, $user_id, 'vote_attempt');

    // Rate limiting
    if (!checkRateLimit($conn, $user_id)) {
        logUserActivity($conn, $user_id, 'rate_limit_exceeded');
        logFraud($conn, $user_id, $user_id, $_SERVER['REMOTE_ADDR'], $election_id, 1, 0.95, json_encode(['reason' => 'Rate limit exceeded']), 'block_user');
        blockUser($conn, $user_id);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Account blocked']);
        ob_end_flush();
        exit;
    }

    // Fraud detection
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $geo_location = getGeoLocation($ip_address);
    $ip_history = getIpHistory($conn, $user_id);
    $vote_pattern = getVotePattern($conn, $user_id);
    $user_behavior = getUserActivityCount($conn, $user_id) * 10;
    $fraud_data = [
        'time_diff' => time() - $_SESSION['last_activity'],
        'votes_per_user' => getUserVoteCount($conn, $user_id),
        'vpn_usage' => detectVPN($geo_location, $ip_address),
        'multiple_logins' => checkMultipleLogins($conn, $user_id),
        'session_duration' => time() - $_SESSION['start_time'],
        'geo_location' => $geo_location,
        'device_fingerprint' => $_SERVER['HTTP_USER_AGENT'],
        'ip_history' => json_decode($ip_history, true),
        'vote_pattern' => $vote_pattern,
        'user_behavior' => min($user_behavior, 100)
    ];

    $fraud_response = @file_get_contents("http://127.0.0.1:8003/predict", false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($fraud_data),
            'timeout' => 5
        ]
    ]));

    if ($fraud_response === false) {
        error_log("Fraud detection API failed for user_id: " . $user_id);
        $is_fraudulent = 0;
        $confidence = 0.0;
    } else {
        $fraud_result = json_decode($fraud_response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($fraud_result['fraud_label'], $fraud_result['fraud_probability'])) {
            error_log("Invalid fraud detection response for user_id: " . $user_id);
            $is_fraudulent = 0;
            $confidence = 0.0;
        } else {
            $is_fraudulent = $fraud_result['fraud_label'];
            $confidence = floatval($fraud_result['fraud_probability']);
        }
    }

    $action = 'none';
    if ($is_fraudulent) {
        $fraud_count = getFraudHistory($conn, $user_id);
        $action = ($confidence > ($fraud_count > 0 ? 0.7 : 0.9)) ? 'block_user' : 'logout';
        logFraud($conn, $user_id, $user_id, $ip_address, $election_id, $is_fraudulent, $confidence, json_encode(array_merge($fraud_data, ['api_response' => $fraud_result ?? []])), $action);
        if ($action === 'block_user') {
            blockUser($conn, $user_id);
            echo json_encode(['success' => false, 'message' => 'Fraud detected. Account blocked']);
            ob_end_flush();
            exit;
        } elseif ($action === 'logout') {
            echo json_encode(['success' => false, 'message' => 'Fraud detected. Logging out']);
            ob_end_flush();
            exit;
        }
    } else {
        logFraud($conn, $user_id, $user_id, $ip_address, $election_id, $is_fraudulent, $confidence, json_encode(array_merge($fraud_data, ['api_response' => $fraud_result ?? []])), $action);
    }

     $stmt = $conn->prepare("INSERT INTO blockchainrecords (election_id, voter, hash, timestamp, vote, candidate_id, position_id) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
    $hash = hash('sha256', $wallet_address . $candidate_id . $position_id . $election_id);
    $stmt->bind_param("isssii", $election_id, $user_id, $hash, $candidate_id, $candidate_id, $position_id);
    $stmt->execute();
    $stmt->close();

    logUserActivity($conn, $user_id, 'vote_cast');

    echo json_encode([
        'success' => true,
        'message' => 'Vote recorded successfully',
        'wallet_address' => $wallet_address
    ]);

    $conn->close();
    ob_end_flush();
    exit;
}


echo json_encode(['success' => false, 'message' => 'Invalid request method']);
ob_end_flush();
$conn->close();
?>