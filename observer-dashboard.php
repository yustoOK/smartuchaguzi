<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// Database connection
$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}

$required_role = 'observer';

// Simplified session validation with logging
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
    error_log("Session validation failed: user_id or role not set or invalid. Session: " . print_r($_SESSION, true));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Please log in as an observer.'));
    exit;
}

// Log session details for debugging
error_log("Session after validation: user_id=" . ($_SESSION['user_id'] ?? 'unset') . 
          ", role=" . ($_SESSION['role'] ?? 'unset'));

// User agent validation
if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

$inactivity_timeout = 30 * 60;
$max_session_duration = 60 * 60; // 1 hour
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

// CSRF token handling
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT fname, mname, lname FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception("No user found for user_id: " . $user_id);
    }
    $observer_name = htmlspecialchars($user['fname'] . ' ' . ($user['mname'] ? $user['mname'] . ' ' : '') . $user['lname']);
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('User not found or server error. Please log in again.'));
    exit;
}

// Overview statistics with error handling
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM elections");
    $stmt->execute();
    $total_elections = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Elections count query error: " . $e->getMessage());
    $total_elections = "N/A";
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates");
    $stmt->execute();
    $total_candidates = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Candidates count query error: " . $e->getMessage());
    $total_candidates = "N/A";
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes");
    $stmt->execute();
    $total_votes = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Votes count query error: " . $e->getMessage());
    $total_votes = "N/A";
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM frauddetectionlogs WHERE is_fraudulent = 1");
    $stmt->execute();
    $total_anomalies = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Fraud incidents count query error: " . $e->getMessage());
    $total_anomalies = "N/A";
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for user_id: $user_id");
        die("CSRF token validation failed.");
    }
    $election_id = filter_input(INPUT_POST, 'election_id', FILTER_VALIDATE_INT);
    if ($election_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO auditlogs (user_id, action, details, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
            $action = "Report Generated";
            $details = "Election ID: $election_id";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->execute([$user_id, $action, $details, $ip_address]);
            header("Location: generate-report.php?election_id=$election_id");
            exit;
        } catch (PDOException $e) {
            error_log("Audit log insertion error: " . $e->getMessage());
            die("Failed to generate report. Please try again.");
        }
    } else {
        error_log("Invalid election_id for report generation: " . $election_id);
        die("Invalid election selection.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Election Observer Dashboard | SmartUchaguzi</title>
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
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/university.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #2d3748;
            line-height: 1.6;
            min-height: 100vh;
            scroll-behavior: smooth;
        }
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(26, 60, 52, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: gradientShift 5s infinite alternate;
        }
        @keyframes gradientShift {
            0% { background: rgba(26, 60, 52, 0.9); }
            100% { background: rgba(44, 82, 76, 0.9); }
        }
        .header .logo {
            display: flex;
            align-items: center;
        }
        .header .logo img {
            width: 40px;
            height: 40px;
            margin-right: 15px;
            border-radius: 50%;
            border: 2px solid #f4a261;
            transition: transform 0.3s ease;
        }
        .header .logo img:hover {
            transform: rotate(360deg);
        }
        .header .logo h1 {
            font-size: 20px;
            font-weight: 600;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .header .nav {
            display: flex;
            gap: 15px;
        }
        .header .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .header .nav a.active {
            background: #f4a261;
            color: #fff;
        }
        .header .nav a:hover {
            background: #f4a261;
            color: #fff;
            transform: scale(1.05);
        }
        .header .user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header .user span {
            font-size: 16px;
            color: #e6e6e6;
        }
        .header .user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #f4a261;
        }
        .header .user a {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .header .user a:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }
        .dashboard {
            padding: 100px 20px 40px;
        }
        .dash-content {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dash-content h2 {
            font-size: 28px;
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .overview .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .overview .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(244, 162, 97, 0.3);
        }
        .overview .card i {
            font-size: 30px;
            color: #f4a261;
            margin-bottom: 10px;
        }
        .overview .card .text {
            font-size: 16px;
            color: #4a5568;
        }
        .overview .card .number {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
            margin-top: 5px;
        }
        h3 {
            font-size: 22px;
            color: #1a3c34;
            margin-bottom: 20px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .election-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .election-card, .analytics-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .election-card:hover, .analytics-card:hover {
            transform: translateY(-5px);
        }
        .election-card p, .analytics-card p {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 10px;
        }
        .election-card .status, .analytics-card .winner {
            font-weight: 500;
            color: #1a3c34;
        }
        .election-card .hash, .analytics-card .hash {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.05);
            padding: 5px;
            border-radius: 4px;
            word-break: break-all;
        }
        .vote-section table, .anomaly-section table, .audit-section table {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-collapse: collapse;
            margin-top: 20px;
        }
        .vote-section th, .anomaly-section th, .audit-section th {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 12px;
            font-size: 16px;
        }
        .vote-section td, .anomaly-section td, .audit-section td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #4a5568;
            font-size: 14px;
        }
        .anomaly-section .score {
            color: #e74c3c;
            font-weight: 500;
        }
        .report-section {
            text-align: center;
            margin-top: 20px;
        }
        .report-section form {
            display: inline-block;
            margin: 10px;
        }
        .report-section select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #e6e6e6;
            background: rgba(255, 255, 255, 0.1);
            color: #2d3748;
            margin-right: 10px;
        }
        .report-section button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            border: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .report-section button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }
        .quick-links {
            margin-top: 40px;
            text-align: center;
        }
        .quick-links h3 {
            font-size: 22px;
            margin-bottom: 15px;
        }
        .quick-links ul {
            list-style: none;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .quick-links ul li a {
            display: block;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            color: #f4a261;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .quick-links ul li a:hover {
            background: #f4a261;
            color: #fff;
            transform: scale(1.05);
        }
        footer {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            padding: 40px 20px;
            text-align: center;
            margin-top: 40px;
        }
        footer p {
            font-size: 14px;
        }
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(244, 162, 97, 0.5);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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
            transition: all 0.3s ease;
        }
        .modal-content button:hover {
            background: #e76f51;
        }
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            .header .nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            .overview, .election-cards {
                grid-template-columns: 1fr;
            }
            .dash-content {
                padding: 20px;
            }
        }
        @media (max-width: 480px) {
            .header .logo h1 {
                font-size: 18px;
            }
            .header .nav a {
                padding: 6px 12px;
                font-size: 14px;
            }
            .header .user img {
                width: 30px;
                height: 30px;
            }
            .dash-content h2 {
                font-size: 24px;
            }
            h3 {
                font-size: 18px;
            }
            .overview .card .number {
                font-size: 20px;
            }
            .vote-section td, .anomaly-section td, .audit-section td {
                font-size: 12px;
            }
            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 20px;
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
            <a href="#" data-section="overview" class="active">Overview</a>
            <a href="#" data-section="elections">Elections</a>
            <a href="#" data-section="vote-feed">Vote Feed</a>
            <a href="#" data-section="anomalies">Fraud Detection</a>
            <a href="#" data-section="analytics">Analytics</a>
            <a href="#" data-section="audit-logs">Audit Logs</a>
        </div>
        <div class="user">
            <span><?php echo $observer_name; ?></span>
            <img src="images/observer.png" alt="Observer" onerror="this.src='images/general.png';">
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <section class="dashboard">
        <div class="dash-content">
            <h2>Election Observer Dashboard</h2>

            <div class="content-section active" id="overview">
                <div class="overview">
                    <div class="card">
                        <i class="fas fa-vote-yea"></i>
                        <span class="text">Total Elections</span>
                        <span class="number"><?php echo $total_elections; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-users"></i>
                        <span class="text">Total Candidates</span>
                        <span class="number"><?php echo $total_candidates; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-check-circle"></i>
                        <span class="text">Total Votes</span>
                        <span class="number"><?php echo $total_votes; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="text">Fraud Incidents</span>
                        <span class="number"><?php echo $total_anomalies; ?></span>
                    </div>
                </div>
            </div>

            <div class="content-section" id="elections">
                <h3>All Elections</h3>
                <div class="election-cards">
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT e.id, e.association, e.start_time, e.end_time, e.blockchain_hash, c.name AS college_name 
                                               FROM elections e 
                                               LEFT JOIN colleges c ON e.college_id = c.college_id 
                                               ORDER BY e.start_time DESC");
                        $stmt->execute();
                        $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($elections as $election) {
                            $current_time = new DateTime();
                            $start_time = new DateTime($election['start_time']);
                            $end_time = new DateTime($election['end_time']);
                            $status = ($current_time < $start_time) ? 'Upcoming' : 
                                      (($current_time > $end_time) ? 'Completed' : 'Ongoing');
                            echo "<div class='election-card'>";
                            echo "<p><strong>" . ($election['college_name'] ? htmlspecialchars($election['college_name']) : 'University-Wide') . " (" . htmlspecialchars($election['association']) . ")</strong></p>";
                            echo "<p><strong>Start:</strong> " . htmlspecialchars($election['start_time']) . "</p>";
                            echo "<p><strong>End:</strong> " . htmlspecialchars($election['end_time']) . "</p>";
                            echo "<p><strong>Status:</strong> <span class='status'>$status</span></p>";
                            echo "<p><strong>Blockchain Hash:</strong> <span class='hash'>" . htmlspecialchars($election['blockchain_hash'] ?? 'N/A') . "</span></p>";
                            $stmt_pos = $pdo->prepare("SELECT ep.name FROM election_positions ep WHERE ep.election_id = ?");
                            $stmt_pos->execute([$election['id']]);
                            while ($pos = $stmt_pos->fetch(PDO::FETCH_ASSOC)) {
                                echo "<p><strong>Position:</strong> " . htmlspecialchars($pos['name']) . "</p>";
                                $stmt_cand = $pdo->prepare("SELECT u.fname, u.mname, u.lname 
                                                            FROM candidates c 
                                                            JOIN users u ON c.user_id = u.user_id 
                                                            WHERE c.election_id = ? AND c.position_id = (SELECT id FROM election_positions WHERE name = ? AND election_id = ?)");
                                $stmt_cand->execute([$election['id'], $pos['name'], $election['id']]);
                                while ($cand = $stmt_cand->fetch(PDO::FETCH_ASSOC)) {
                                    $full_name = $cand['fname'] . ' ' . ($cand['mname'] ? $cand['mname'] . ' ' : '') . $cand['lname'];
                                    echo "<p>Candidate: " . htmlspecialchars($full_name) . "</p>";
                                }
                            }
                            echo "</div>";
                        }
                        if (empty($elections)) {
                            echo "<p>No elections found.</p>";
                        }
                    } catch (PDOException $e) {
                        error_log("Elections query error: " . $e->getMessage());
                        echo "<p>Error loading elections. Please try again later.</p>";
                    }
                    ?>
                </div>
            </div>

            <div class="content-section" id="vote-feed">
                <h3>Live Vote Feed</h3>
                <div class="vote-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Election</th>
                                <th>Position</th>
                                <th>Candidate</th>
                                <th>Association</th>
                                <th>Blockchain Hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT v.vote_timestamp, v.blockchain_hash, e.association, c.id AS candidate_id, ep.name AS position_name, u.fname, u.mname, u.lname, c.name AS college_name 
                                                       FROM votes v 
                                                       JOIN elections e ON v.election_id = e.id 
                                                       JOIN candidates c ON v.candidate_id = c.id 
                                                       JOIN election_positions ep ON c.position_id = ep.id 
                                                       JOIN users u ON c.user_id = u.user_id 
                                                       LEFT JOIN colleges c ON e.college_id = c.college_id 
                                                       ORDER BY v.vote_timestamp DESC LIMIT 50");
                                $stmt->execute();
                                $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if ($votes) {
                                    foreach ($votes as $vote) {
                                        $election_name = ($vote['college_name'] ? htmlspecialchars($vote['college_name']) : 'University-Wide') . ' (' . htmlspecialchars($vote['association']) . ')';
                                        $full_name = $vote['fname'] . ' ' . ($vote['mname'] ? $vote['mname'] . ' ' : '') . $vote['lname'];
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($vote['vote_timestamp']) . "</td>";
                                        echo "<td>$election_name</td>";
                                        echo "<td>" . htmlspecialchars($vote['position_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($full_name) . "</td>";
                                        echo "<td>" . htmlspecialchars($vote['association']) . "</td>";
                                        echo "<td class='hash'>" . htmlspecialchars($vote['blockchain_hash']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6'>No votes recorded yet.</td></tr>";
                                }
                            } catch (PDOException $e) {
                                error_log("Vote feed query error: " . $e->getMessage());
                                echo "<tr><td colspan='6'>Error loading vote feed. Please try again later.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section" id="anomalies">
                <h3>Fraud Detection Logs</h3>
                <div class="anomaly-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Election</th>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>Anomaly Score</th>
                                <th>Details</th>
                                <th>Blockchain Hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT f.timestamp, f.anomaly_score, f.details, f.ip_address, v.blockchain_hash, e.association, c.name AS college_name, u.fname, u.mname, u.lname 
                                                       FROM frauddetectionlogs f 
                                                       JOIN votes v ON f.vote_id = v.id 
                                                       JOIN elections e ON v.election_id = e.id 
                                                       JOIN users u ON f.user_id = u.user_id 
                                                       LEFT JOIN colleges c ON e.college_id = c.college_id 
                                                       WHERE f.is_fraudulent = 1 
                                                       ORDER BY f.timestamp DESC");
                                $stmt->execute();
                                $anomalies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if ($anomalies) {
                                    foreach ($anomalies as $anomaly) {
                                        $election_name = ($anomaly['college_name'] ? htmlspecialchars($anomaly['college_name']) : 'University-Wide') . ' (' . htmlspecialchars($anomaly['association']) . ')';
                                        $full_name = $anomaly['fname'] . ' ' . ($anomaly['mname'] ? $anomaly['mname'] . ' ' : '') . $anomaly['lname'];
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($anomaly['timestamp']) . "</td>";
                                        echo "<td>$election_name</td>";
                                        echo "<td>" . htmlspecialchars($full_name) . "</td>";
                                        echo "<td>" . htmlspecialchars($anomaly['ip_address']) . "</td>";
                                        echo "<td class='score'>" . htmlspecialchars($anomaly['anomaly_score']) . "</td>";
                                        echo "<td>" . htmlspecialchars($anomaly['details'] ?? 'N/A') . "</td>";
                                        echo "<td class='hash'>" . htmlspecialchars($anomaly['blockchain_hash']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No fraud incidents detected.</td></tr>";
                                }
                            } catch (PDOException $e) {
                                error_log("Fraud detection logs query error: " . $e->getMessage());
                                echo "<tr><td colspan='7'>Error loading fraud detection logs. Please try again later.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section" id="analytics">
                <h3>Election Analytics</h3>
                <div class="election-cards">
                    <?php
                    try {
                        $stmt = $pdo->prepare("SELECT e.id, e.association, e.end_time, c.name AS college_name 
                                               FROM elections e 
                                               LEFT JOIN colleges c ON e.college_id = c.college_id 
                                               ORDER BY e.end_time DESC");
                        $stmt->execute();
                        $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($elections as $election) {
                            echo "<div class='analytics-card'>";
                            echo "<p><strong>" . ($election['college_name'] ? htmlspecialchars($election['college_name']) : 'University-Wide') . " (" . htmlspecialchars($election['association']) . ")</strong></p>";
                            $stmt_pos = $pdo->prepare("SELECT ep.id, ep.name FROM election_positions ep WHERE ep.election_id = ?");
                            $stmt_pos->execute([$election['id']]);
                            while ($pos = $stmt_pos->fetch(PDO::FETCH_ASSOC)) {
                                echo "<p><strong>Position:</strong> " . htmlspecialchars($pos['name']) . "</p>";
                                $stmt_cand = $pdo->prepare("SELECT u.fname, u.mname, u.lname, COUNT(v.id) as vote_count 
                                                            FROM candidates c 
                                                            JOIN users u ON c.user_id = u.user_id 
                                                            LEFT JOIN votes v ON v.candidate_id = c.id 
                                                            WHERE c.election_id = ? AND c.position_id = ? 
                                                            GROUP BY c.id, u.fname, u.mname, u.lname 
                                                            ORDER BY vote_count DESC");
                                $stmt_cand->execute([$election['id'], $pos['id']]);
                                $candidates = $stmt_cand->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($candidates as $cand) {
                                    $full_name = $cand['fname'] . ' ' . ($cand['mname'] ? $cand['mname'] . ' ' : '') . $cand['lname'];
                                    echo "<p>Candidate: " . htmlspecialchars($full_name) . " - Votes: " . $cand['vote_count'] . "</p>";
                                }
                                if (new DateTime() > new DateTime($election['end_time'])) {
                                    $winner = $candidates ? htmlspecialchars($candidates[0]['fname'] . ' ' . ($candidates[0]['mname'] ? $candidates[0]['mname'] . ' ' : '') . $candidates[0]['lname']) : 'None';
                                    echo "<p><strong>Winner:</strong> <span class='winner'>$winner</span></p>";
                                }
                            }
                            echo "<div class='report-section'>";
                            echo "<form method='POST'>";
                            echo "<input type='hidden' name='action' value='generate_report'>";
                            echo "<input type='hidden' name='election_id' value='" . $election['id'] . "'>";
                            echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
                            echo "<button type='submit'>Generate Report</button>";
                            echo "</form>";
                            echo "</div>";
                            echo "</div>";
                        }
                        if (empty($elections)) {
                            echo "<p>No election analytics available.</p>";
                        }
                    } catch (PDOException $e) {
                        error_log("Analytics query error: " . $e->getMessage());
                        echo "<p>Error loading election analytics. Please try again later.</p>";
                    }
                    ?>
                </div>
            </div>

            <div class="content-section" id="audit-logs">
                <h3>Audit Logs</h3>
                <div class="audit-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT a.timestamp, a.action, a.details, a.ip_address, u.fname, u.mname, u.lname 
                                                       FROM auditlogs a 
                                                       JOIN users u ON a.user_id = u.user_id 
                                                       ORDER BY a.timestamp DESC LIMIT 50");
                                $stmt->execute();
                                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if ($logs) {
                                    foreach ($logs as $log) {
                                        $full_name = $log['fname'] . ' ' . ($log['mname'] ? $log['mname'] . ' ' : '') . $log['lname'];
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($log['timestamp']) . "</td>";
                                        echo "<td>" . htmlspecialchars($full_name) . "</td>";
                                        echo "<td>" . htmlspecialchars($log['action']) . "</td>";
                                        echo "<td>" . htmlspecialchars($log['details'] ?? 'N/A') . "</td>";
                                        echo "<td>" . htmlspecialchars($log['ip_address']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5'>No audit logs available.</td></tr>";
                                }
                            } catch (PDOException $e) {
                                error_log("Audit logs query error: " . $e->getMessage());
                                echo "<tr><td colspan='5'>Error loading audit logs. Please try again later.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="quick-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="election-rules.php">Election Rules</a></li>
                    <li><a href="contact.php">Support</a></li>
                </ul>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <p>© 2025 SmartUchaguzi | University of Dodoma</p>
        </div>
    </footer>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute due to inactivity.</p>
            <button id="extend-session">OK</button>
        </div>
    </div>

    <a href="#" class="back-to-top" id="back-to-top"><i class="fas fa-chevron-up"></i></a>

    <script>
        const links = document.querySelectorAll('.header .nav a');
        const sections = document.querySelectorAll('.content-section');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = link.getAttribute('data-section');
                sections.forEach(section => section.classList.remove('active'));
                document.getElementById(sectionId).classList.add('active');
                links.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });

        window.addEventListener('scroll', () => {
            const backToTop = document.getElementById('back-to-top');
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        const inactivityTimeout = <?php echo $inactivity_timeout; ?> * 1000;
        const maxSessionDuration = <?php echo $max_session_duration; ?> * 1000;
        const warningTime = <?php echo $warning_time; ?> * 1000;
        let lastActivity = Date.now();
        let sessionStart = <?php echo $_SESSION['start_time'] * 1000; ?>;

        const modal = document.getElementById('timeout-modal');
        const timeoutMessage = document.getElementById('timeout-message');
        const extendButton = document.getElementById('extend-session');

        function checkTimeouts() {
            const currentTime = Date.now();
            const inactiveTime = currentTime - lastActivity;
            const sessionTime = currentTime - sessionStart;

            if (sessionTime >= maxSessionDuration - warningTime && sessionTime < maxSessionDuration) {
                timeoutMessage.textContent = "Your session will expire in 1 minute.";
                modal.style.display = 'flex';
            } else if (sessionTime >= maxSessionDuration) {
                window.location.href = 'logout.php';
            }

            if (inactiveTime >= inactivityTimeout - warningTime && inactiveTime < inactivityTimeout) {
                timeoutMessage.textContent = "You will be logged out in 1 minute due to inactivity.";
                modal.style.display = 'flex';
            } else if (inactiveTime >= inactivityTimeout) {
                window.location.href = 'logout.php';
            }
        }

        document.addEventListener('mousemove', () => lastActivity = Date.now());
        document.addEventListener('keydown', () => lastActivity = Date.now());

        extendButton.addEventListener('click', () => {
            lastActivity = Date.now();
            modal.style.display = 'none';
        });

        setInterval(checkTimeouts, 1000);
    </script>
</body>
</html>
<?php
// Close the database connection
$pdo = null;
?>