<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// CSRF Token Validation
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed.");
    header('Location: login.php?error=' . urlencode('Invalid CSRF token.'));
    exit;
}

// Load configuration
require_once 'config.php';

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
    if ($ipParts[0] == 41 || $ipParts[0] == 102) return 0; // Tanzania
    if ($ipParts[0] == 105 || $ipParts[0] == 197) return 1; // Kenya
    if ($ipParts[0] == 154) return 2; // Uganda
    if ($ipParts[0] == 168) return 3; // Rwanda
    return 4; // Other
}

function detectVPN($geo_location, $ip) {
    $ipParts = explode('.', $ip);
    $isNonLocal = ($geo_location > 0 || $ipParts[0] > 200);
    $randomFactor = random_int(0, 100);
    return $isNonLocal ? ($randomFactor < 80 ? 1 : 0) : ($randomFactor < 5 ? 1 : 0);
}

function logFraud($conn, $user_id, $voter_id, $ip_address, $election_id, $is_fraudulent, $confidence, $details, $action) {
    $description = $is_fraudulent ? "Fraud detected with confidence $confidence" : "No fraud detected";
    $details_array = json_decode($details, true);
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
        json_encode($details_array['ip_history']),
        $details_array['vote_pattern'],
        $details_array['user_behavior'],
        json_encode($details_array['api_response']),
        $description,
        $action,
        $details_array['time_diff'],
        $details_array['votes_per_user'],
        $details_array['session_duration'],
        $details_array['geo_location'],
        $details_array['device_fingerprint']
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
    return $result['avg_interval'] ?: 600; // Default to non-fraud-like value
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
    return json_encode($ip_list ?: [$_SERVER['REMOTE_ADDR']]); // Fallback to current IP
}

function getUserBehavior($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as activity_count 
         FROM user_activity 
         WHERE user_id = ? AND timestamp >= NOW() - INTERVAL 24 HOUR"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $activity_count = $result['activity_count'] ?: 0;
    return min($activity_count * 10, 100); // Scale to 0-100
}

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

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

// Store session data in database
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['session_stored'])) {
    $session_id = session_id();
    $session_token = session_id(); 
    $stmt = $conn->prepare(
        "INSERT INTO sessions (session_id, user_id, login_time, ip_address, device_fingerprint, session_token) 
         VALUES (?, ?, NOW(), ?, ?, ?)"
    );
    $stmt->bind_param("sisss", $session_id, $user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $session_token);
    $stmt->execute();
    $stmt->close();
    $_SESSION['session_stored'] = true;
}

$inactivity_timeout = 5 * 60;
$max_session_duration = 30 * 60;
$warning_time = 60;

if (!isset($_SESSION['start_time'])) {
    $_SESSION['start_time'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= $max_session_duration) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired. Please log in again.'));
    exit;
}

$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= $inactivity_timeout) {
    error_log("Session expired due to inactivity: $inactive_time seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.'));
    exit;
}

$_SESSION['last_activity'] = time();

$user = [];
try {
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id, association, active FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
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

$profile_picture = 'uploads/passports/general.png';
$errors = [];
$elections = [];
$csrf_token = generateCsrfToken();


$association = $user['association'];
$college_id = $user['college_id'];
$dashboard_file = '';
if ($association === 'UDOSO') { // Students
    if ($college_id == 1) { // CIVE
        $dashboard_file = 'cive-students.php';
    } elseif ($college_id == 2) { // COED
        $dashboard_file = 'coed-students.php';
    } elseif ($college_id == 3) { // CNMS
        $dashboard_file = 'cnms-students.php';
    } else {
        $dashboard_file = 'login.php';
    }
} elseif ($association === 'UDOMASA') { // Teachers
    if ($college_id == 1) {
        $dashboard_file = 'cive-teachers.php';
    } elseif ($college_id == 2) {
        $dashboard_file = 'coed-teachers.php';
    } elseif ($college_id == 3) {
        $dashboard_file = 'cnms-teachers.php';
    } else {
        $dashboard_file = 'login.php';
    }
}

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
            if ($association === 'UDOSO' && $hostel_id) {
                $query .= " OR (ep.scope = 'hostel' AND ep.hostel_id = ?)";
            }
            $query .= ") ORDER BY ep.position_id";

            $stmt = $conn->prepare($query);
            if ($association === 'UDOSO' && $hostel_id) {
                $stmt->bind_param('iii', $election_id, $college_id, $hostel_id);
            } else {
                $stmt->bind_param('ii', $election_id, $college_id);
            }
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
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
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

        .back-arrow {
            display: inline-block;
            width: 60px;
            height: 60px;
            background: #1a3c34;
            border-radius: 50%;
            text-align: center;
            line-height: 60px;
            color: #fff;
            text-decoration: none;
            font-size: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
        }

        .back-arrow:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            background: #f4a261;
        }

        #profile-pic {
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        #profile-pic:hover {
            transform: scale(1.05);
        }
    </style>
</head>

