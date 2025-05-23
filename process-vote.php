<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// Enforce HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Load configuration and session validation
require_once 'config.php';

// Constants
const INACTIVITY_TIMEOUT = 5 * 60; // 5 minutes
const MAX_SESSION_DURATION = 30 * 60; // 30 minutes
const WARNING_TIME = 60; // 1 minute
const RATE_LIMIT_ATTEMPTS = 5;
const RATE_LIMIT_INTERVAL = '1 HOUR';
const GAS_LIMIT = 300000;
const FRAUD_CONFIDENCE_THRESHOLD_NEW = 0.9;
const FRAUD_CONFIDENCE_THRESHOLD_RECURRING = 0.7;

// Session Validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    error_log("Session validation failed: user_id or role not set or invalid.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: 2fa.php');
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch; possible session hijacking attempt.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed.'));
    exit;
}

if (!isset($_SESSION['start_time'])) {
    $_SESSION['start_time'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= MAX_SESSION_DURATION) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired. Please log in again.'));
    exit;
}

$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= INACTIVITY_TIMEOUT) {
    error_log("Session expired due to inactivity: $inactive_time seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.'));
    exit;
}

$_SESSION['last_activity'] = time();

// Validate CSRF token
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed.");
    header('Location: login.php?error=' . urlencode('Invalid CSRF token.'));
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate CSRF token

// Database connection
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}

// Helper Functions
function getUserVoteCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM blockchainrecords WHERE voter = ?");
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
    $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode,lat,lon";
    $response = @file_get_contents($url);
    if ($response === false) {
        error_log("Geolocation API failed for IP: {$ip}");
        return ['countryCode' => 'UNKNOWN', 'lat' => 0, 'lon' => 0];
    }
    $data = json_decode($response, true);
    if ($data['status'] !== 'success') {
        error_log("Geolocation API error for IP: {$ip}, response: " . json_encode($data));
        return ['countryCode' => 'UNKNOWN', 'lat' => 0, 'lon' => 0];
    }
    return [
        'countryCode' => $data['countryCode'],
        'lat' => $data['lat'],
        'lon' => $data['lon']
    ];
}

function detectVPN($ip, $api_key) {
    $url = "https://www.ipqualityscore.com/api/json/ip/{$api_key}/{$ip}?fields=vpn,proxy,fraud_score";
    $response = @file_get_contents($url);
    if ($response === false) {
        error_log("VPN detection API failed for IP: {$ip}");
        return 0;
    }
    $data = json_decode($response, true);
    if (!isset($data['vpn']) || !isset($data['proxy'])) {
        error_log("VPN detection API error for IP: {$ip}, response: " . json_encode($data));
        return 0;
    }
    return ($data['vpn'] || $data['proxy']) ? 1 : 0;
}

