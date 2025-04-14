<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Ensuring HTTPS
session_start();

// database connection
include 'db.php';

// Checking if user is logged in and has the 'observer' role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'observer') {
    header('Location: login.php');
    exit;
}

// Session timeout (30 minutes)
$timeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// CSRF token generation for actions
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch observer details
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT name FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$observer_name = htmlspecialchars($user['name'] ?? 'Observer');

// Fetch overview statistics
$total_elections = $db->query("SELECT COUNT(*) FROM Elections")->fetch_row()[0];
$total_candidates = $db->query("SELECT COUNT(*) FROM Candidates")->fetch_row()[0];
$total_votes = $db->query("SELECT COUNT(*) FROM Votes")->fetch_row()[0];
$total_anomalies = $db->query("SELECT COUNT(*) FROM FraudDetectionLogs WHERE is_fraudulent = 1")->fetch_row()[0];

// Handle election status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_election_status') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $election_id = filter_input(INPUT_POST, 'election_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

    if ($election_id && in_array($new_status, ['upcoming', 'ongoing', 'completed'])) {
        $stmt = $db->prepare("UPDATE Elections SET status = ? WHERE election_id = ?");
        $stmt->bind_param("si", $new_status, $election_id);
        if ($stmt->execute()) {
            // Log the action
            $stmt_log = $db->prepare("INSERT INTO AuditLogs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $action = "Election Status Updated";
            $details = "Election ID: $election_id, New Status: $new_status";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt_log->bind_param("isss", $user_id, $action, $details, $ip_address);
            $stmt_log->execute();
        }
        $stmt->close();
    }
    header("Location: observer-dashboard.php");
    exit;
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
    <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
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
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: gradientShift 5s infinite alternate;
        }

        @keyframes gradientShift {
            0% { background: rgba(26, 60, 52, 0.9); }
            100% { background: rgba(44, 82, 76, 0.9); }
        }

        .header .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
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
            position: relative;
            overflow: hidden;
        }

        .header .nav a.active {
            background: #f4a261;
            color: #fff;
        }

        .header .nav a:hover {
            background: #f4a261;
            color: #fff;
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }

        .header .nav a::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .header .nav a:hover::before {
            width: 200px;
            height: 200px;
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
            object-fit: cover;
        }

        .header .user a {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .header .user a:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }

        .header .user a::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .header .user a:hover::before {
            width: 200px;
            height: 200px;
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

        .election-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .election-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(244, 162, 97, 0.3);
        }

        .election-card p {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 10px;
        }

        .election-card .status {
            font-weight: 500;
            color: #1a3c34;
        }

        .election-card form {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .election-card select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #e6e6e6;
            background: rgba(255, 255, 255, 0.1);
            color: #2d3748;
        }

        .election-card button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            border: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .election-card button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }

        .election-card button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .election-card button:hover::before {
            width: 200px;
            height: 200px;
        }

        .vote-section table,
        .anomaly-section table {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-collapse: collapse;
            margin-top: 20px;
        }

        .vote-section th,
        .anomaly-section th {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 12px;
            font-size: 16px;
        }

        .vote-section td,
        .anomaly-section td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #4a5568;
            font-size: 14px;
        }

        .vote-section .hash,
        .anomaly-section .hash {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.05);
            padding: 5px;
            border-radius: 4px;
            word-break: break-all;
        }

        .anomaly-section .score {
            color: #e74c3c;
            font-weight: 500;
        }

        .report-section {
            text-align: center;
        }

        .report-section button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            border: none;
            color: #fff;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .report-section button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .report-section button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .report-section button:hover::before {
            width: 300px;
            height: 300px;
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
            box-shadow: 0 4px 10px rgba(244, 162, 97, 0.5);
        }

        footer {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            padding: 40px 20px;
            text-align: center;
            margin-top: 40px;
        }

        footer .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        footer p {
            font-size: 14px;
        }

        footer .footer-links {
            display: flex;
            gap: 20px;
        }

        footer .footer-links a {
            color: #f4a261;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        footer .footer-links a:hover {
            color: #e76f51;
        }

        footer .social-icons {
            display: flex;
            gap: 15px;
        }

        footer .social-icons a {
            color: #e6e6e6;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        footer .social-icons a:hover {
            color: #f4a261;
            transform: scale(1.1);
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

            .overview,
            .election-cards {
                grid-template-columns: 1fr;
            }

            .dash-content {
                padding: 20px;
            }

            footer .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .header .logo h1 {
                font-size: 18px;
            }

            .header .logo img {
                width: 35px;
                height: 35px;
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

            .vote-section td,
            .anomaly-section td {
                font-size: 12px;
            }

            .report-section button {
                padding: 10px 20px;
                font-size: 14px;
            }

            footer .social-icons a {
                font-size: 14px;
            }

            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 20px;
                bottom: 20px;
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-container">
            <div class="logo">
                <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
                <h1>SmartUchaguzi</h1>
            </div>
        </div>
        <div class="nav">
            <a href="#" data-section="overview" class="active">Overview</a>
            <a href="#" data-section="elections">Elections</a>
            <a href="#" data-section="vote-feed">Vote Feed</a>
            <a href="#" data-section="anomalies">Fraud Detection</a>
            <a href="#" data-section="reports">Reports</a>
        </div>
        <div class="user">
            <span><?php echo $observer_name; ?></span>
            <img src="./uploads/observer-placeholder.jpg" alt="Observer" onerror="this.src='./uploads/default-user.jpg';">
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
                        <span class="text">Anomalies Detected</span>
                        <span class="number"><?php echo $total_anomalies; ?></span>
                    </div>
                </div>
            </div>

            <div class="content-section" id="elections">
                <h3>Manage Elections</h3>
                <div class="election-cards">
                    <?php
                    $elections = $db->query("SELECT election_id, name, date, type, status, blockchain_hash FROM Elections ORDER BY date DESC");
                    while ($election = $elections->fetch_assoc()) {
                        echo "<div class='election-card'>";
                        echo "<h3>" . htmlspecialchars($election['name']) . "</h3>";
                        echo "<p><strong>Type:</strong> " . htmlspecialchars($election['type']) . "</p>";
                        echo "<p><strong>Date:</strong> " . htmlspecialchars($election['date']) . "</p>";
                        echo "<p><strong>Status:</strong> <span class='status'>" . htmlspecialchars($election['status']) . "</span></p>";
                        echo "<p><strong>Blockchain Hash:</strong> <span class='hash'>" . htmlspecialchars($election['blockchain_hash'] ?? 'N/A') . "</span></p>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='action' value='update_election_status'>";
                        echo "<input type='hidden' name='election_id' value='" . $election['election_id'] . "'>";
                        echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
                        echo "<select name='new_status'>";
                        echo "<option value='upcoming'" . ($election['status'] == 'upcoming' ? ' selected' : '') . ">Upcoming</option>";
                        echo "<option value='ongoing'" . ($election['status'] == 'ongoing' ? ' selected' : '') . ">Ongoing</option>";
                        echo "<option value='completed'" . ($election['status'] == 'completed' ? ' selected' : '') . ">Completed</option>";
                        echo "</select>";
                        echo "<button type='submit'>Update Status</button>";
                        echo "</form>";
                        echo "</div>";
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
                                <th>Candidate</th>
                                <th>Type</th>
                                <th>Blockchain Hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $votes = $db->query("SELECT v.vote_id, v.vote_timestamp, v.blockchain_hash, e.name AS election_name, c.position, u.name AS candidate_name, e.type
                                                 FROM Votes v
                                                 JOIN Elections e ON v.election_id = e.election_id
                                                 JOIN Candidates c ON v.candidate_id = c.candidate_id
                                                 JOIN Users u ON c.user_id = u.user_id
                                                 ORDER BY v.vote_timestamp DESC LIMIT 50");
                            if ($votes->num_rows > 0) {
                                while ($vote = $votes->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($vote['vote_timestamp']) . "</td>";
                                    echo "<td>" . htmlspecialchars($vote['election_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($vote['candidate_name']) . " (" . htmlspecialchars($vote['position']) . ")</td>";
                                    echo "<td>" . htmlspecialchars($vote['type']) . "</td>";
                                    echo "<td class='hash'>" . htmlspecialchars($vote['blockchain_hash']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No votes recorded yet.</td></tr>";
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
                                <th>Candidate</th>
                                <th>Type</th>
                                <th>Blockchain Hash</th>
                                <th>Anomaly Score</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $anomalies = $db->query("SELECT f.log_id, f.timestamp, f.anomaly_score, f.details, f.is_fraudulent, v.blockchain_hash, e.name AS election_name, c.position, u.name AS candidate_name, e.type
                                                     FROM FraudDetectionLogs f
                                                     JOIN Votes v ON f.vote_id = v.vote_id
                                                     JOIN Elections e ON v.election_id = e.election_id
                                                     JOIN Candidates c ON v.candidate_id = c.candidate_id
                                                     JOIN Users u ON c.user_id = u.user_id
                                                     WHERE f.is_fraudulent = 1
                                                     ORDER BY f.timestamp DESC");
                            if ($anomalies->num_rows > 0) {
                                while ($anomaly = $anomalies->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($anomaly['timestamp']) . "</td>";
                                    echo "<td>" . htmlspecialchars($anomaly['election_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($anomaly['candidate_name']) . " (" . htmlspecialchars($anomaly['position']) . ")</td>";
                                    echo "<td>" . htmlspecialchars($anomaly['type']) . "</td>";
                                    echo "<td class='hash'>" . htmlspecialchars($anomaly['blockchain_hash']) . "</td>";
                                    echo "<td class='score'>" . htmlspecialchars($anomaly['anomaly_score']) . "</td>";
                                    echo "<td>" . htmlspecialchars($anomaly['details'] ?? 'N/A') . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7'>No anomalies detected.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section" id="reports">
                <h3>Generate Reports</h3>
                <div class="report-section">
                    <form method="POST" action="generate-report.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit">Download Election Report (PDF)</button>
                    </form>
                </div>
            </div>

            <div class="quick-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="observer-profile.php">My Profile</a></li>
                    <li><a href="election-rules.php">Election Rules</a></li>
                    <li><a href="contact-us.html">Support</a></li>
                </ul>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <p>© 2025 SmartUchaguzi | Group 4, University of Dodoma</p>
            <div class="footer-links">
                <a href="index.html">Home</a>
                <a href="about-us.html">About Us</a>
                <a href="contact-us.html">Contact Us</a>
                <a href="privacy-policy.html">Privacy Policy</a>
            </div>
            <div class="social-icons">
                <a href="https://instagram.com/smartuchaguzi" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                <a href="https://facebook.com/smartuchaguzi" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/SmartUchaguzi" target="_blank" rel="noopener"><i class="fab fa-x-twitter"></i></a>
                <a href="https://wa.me/255719950708" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

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

        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('back-to-top');
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
    </script>
</body>
</html>