<body>
    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <a href="<?php echo htmlspecialchars($dashboard_file); ?>" class="back-arrow">←</a>
    </div>
    <div style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic" style="width: 40px; height: 40px; border-radius: 50%; cursor: pointer;">
        <div class="dropdown" id="user-dropdown">
            <span style="color: #2d3748; padding: 10px 20px;"><?php echo htmlspecialchars($user['fname'] ?? 'User'); ?></span>
            <a href="#">My Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

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
        const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
        const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';
        const contractABI = <?php echo file_get_contents('./js/contract-abi.json'); ?>;
        const contract = new ethers.Contract(contractAddress, contractABI, provider);

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

                confirmCandidateName.textContent = candidateName;
                confirmPositionName.textContent = positionName;
                confirmModal.style.display = 'flex';
                formData = {
                    electionId,
                    positionId,
                    candidateId,
                    candidateName,
                    positionName
                };

                confirmButton.onclick = async () => {
                    confirmModal.style.display = 'none';
                    const submitButton = form.querySelector('button');
                    submitButton.disabled = true;
                    submitButton.textContent = 'Checking for Fraud...';

                    try {
                        if (!<?php echo json_encode(checkRateLimit($conn, $user_id)); ?>) {
                            showError('Rate limit exceeded. Please try again later.');
                            submitButton.disabled = false;
                            submitButton.textContent = 'Cast Vote';
                            return;
                        }

                        const ipAddress = '<?php echo $_SERVER['REMOTE_ADDR']; ?>';
                        const voterId = '<?php echo htmlspecialchars($user['fname'] . '/' . $user_id); ?>';
                        const geoLocation = <?php echo getGeoLocation($_SERVER['REMOTE_ADDR']); ?>;
                        const ipHistory = <?php echo getIpHistory($conn, $user_id); ?>;
                        const votePattern = <?php echo getVotePattern($conn, $user_id); ?>;
                        const userBehavior = <?php echo getUserBehavior($conn, $user_id); ?>;
                        const fraudData = {
                            time_diff: <?php echo time() - $_SESSION['last_activity']; ?>,
                            votes_per_user: <?php echo getUserVoteCount($conn, $user_id); ?>,
                            vpn_usage: <?php echo detectVPN(getGeoLocation($_SERVER['REMOTE_ADDR']), $_SERVER['REMOTE_ADDR']); ?>,
                            multiple_logins: <?php echo checkMultipleLogins($conn, $user_id); ?>,
                            session_duration: <?php echo time() - $_SESSION['start_time']; ?>,
                            geo_location: geoLocation,
                            device_fingerprint: '<?php echo $_SERVER['HTTP_USER_AGENT']; ?>',
                            ip_history: JSON.parse(ipHistory),
                            vote_pattern: votePattern,
                            user_behavior: userBehavior
                        };

                        const fraudResponse = await retryOperation(() => fetch('http://127.0.0.1:8003/predict', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(fraudData)
                        }), 3, 1000, 5000);
                        const fraudResult = await fraudResponse.json();

                        if (fraudResult.error || !('fraud_label' in fraudResult) || !('fraud_probability' in fraudResult)) {
                            throw new Error('Invalid fraud detection response: ' + (fraudResult.error || 'Missing required fields'));
                        }

                        const isFraudulent = fraudResult.fraud_label;
                        const confidence = fraudResult.fraud_probability;
                        let action = 'none';
                        const details = {
                            ...fraudData,
                            api_response: fraudResult
                        };

                        if (isFraudulent) {
                            const fraudCount = <?php echo getFraudHistory($conn, $user_id); ?>;
                            action = (confidence > (fraudCount > 0 ? 0.7 : 0.9)) ? 'block_user' : 'logout';
                            await fetch('log-fraud.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    user_id: <?php echo $user_id; ?>,
                                    voter_id: voterId,
                                    ip_address: ipAddress,
                                    election_id: electionId,
                                    is_fraudulent: isFraudulent,
                                    confidence: confidence,
                                    details: JSON.stringify(details),
                                    action: action
                                })
                            });
                            if (action === 'block_user') {
                                await fetch('block-user.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        user_id: <?php echo $user_id; ?>
                                    })
                                });
                                showError('Fraud detected. Your account has been blocked.');
                                setTimeout(() => window.location.href = 'login.php?error=' + encodeURIComponent('Account blocked due to fraud detection.'), 3000);
                                return;
                            } else {
                                showError('Fraud detected. You will be logged out.');
                                setTimeout(() => window.location.href = 'login.php?error=' + encodeURIComponent('Logged out due to fraud detection.'), 3000);
                                return;
                            }
                        } else {
                            await fetch('log-fraud.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    user_id: <?php echo $user_id; ?>,
                                    voter_id: voterId,
                                    ip_address: ipAddress,
                                    election_id: electionId,
                                    is_fraudulent: isFraudulent,
                                    confidence: confidence,
                                    details: JSON.stringify(details),
                                    action: action
                                })
                            });
                        }

                        submitButton.textContent = 'Submitting Vote...';
                        let signer;
                        try {
                            signer = provider.getSigner();
                        } catch (error) {
                            throw new Error('Signer not available. Please ensure wallet is connected.');
                        }
                        const contractWithSigner = contract.connect(signer);
                        const tx = await retryOperation(() => contractWithSigner.castVote(
                            electionId, positionId, candidateId, candidateName, positionName, {
                                gasLimit: 300000
                            }
                        ));
                        const receipt = await tx.wait();

                        const response = await fetch('log-vote.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                election_id: electionId,
                                hash: receipt.transactionHash,
                                data: JSON.stringify({
                                    voter: await signer.getAddress(),
                                    position_id: positionId,
                                    candidate_id: candidateId,
                                    candidateName,
                                    positionName
                                }),
                                voter: await signer.getAddress(),
                                position_id: positionId,
                                candidate_id: candidateId,
                                timestamp: Math.floor(Date.now() / 1000)
                            })
                        });
                        const result = await response.json();
                        if (!result.success) {
                            throw new Error('Failed to log vote in database: ' + result.message);
                        }

                        showSuccess('Vote cast successfully! Transaction Hash: ' + receipt.transactionHash);
                        form.querySelectorAll('input[name="candidate_id"]').forEach(radio => radio.disabled = true);
                        submitButton.disabled = true;
                        submitButton.textContent = 'Vote Cast';

                        await fetch('set-voted-flag.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });
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

        const inactivityTimeout = <?php echo $inactivity_timeout; ?>;
        const warningTime = <?php echo $warning_time; ?>;
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