function logFraud($conn, $user_id, $voter_id, $ip_address, $election_id, $is_fraudulent, $confidence, $details, $action) {
    $description = $is_fraudulent ? "Fraud detected with confidence {$confidence}" : "No fraud detected";
    $details_json = json_encode(array_merge(json_decode($details, true), [
        'voter_id' => $voter_id,
        'ip_address' => $ip_address
    ]));
    $details_data = json_decode($details_json, true);
    $stmt = $conn->prepare(
        "INSERT INTO frauddetectionlogs (user_id, election_id, is_fraudulent, confidence, details, ip_history, vote_pattern, user_behavior, api_response, description, action, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        "iiidssdsss",
        $user_id,
        $election_id,
        $is_fraudulent,
        $confidence,
        $details_json,
        $details_data['ip_history'],
        $details_data['vote_pattern'],
        $details_data['user_behavior'],
        $details_data['api_response'],
        $description,
        $action
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
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM blockchainrecords WHERE voter = ? AND timestamp >= NOW() - INTERVAL " . RATE_LIMIT_INTERVAL);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['attempts'] < RATE_LIMIT_ATTEMPTS;
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
    $stmt = $conn->prepare("SELECT AVG(UNIX_TIMESTAMP(timestamp) - UNIX_TIMESTAMP(LAG(timestamp) OVER (ORDER BY timestamp))) as avg_interval FROM blockchainrecords WHERE voter = ? AND timestamp >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['avg_interval'] ?: 0;
}

function getIpHistory($conn, $user_id) {
    $stmt = $conn->prepare("SELECT DISTINCT ip_address FROM frauddetectionlogs WHERE user_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return json_encode(array_column($result, 'ip_address'));
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$user = [];
try {
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id, association, active FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if (!$user || $user['active'] == 0) {
        throw new Exception("No user found or user is blocked for user_id: " . $user_id);
    }
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('User not found, blocked, or server error. Please log in again.'));
    exit;
}

// Fetch elections and candidates
$profile_picture = 'uploads/passports/general.png';
$errors = [];
$elections = [];
$csrf_token = $_SESSION['csrf_token'];

try {
    $stmt = $conn->prepare(
        "SELECT u.association, u.college_id, u.hostel_id, c.name AS college_name
         FROM users u
         LEFT JOIN colleges c ON u.college_id = c.college_id
         WHERE u.user_id = ? AND u.active = 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_details = $result->fetch_assoc();
    $stmt->close();

    if (!$user_details) {
        $errors[] = "User not found or not active.";
    } else {
        $association = $user_details['association'];
        $college_id = $user_details['college_id'];
        $hostel_id = $user_details['hostel_id'] ?: 0;

        $stmt = $conn->prepare(
            "SELECT election_id, title
             FROM elections
             WHERE status = ? AND end_time > NOW() AND association = ?
             ORDER BY start_time ASC"
        );
        $status = 'ongoing';
        $stmt->bind_param('ss', $status, $association);
        $stmt->execute();
        $result = $stmt->get_result();
        $elections = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($elections as &$election) {
            $election_id = $election['election_id'];
            $positions = [];

            $query = "
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id, ep.hostel_id, ep.is_vice
                FROM electionpositions ep
                WHERE ep.election_id = ?
                AND (
                    ep.scope = 'university'
                    OR (ep.scope = 'college' AND ep.college_id = ?)
                ";
            $params = [$election_id, $college_id];
            $types = 'ii';
            if ($association === 'UDOSO' && $hostel_id) {
                $query .= " OR (ep.scope = 'hostel' AND ep.hostel_id = ?)";
                $params[] = $hostel_id;
                $types .= 'i';
            }
            $query .= ") ORDER BY ep.position_id";

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($position = $result->fetch_assoc()) {
                $position_id = $position['position_id'];
                $scope = $position['scope'];
                $is_vice = $position['is_vice'];
                $candidates = [];

                if ($scope === 'hostel') {
                    $cand_stmt = $conn->prepare(
                        "SELECT c.id, c.official_id, c.firstname, c.lastname, c.passport, c.pair_id, c.position_id, ep.is_vice
                         FROM candidates c
                         JOIN electionpositions ep ON c.position_id = ep.position_id
                         WHERE c.election_id = ? AND c.position_id = ? AND c.pair_id IS NULL"
                    );
                    $cand_stmt->bind_param('ii', $election_id, $position_id);
                    $cand_stmt->execute();
                    $cand_result = $cand_stmt->get_result();
                    while ($row = $cand_result->fetch_assoc()) {
                        $candidates[$row['id']] = [$row];
                    }
                    $cand_stmt->close();
                } else {
                    if ($is_vice == 0) {
                        $vice_position_id = null;
                        $vice_position_name = '';
                        $vice_stmt = $conn->prepare(
                            "SELECT position_id, name
                             FROM electionpositions
                             WHERE election_id = ? AND is_vice = 1
                             AND (
                                 (scope = 'university' AND scope = ?)
                                 OR (scope = 'college' AND scope = ? AND college_id = ?)
                             )"
                        );
                        $vice_stmt->bind_param('issi', $election_id, $scope, $scope, $position['position_college_id']);
                        $vice_stmt->execute();
                        $vice_result = $vice_stmt->get_result();
                        if ($vice_row = $vice_result->fetch_assoc()) {
                            $vice_position_id = $vice_row['position_id'];
                            $vice_position_name = $vice_row['name'];
                        }
                        $vice_stmt->close();

                        if ($vice_position_id) {
                            $cand_stmt = $conn->prepare(
                                "SELECT c.id, c.official_id, c.firstname, c.lastname, c.passport, c.pair_id, c.position_id, ep.is_vice
                                 FROM candidates c
                                 JOIN electionpositions ep ON c.position_id = ep.position_id
                                 WHERE c.election_id = ? AND c.position_id IN (?, ?)
                                 AND c.pair_id IS NOT NULL
                                 ORDER BY c.pair_id, ep.is_vice ASC"
                            );
                            $cand_stmt->bind_param('iii', $election_id, $position_id, $vice_position_id);
                            $cand_stmt->execute();
                            $cand_result = $cand_stmt->get_result();
                            while ($row = $cand_result->fetch_assoc()) {
                                $pair_id = $row['pair_id'];
                                if (!isset($candidates[$pair_id])) {
                                    $candidates[$pair_id] = [];
                                }
                                $candidates[$pair_id][] = $row;
                            }
                            $cand_stmt->close();
                        }

                        $position['vice_position_name'] = $vice_position_name;
                    } else {
                        continue;
                    }
                }

                $position['candidates'] = $candidates;
                $positions[] = $position;
            }
            $stmt->close();

            $election['positions'] = $positions;
        }
    }
} catch (Exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections due to a server error.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Vote | SmartUchaguzi</title>
    <link rel="icon" href="./Uploads/Vote.jpeg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
        }

        .header {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .nav a {
            color: #e6e6e6;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .nav a:hover {
            color: #f4a261;
        }

        .user {
            display: flex;
            align-items: center;
        }

        .user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }

        .dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .dropdown a,
        .dropdown span {
            display: block;
            padding: 10px 20px;
            color: #2d3748;
            text-decoration: none;
            font-size: 16px;
        }

        .dropdown a:hover {
            background: #f4a261;
            color: #fff;
        }

        .logout-link {
            display: none;
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
        }

        .dashboard {
            margin-top: 80px;
            padding: 30px;
            display: flex;
            justify-content: center;
        }

        .dash-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 1200px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .dash-content h2 {
            font-size: 28px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
        }

        .election-section {
            margin-bottom: 30px;
        }

        .election-section h3 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .position-section {
            margin-bottom: 20px;
        }

        .position-section h4 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 15px;
            border-bottom: 2px solid #1a3c34;
            padding-bottom: 5px;
        }

        .candidate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .candidate-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .candidate-card.selected {
            border: 2px solid #f4a261;
            background: #fff5e6;
        }

        .candidate-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #1a3c34;
            margin-right: 15px;
            transition: border-color 0.3s ease;
        }

        .candidate-card:hover .candidate-img {
            border-color: #f4a261;
        }

        .candidate-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .candidate-details h5 {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .candidate-details p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .vote-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .vote-checkbox {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            accent-color: #f4a261;
        }

        .vote-form button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
            margin-top: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .vote-form button:hover {
            background: #e76f51;
        }

        .vote-form button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        .error,
        .success {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .error {
            background: #ffe6e6;
            color: #e76f51;
            border: 1px solid #e76f51;
        }

        .success {
            background: #e6fff5;
            color: #2a9d8f;
            border: 1px solid #2a9d8f;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .modal-content p {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .modal-content button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
        }

        .modal-content button:hover {
            background: #e76f51;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                padding: 10px 20px;
            }

            .logo h1 {
                font-size: 20px;
            }

            .nav {
                margin: 10px 0;
                text-align: center;
            }

            .nav a {
                margin: 0 10px;
                font-size: 14px;
            }

            .user img {
                display: none;
            }

            .dropdown {
                display: block;
                position: static;
                box-shadow: none;
                background: none;
                text-align: center;
            }

            .dropdown a,
            .dropdown span {
                color: #e6e6e6;
                padding: 5px 10px;
            }

            .dropdown a:hover {
                background: none;
                color: #f4a261;
            }

            .logout-link {
                display: block;
                margin-top: 10px;
            }

            .dash-content {
                padding: 20px;
            }

            .dash-content h2 {
                font-size: 24px;
            }

            .election-section h3 {
                font-size: 18px;
            }

            .position-section h4 {
                font-size: 16px;
            }

            .candidate-grid {
                grid-template-columns: 1fr;
            }

            .candidate-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }

            .candidate-img {
                width: 60px;
                height: 60px;
                margin-bottom: 10px;
                margin-right: 0;
            }

            .candidate-details h5 {
                font-size: 14px;
            }

            .candidate-details p {
                font-size: 12px;
            }

            .vote-form button {
                font-size: 14px;
                padding: 10px 20px;
            }
        }

        @media (min-width: 600px) {
            .candidate-card {
                flex-direction: row;
                align-items: center;
            }

            .candidate-img {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="./Uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="nav">
            <a href="<?php echo htmlspecialchars($association === 'UDOSO' ? 'cive-students.php' : 'cive-teachers.php'); ?>">Back to Dashboard</a>
            <a href="#">Verify Vote</a>
            <a href="#">Results</a>
        </div>
        <div class="user">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic" aria-label="User Profile">
            <div class="dropdown" id="user-dropdown">
                <span style="color: #e6e6e6; padding: 10px 20px;"><?php echo htmlspecialchars($user['fname'] ?? 'User'); ?></span>
                <a href="#">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </header>

    <section class="dashboard">
        <div class="dash-content">
            <h2>Cast Your Vote</h2>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($elections)): ?>
                <div class="error">
                    <p>No ongoing elections available at this time.</p>
                </div>
            <?php else: ?>
                <div id="success-message" class="success" style="display: none;"></div>
                <div id="error-message" class="error" style="display: none;"></div>
                <?php foreach ($elections as $election): ?>
                    <div class="election-section">
                        <h3>Election: <?php echo htmlspecialchars($election['title']); ?></h3>
                        <?php if (empty($election['positions'])): ?>
                            <div class="error">
                                <p>No positions available for you to vote in this election.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($election['positions'] as $position): ?>
                                <div class="position-section">
                                    <h4>Position: <?php echo htmlspecialchars($position['position_name']); ?></h4>
                                    <?php if (empty($position['candidates'])): ?>
                                        <div class="error">
                                            <p>No candidates available for this position.</p>
                                        </div>
                                    <?php else: ?>
                                        <form class="vote-form" data-election-id="<?php echo $election['election_id']; ?>" data-position-id="<?php echo $position['position_id']; ?>" aria-label="Vote for <?php echo htmlspecialchars($position['position_name']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <div class="candidate-grid">
                                                <?php
                                                foreach ($position['candidates'] as $key => $candidateGroup) {
                                                    if ($position['scope'] !== 'hostel' && count($candidateGroup) == 2) {
                                                        $mainCandidate = $candidateGroup[0]['is_vice'] == 0 ? $candidateGroup[0] : $candidateGroup[1];
                                                        $viceCandidate = $candidateGroup[0]['is_vice'] == 1 ? $candidateGroup[0] : $candidateGroup[1];
                                                        $pair_id = $mainCandidate['pair_id'];
                                                ?>
                                                        <div class="candidate-card" data-candidate-id="<?php echo $mainCandidate['id']; ?>">
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($mainCandidate['passport'] ?: 'images/general.png'); ?>" alt="Candidate <?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname']); ?></h5>
                                                                    <p>Official ID: <?php echo htmlspecialchars($mainCandidate['official_id']); ?></p>
                                                                    <p>Association: <?php echo htmlspecialchars($association); ?></p>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($viceCandidate['passport'] ?: 'images/general.png'); ?>" alt="Running Mate <?php echo htmlspecialchars($viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?></h5>
                                                                    <p>Official ID: <?php echo htmlspecialchars($viceCandidate['official_id']); ?></p>
                                                                    <p>Role: <?php echo htmlspecialchars($position['vice_position_name']); ?></p>
                                                                </div>
                                                            </div>
                                                            <label class="vote-label">
                                                                <input type="radio" name="candidate_id" value="<?php echo $pair_id; ?>" id="candidate_<?php echo $mainCandidate['id']; ?>" required aria-label="Vote for <?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname'] . ' and ' . $viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?>">
                                                                <span class="vote-checkmark"></span>
                                                            </label>
                                                        </div>
                                                    <?php
                                                    } else {
                                                        $candidate = $candidateGroup[0];
                                                        $candidate_id = $candidate['id'];
                                                    ?>
                                                        <div class="candidate-card" data-candidate-id="<?php echo $candidate_id; ?>">
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($candidate['passport'] ?: 'images/general.png'); ?>" alt="Candidate <?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></h5>
                                                                    <p>Official ID: <?php echo htmlspecialchars($candidate['official_id']); ?></p>
                                                                    <p>Association: <?php echo htmlspecialchars($association); ?></p>
                                                                </div>
                                                            </div>
                                                            <label class="vote-label">
                                                                <input type="radio" name="candidate_id" value="<?php echo $candidate_id; ?>" id="candidate_<?php echo $candidate_id; ?>" required aria-label="Vote for <?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?>">
                                                                <span class="vote-checkmark"></span>
                                                            </label>
                                                        </div>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <button type="submit">Cast Vote</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute due to inactivity.</p>
            <button id="extend-session">OK</button>
        </div>
    </div>

    <div class="modal" id="confirm-vote-modal">
        <div class="modal-content">
            <p id="confirm-vote-message">Are you sure you want to vote for <span id="confirm-candidate-name"></span> for <span id="confirm-position-name"></span>?</p>
            <button id="confirm-vote">Confirm</button>
            <button id="cancel-vote">Cancel</button>
        </div>
    </div>

    <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js"></script>
    <script>
        const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/' + '<?php echo $alchemy_api_key; ?>');
        const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';
        const contractABI = <?php echo json_encode([
            [
                ["inputs" => [], "stateMutability" => "nonpayable", "type" => "constructor"],
                [
                    "anonymous" => false,
                    "inputs" => [
                        ["indexed" => false, "internalType" => "uint256", "name" => "electionId", "type" => "uint256"],
                        ["indexed" => true, "internalType" => "address", "name" => "voter", "type" => "address"],
                        ["indexed" => false, "internalType" => "uint256", "name" => "positionId", "type" => "uint256"],
                        ["indexed" => false, "internalType" => "uint256", "name" => "candidateId", "type" => "uint256"],
                        ["indexed" => false, "internalType" => "string", "name" => "candidateName", "type" => "string"],
                        ["indexed" => false, "internalType" => "string", "name" => "positionName", "type" => "string"]
                    ],
                    "name" => "VoteCast",
                    "type" => "event"
                ],
                [
                    "inputs" => [],
                    "name" => "admin",
                    "outputs" => [["internalType" => "address", "name" => "", "type" => "address"]],
                    "stateMutability" => "view",
                    "type" => "function"
                ],
                [
                    "inputs" => [
                        ["internalType" => "uint256", "name" => "electionId", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "positionId", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "candidateId", "type" => "uint256"],
                        ["internalType" => "string", "name" => "candidateName", "type" => "string"],
                        ["internalType" => "string", "name" => "positionName", "type" => "string"]
                    ],
                    "name" => "castVote",
                    "outputs" => [],
                    "stateMutability" => "nonpayable",
                    "type" => "function"
                ],
                [
                    "inputs" => [
                        ["internalType" => "uint256", "name" => "positionId", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "candidateId", "type" => "uint256"]
                    ],
                    "name" => "getVoteCount",
                    "outputs" => [["internalType" => "uint256", "name" => "", "type" => "uint256"]],
                    "stateMutability" => "view",
                    "type" => "function"
                ],
                [
                    "inputs" => [["internalType" => "uint256", "name" => "electionId", "type" => "uint256"]],
                    "name" => "getVotesByElection",
                    "outputs" => [
                        [
                            "components" => [
                                ["internalType" => "uint256", "name" => "electionId", "type" => "uint256"],
                                ["internalType" => "address", "name" => "voter", "type" => "address"],
                                ["internalType" => "uint256", "name" => "positionId", "type" => "uint256"],
                                ["internalType" => "uint256", "name" => "candidateId", "type" => "uint256"],
                                ["internalType" => "uint256", "name" => "timestamp", "type" => "uint256"],
                                ["internalType" => "string", "name" => "candidateName", "type" => "string"],
                                ["internalType" => "string", "name" => "positionName", "type" => "string"]
                            ],
                            "internalType" => "struct VoteContract.Vote[]",
                            "name" => "",
                            "type" => "tuple[]"
                        ]
                    ],
                    "stateMutability" => "view",
                    "type" => "function"
                ],
                [
                    "inputs" => [
                        ["internalType" => "address", "name" => "", "type" => "address"],
                        ["internalType" => "uint256", "name" => "", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "", "type" => "uint256"]
                    ],
                    "name" => "hasVoted",
                    "outputs" => [["internalType" => "bool", "name" => "", "type" => "bool"]],
                    "stateMutability" => "view",
                    "type" => "function"
                ],
                [
                    "inputs" => [
                        ["internalType" => "uint256", "name" => "", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "", "type" => "uint256"]
                    ],
                    "name" => "voteCount",
                    "outputs" => [["internalType" => "uint256", "name" => "", "type" => "uint256"]],
                    "stateMutability" => "view",
                    "type" => "function"
                ],
                [
                    "inputs" => [["internalType" => "uint256", "name" => "", "type" => "uint256"]],
                    "name" => "votes",
                    "outputs" => [
                        ["internalType" => "uint256", "name" => "electionId", "type" => "uint256"],
                        ["internalType" => "address", "name" => "voter", "type" => "address"],
                        ["internalType" => "uint256", "name" => "positionId", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "candidateId", "type" => "uint256"],
                        ["internalType" => "uint256", "name" => "timestamp", "type" => "uint256"],
                        ["internalType" => "string", "name" => "candidateName", "type" => "string"],
                        ["internalType" => "string", "name" => "positionName", "type" => "string"]
                    ],
                    "stateMutability" => "view",
                    "type" => "function"
                ]
            ]
        ]); ?>;
        const contract = new ethers.Contract(contractAddress, contractABI[0], provider);

        const GAS_LIMIT = 300000;
        const FRAUD_CONFIDENCE_THRESHOLD_NEW = 0.9;
        const FRAUD_CONFIDENCE_THRESHOLD_RECURRING = 0.7;
        const userId = <?php echo json_encode($user_id); ?>;
        const voterId = <?php echo json_encode(htmlspecialchars($user['fname'] . '/' . $user_id)); ?>;
        const ipAddress = <?php echo json_encode($_SERVER['REMOTE_ADDR']); ?>;
        const geoLocation = <?php echo json_encode(getGeoLocation($_SERVER['REMOTE_ADDR'])); ?>;
        const ipHistory = <?php echo getIpHistory($conn, $user_id); ?>;
        const votePattern = <?php echo json_encode(getVotePattern($conn, $user_id)); ?>;
        const timeDiff = <?php echo time() - $_SESSION['last_activity']; ?>;
        const votesPerUser = <?php echo getUserVoteCount($conn, $user_id); ?>;
        const vpnUsage = <?php echo detectVPN($_SERVER['REMOTE_ADDR'], $ipqualityscore_api_key); ?>;
        const multipleLogins = <?php echo checkMultipleLogins($conn, $user_id); ?>;
        const sessionDuration = <?php echo time() - $_SESSION['start_time']; ?>;
        const fraudCount = <?php echo getFraudHistory($conn, $user_id); ?>;
        const rateLimitOk = <?php echo json_encode(checkRateLimit($conn, $user_id)); ?>;

        function showSuccess(message) {
            const successMessage = document.getElementById('success-message');
            successMessage.textContent = message;
            successMessage.style.display = 'block';
            setTimeout(() => successMessage.style.display = 'none', 5000);
        }

        function showError(message) {
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            setTimeout(() => errorMessage.style.display = 'none', 5000);
        }

        async function retryOperation(operation, maxAttempts = 3, delay = 1000, timeout = 5000) {
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), timeout);
                try {
                    return await operation();
                } catch (error) {
                    if (attempt === maxAttempts) throw error;
                    console.warn(`Attempt ${attempt} failed: ${error.message}. Retrying...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                } finally {
                    clearTimeout(timeoutId);
                }
            }
        }

        async function checkFraud(formData, submitButton) {
            if (!rateLimitOk) {
                showError('Rate limit exceeded. Please try again later.');
                submitButton.disabled = false;
                submitButton.textContent = 'Cast Vote';
                return false;
            }

            const fraudData = {
                time_diff: timeDiff,
                votes_per_user: votesPerUser,
                vpn_usage: vpnUsage,
                multiple_logins: multipleLogins,
                session_duration: sessionDuration,
                geo_location: geoLocation,
                device_fingerprint: navigator.userAgent,
                ip_history: JSON.parse(ipHistory),
                vote_pattern: votePattern,
                user_behavior: userActivityScore
            };

            const fraudResponse = await retryOperation(() => fetch('http://localhost:800/predict', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(fraudData)
            }));
            const fraudResult = await fraudResponse.json();

            if (fraudResult.error || !('fraud_label' in fraudResult) || !('fraud_probability' in fraudResult)) {
                throw new Error('Invalid fraud detection response: ' + (fraudResult.error || 'Missing required fields'));
            }

            const isFraudulent = fraudResult.fraud_label;
            const confidence = fraudResult.fraud_probability;
            let action = 'none';
            const details = { ...fraudData, api_response: fraudResult };

            if (isFraudulent) {
                action = (confidence > (fraudCount > 0 ? FRAUD_CONFIDENCE_THRESHOLD_RECURRING : FRAUD_CONFIDENCE_THRESHOLD_NEW)) ? 'block_user' : 'logout';
                await fetch('log-fraud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userId,
                        voter_id: voterId,
                        ip_address: ipAddress,
                        election_id: formData.electionId,
                        is_fraudulent: isFraudulent,
                        confidence: confidence,
                        details: JSON.stringify(details),
                        action: action
                    })
                });
                if (action === 'block_user') {
                    await fetch('block-user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: userId })
                    });
                    showError('Fraud detected. Your account has been blocked.');
                    setTimeout(() => window.location.href = 'login.php?error=' + encodeURIComponent('Account blocked due to fraud detection.'), 3000);
                    return false;
                } else {
                    showError('Fraud detected. You will be logged out.');
                    setTimeout(() => window.location.href = 'login.php?error=' + encodeURIComponent('Logged out due to fraud detection.'), 3000);
                    return false;
                }
            } else {
                await fetch('log-fraud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userId,
                        voter_id: voterId,
                        ip_address: ipAddress,
                        election_id: formData.electionId,
                        is_fraudulent: isFraudulent,
                        confidence: confidence,
                        details: JSON.stringify(details),
                        action: action
                    })
                });
            }
            return true;
        }

        async function castVoteOnBlockchain(formData, submitButton) {
            submitButton.textContent = 'Submitting Vote...';
            let signer;
            try {
                signer = provider.getSigner();
            } catch (error) {
                throw new Error('Signer not available. Please ensure wallet is connected.');
            }
            const contractWithSigner = contract.connect(signer);
            const tx = await retryOperation(() => contractWithSigner.castVote(
                formData.electionId,
                formData.positionId,
                formData.candidateId,
                formData.candidateName,
                formData.positionName,
                { gasLimit: GAS_LIMIT }
            ));
            const receipt = await tx.wait();

            const voterAddress = await signer.getAddress();
            const response = await fetch('log-vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    election_id: formData.electionId,
                    hash: receipt.transactionHash,
                    data: JSON.stringify({
                        voter: voterAddress,
                        position_id: formData.positionId,
                        candidate_id: formData.candidateId,
                        candidateName: formData.candidateName,
                        positionName: formData.positionName
                    }),
                    voter: voterAddress,
                    position_id: formData.positionId,
                    candidate_id: formData.candidateId,
                    timestamp: Math.floor(Date.now() / 1000)
                })
            });
            const result = await response.json();
            if (!result.success) {
                throw new Error('Failed to log vote in database: ' + result.message);
            }

            showSuccess('Vote cast successfully! Transaction Hash: ' + receipt.transactionHash);
            return true;
        }

        // Track user activity for fraud detection
        let userActivityScore = 0;
        document.addEventListener('mousemove', () => userActivityScore = Math.min(userActivityScore + 1, 100));
        document.addEventListener('keypress', () => userActivityScore = Math.min(userActivityScore + 1, 100));

        // Handle vote form submission
        document.querySelectorAll('.vote-form').forEach(form => {
            const confirmModal = document.getElementById('confirm-vote-modal');
            const confirmMessage = document.getElementById('confirm-vote-message');
            const confirmCandidateName = document.getElementById('confirm-candidate-name');
            const confirmPositionName = document.getElementById('confirm-position-name');
            const confirmButton = document.getElementById('confirm-vote');
            const cancelButton = document.getElementById('cancel-vote');
            let formData = null;

            form.querySelectorAll('input[name="candidate_id"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    form.querySelectorAll('.candidate-card').forEach(card => card.classList.remove('selected'));
                    const card = radio.closest('.candidate-card');
                    if (card) card.classList.add('selected');
                });
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const electionId = form.getAttribute('data-election-id');
                const positionId = form.getAttribute('data-position-id');
                const candidateId = form.querySelector('input[name="candidate_id"]:checked')?.value;
                const candidateName = form.querySelector('input[name="candidate_id"]:checked')?.closest('.candidate-card')?.querySelector('.candidate-details h5').textContent;
                const positionName = form.querySelector('h4').textContent.replace('Position: ', '');
                const csrfToken = form.querySelector('input[name="csrf_token"]').value;

                if (!candidateId) {
                    showError('Please select a candidate to vote for.');
                    return;
                }
                if (csrfToken !== '<?php echo $csrf_token; ?>') {
                    showError('Invalid CSRF token. Please try again.');
                    return;
                }

                if (!/^\d+$/.test(candidateId)) {
                    showError('Invalid candidate selection.');
                    return;
                }

                confirmCandidateName.textContent = candidateName;
                confirmPositionName.textContent = positionName;
                confirmModal.style.display = 'flex';
                formData = { electionId, positionId, candidateId, candidateName, positionName };

                confirmButton.onclick = async () => {
                    confirmModal.style.display = 'none';
                    const submitButton = form.querySelector('button');
                    submitButton.disabled = true;
                    submitButton.textContent = 'Checking for Fraud...';

                    try {
                        const fraudCheckPassed = await checkFraud(formData, submitButton);
                        if (!fraudCheckPassed) return;

                        const voteCast = await castVoteOnBlockchain(formData, submitButton);
                        if (voteCast) {
                            form.querySelectorAll('input[name="candidate_id"]').forEach(radio => radio.disabled = true);
                            submitButton.disabled = true;
                            submitButton.textContent = 'Vote Cast';
                        }
                    } catch (error) {
                        console.error('Vote submission error:', error);
                        showError('Error: ' + (error.message.includes('signer') ? 'Wallet connection issue. Please connect your wallet.' : 'Failed to submit vote. Please try again.'));
                        submitButton.disabled = false;
                        submitButton.textContent = 'Cast Vote';
                    }
                };

                cancelButton.onclick = () => {
                    confirmModal.style.display = 'none';
                    form.querySelectorAll('input[name="candidate_id"]').forEach(radio => radio.checked = false);
                    form.querySelectorAll('.candidate-card').forEach(card => card.classList.remove('selected'));
                };
            });
        });

        // Session timeout handling
        const inactivityTimeout = <?php echo INACTIVITY_TIMEOUT; ?>;
        const warningTime = <?php echo WARNING_TIME; ?>;
        let inactivityTimer;
        let warningTimer;
        const timeoutModal = document.getElementById('timeout-modal');
        const timeoutMessage = document.getElementById('timeout-message');
        const extendSessionButton = document.getElementById('extend-session');

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(warningTimer);
            timeoutModal.style.display = 'none';
            warningTimer = setTimeout(() => {
                timeoutMessage.textContent = 'You will be logged out in 1 minute due to inactivity.';
                timeoutModal.style.display = 'flex';
            }, (inactivityTimeout - warningTime) * 1000);
            inactivityTimer = setTimeout(() => {
                window.location.href = 'login.php?error=' + encodeURIComponent('Session expired due to inactivity. Please log in again.');
            }, inactivityTimeout * 1000);
        }

        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        document.addEventListener('click', resetInactivityTimer);
        document.addEventListener('scroll', resetInactivityTimer);
        extendSessionButton.addEventListener('click', resetInactivityTimer);
        resetInactivityTimer();

        // Profile dropdown handling
        const profilePic = document.getElementById('profile-pic');
        const userDropdown = document.getElementById('user-dropdown');
        profilePic.addEventListener('click', () => {
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', (e) => {
            if (!profilePic.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>