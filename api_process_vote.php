<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once 'config.php';

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

function hasVotedForPosition($conn, $user_id, $election_id, $position_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM user_votes WHERE user_id = ? AND election_id = ? AND position_id = ?");
    $stmt->bind_param("iii", $user_id, $election_id, $position_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($result['vote_count'] > 0);
}

function logUserActivity($conn, $user_id, $action) {
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

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
$session_wallet = $_SESSION['wallet_address'] ?? '';

$stmt = $conn->prepare("SELECT wallet_address FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$db_wallet = $user['wallet_address'] ?? '';
if ($session_wallet !== $db_wallet && !empty($db_wallet)) {
    error_log("Wallet address mismatch for user_id: " . $user_id);
    echo json_encode(['success' => false, 'message' => 'Wallet address mismatch detected']);
    ob_end_flush();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($input['vote_data']) || !is_array($input['vote_data'])) {
        error_log("Invalid or missing vote data for user_id: $user_id");
        echo json_encode(['success' => false, 'message' => 'Invalid vote data']);
        ob_end_flush();
        exit;
    }

    $vote_data = $input['vote_data'];
    if (!isset($vote_data['electionId'], $vote_data['positionId'], $vote_data['candidateId'], $vote_data['candidateName'], $vote_data['positionName'])) {
        error_log("Incomplete vote data for user_id: $user_id. Data: " . json_encode($vote_data));
        echo json_encode(['success' => false, 'message' => 'Incomplete vote data']);
        ob_end_flush();
        exit;
    }

    $election_id = (int)$vote_data['electionId'];
    $position_id = (int)$vote_data['positionId'];
    $candidate_id = (string)$vote_data['candidateId'];
    $candidate_name = $vote_data['candidateName'];
    $position_name = $vote_data['positionName'];

    if (hasVotedForPosition($conn, $user_id, $election_id, $position_id)) {
        echo json_encode(['success' => false, 'message' => 'You have already voted for this position']);
        ob_end_flush();
        exit;
    }

    $stmt = $conn->prepare("SELECT firstname, lastname FROM candidates WHERE id = ? AND election_id = ?");
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

    $conn->begin_transaction();
    try {
        $hash = hash('sha256', $session_wallet . $candidate_id . $position_id . $election_id);
        $vote_data_json = json_encode([
            'candidate_name' => $candidate_name,
            'position_name' => $position_name
        ]);
        $stmt = $conn->prepare("INSERT INTO blockchainrecords (election_id, voter, hash, timestamp, data, candidate_id, position_id) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
        $stmt->bind_param("isssii", $election_id, $session_wallet, $hash, $vote_data_json, $candidate_id, $position_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into blockchainrecords: " . $conn->error);
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO user_votes (user_id, election_id, position_id, candidate_id, voted_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiii", $user_id, $election_id, $position_id, $candidate_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into user_votes: " . $conn->error);
        }
        $stmt->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction failed for user_id: $user_id. Error: " . $e->getMessage() . ". Vote data: " . json_encode($vote_data));
        echo json_encode(['success' => false, 'message' => 'Failed to record vote']);
        ob_end_flush();
        exit;
    }

    logUserActivity($conn, $user_id, 'vote_cast');

    echo json_encode(['success' => true, 'message' => 'Vote recorded successfully', 'wallet_address' => $session_wallet, 'position_id' => $position_id]);
    $conn->close();
    ob_end_flush();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
ob_end_flush();
$conn->close();
?>