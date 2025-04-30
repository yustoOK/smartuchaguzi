<?php
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
    die("Unable to connect to the database");
}

$required_role = 'admin';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
    error_log("Session validation failed: user_id or role not set or invalid. Session: " . print_r($_SESSION, true));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Please log in as an admin.'));
    exit;
}

error_log("Session after validation: user_id=" . ($_SESSION['user_id'] ?? 'unset') . 
          ", role=" . ($_SESSION['role'] ?? 'unset'));

if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

$inactivity_timeout = 15 * 60; // 15 minutes
$max_session_duration = 12 * 60 * 60; // 12 hours
$warning_time = 60;

if (!isset($_SESSION['start_time'])) {
    $_SESSION['start_time'] = time();
}
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= $max_session_duration) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired. Please log in again.'));
    exit;
}

$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= $inactivity_timeout) {
    error_log("Session expired due to inactivity: $inactive_time seconds.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.'));
    exit;
}
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT fname, mname, lname, college_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception("No user found for user_id: " . $user_id);
    }
    $admin_name = htmlspecialchars($user['fname'] . ' ' . ($user['mname'] ? $user['mname'] . ' ' : '') . $user['lname']);
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('error logging in'));
    exit;
}

$college_name = '';
if ($user['college_id']) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM colleges WHERE college_id = ?");
        $stmt->execute([$user['college_id']]);
        $college_name = $stmt->fetchColumn() ?: 'Unknown';
    } catch (PDOException $e) {
        error_log("College query failed: " . $e->getMessage());
        $college_name = 'Unknown';
    }
}


try {
    $stmt = $pdo->prepare("INSERT INTO auditlogs (user_id, action, details, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, 'Admin Dashboard Access', 'User accessed admin dashboard', $_SERVER['REMOTE_ADDR']]);
} catch (PDOException $e) {
    error_log("Audit log insertion failed: " . $e->getMessage());
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

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM elections WHERE end_time > NOW()");
    $stmt->execute();
    $total_active_elections = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Active elections count query error: " . $e->getMessage());
    $total_active_elections = "N/A";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard | SmartUchaguzi</title>
    <link rel="icon" href="./Uploads/Vote.jpeg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            min-height: 100vh;
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
        }
        .header .logo {
            display: flex;
            align-items: center;
        }
        .header .logo img {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            border-radius: 50%;
            border: 2px solid #f4a261;
        }
        .header .logo h1 {
            font-size: 24px;
            color: #e6e6e6;
            font-weight: 600;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .header .nav {
            display: flex;
            gap: 20px;
        }
        .header .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 20px;
            position: relative;
            transition: color 0.3s ease;
        }
        .header .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #f4a261;
            transition: width 0.3s ease;
        }
        .header .nav a.active {
            color: #f4a261;
        }
        .header .nav a.active::after {
            width: 100%;
        }
        .header .nav a:hover::after {
            width: 100%;
        }
        .header .nav a:hover {
            color: #f4a261;
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
            background: #f4a261;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .header .user a:hover {
            background: #e76f51;
        }
        .dashboard {
            padding: 100px 20px 40px;
            min-height: calc(100vh - 70px);
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .dash-content {
            max-width: 1280px;
            width: 90%;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .dash-content h2 {
            font-size: 32px;
            color: #1a3c34;
            margin-bottom: 30px;
            text-align: center;
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
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .overview .card:hover {
            transform: translateY(-5px);
        }
        .overview .card i {
            font-size: 30px;
            color: #f4a261;
            margin-bottom: 10px;
        }
        .overview .card .text {
            font-size: 16px;
            color: #4a5568;
            font-weight: 500;
        }
        .overview .card .number {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
            margin-top: 5px;
        }
        h3 {
            font-size: 24px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .management-section,
        .upcoming-section,
        .user-section,
        .analytics-section,
        .audit-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .action-buttons button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .action-buttons button:hover {
            background: #e76f51;
        }
        .upcoming-table,
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .upcoming-table th,
        .upcoming-table td,
        .audit-table th,
        .audit-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
        }
        .upcoming-table th,
        .audit-table th {
            background: #e0e0e0;
            color: #1a3c34;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
        }
        .upcoming-table td,
        .audit-table td {
            color: #4a5568;
            font-size: 14px;
            background: #ffffff;
        }
        .upcoming-table tr:hover,
        .audit-table tr:hover {
            background: #f5f5f5;
        }
        .upcoming-table td a {
            color: #f4a261;
            text-decoration: none;
            font-weight: 500;
        }
        .upcoming-table td a:hover {
            color: #e76f51;
        }
        .analytics-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .analytics-filter label {
            font-size: 16px;
            color: #4a5568;
        }
        .analytics-filter select {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            background: #ffffff;
            color: #2d3748;
            font-size: 16px;
            max-width: 300px;
        }
        .analytics-filter button {
            background: #f4a261;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .analytics-filter button:hover {
            background: #e76f51;
        }
        .analytics-filter button:disabled {
            background: #e0e0e0;
            cursor: not-allowed;
        }
        .vote-analytics {
            margin-top: 20px;
        }
        .vote-analytics h4 {
            font-size: 20px;
            color: #1a3c34;
            margin-bottom: 15px;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .vote-analytics h5 {
            font-size: 16px;
            color: #2d3748;
            margin: 15px 0 10px;
            text-align: center;
        }
        .vote-analytics p {
            font-size: 14px;
            color: #4a5568;
            text-align: center;
            margin-bottom: 10px;
        }
        .vote-analytics canvas {
            max-width: 100%;
            margin: 20px auto;
            background: #ffffff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .error {
            color: #e76f51;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }
        footer {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }
        footer p {
            font-size: 14px;
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
            background: rgba(255, 255, 255, 0.95);
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
            transition: background 0.3s ease;
        }
        .modal-content button:hover {
            background: #e76f51;
        }
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            .header .nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            .dash-content {
                padding: 20px;
            }
            .overview {
                grid-template-columns: 1fr;
            }
            .dash-content h2 {
                font-size: 24px;
            }
            h3 {
                font-size: 20px;
            }
            .upcoming-table th,
            .upcoming-table td,
            .audit-table th,
            .audit-table td {
                padding: 8px 10px;
                font-size: 12px;
            }
            .analytics-filter select,
            .analytics-filter button {
                font-size: 14px;
                padding: 8px 12px;
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
            <a href="#" data-section="management">Election Management</a>
            <a href="#" data-section="upcoming">Upcoming Elections</a>
            <a href="#" data-section="users">User Management</a>
            <a href="#" data-section="analytics">Analytics</a>
            <a href="#" data-section="audit">Audit Logs</a>
        </div>
        <div class="user">
            <span><?php echo $admin_name . ($college_name ? ' (' . $college_name . ')' : ''); ?></span>
            <img src="images/default.png" alt="Profile" onerror="this.src='images/general.png';">
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <section class="dashboard">
        <div class="dash-content">

            <div class="content-section active" id="overview">
                <div class="overview">
                    <div class="card">
                        <i class="fas fa-users"></i>
                        <span class="text">Total Candidates</span>
                        <span class="number"><?php echo $total_candidates; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-vote-yea"></i>
                        <span class="text">Total Votes</span>
                        <span class="number"><?php echo $total_votes; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-clock"></i>
                        <span class="text">Active Elections</span>
                        <span class="number"><?php echo $total_active_elections; ?></span>
                    </div>
                    <div class="card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="text">Fraud Incidents</span>
                        <span class="number"><?php echo $total_anomalies; ?></span>
                    </div>
                </div>
            </div>

            <div class="content-section" id="management">
                <h3>Election Management</h3>
                <div class="management-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='admin-operations/add-election.php'">Add Election</button>
                        <button onclick="window.location.href='admin-operations/edit-election.php'">Edit Election</button>
                        <button onclick="window.location.href='admin-operations/manage-candidates.php'">Manage Candidates</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="upcoming">
                <h3>Upcoming Elections</h3>
                <div class="upcoming-section">
                    <table class="upcoming-table">
                        <thead>
                            <tr>
                                <th>Association</th>
                                <th>College</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT e.id, e.association, e.start_time, e.end_time, c.name AS college_name 
                                                       FROM elections e 
                                                       LEFT JOIN colleges c ON e.college_id = c.college_id 
                                                       WHERE e.start_time > NOW() 
                                                       ORDER BY e.start_time");
                                $stmt->execute();
                                $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if ($elections) {
                                    foreach ($elections as $election) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($election['association']) . "</td>";
                                        echo "<td>" . ($election['college_name'] ? htmlspecialchars($election['college_name']) : 'University-Wide') . "</td>";
                                        echo "<td>" . htmlspecialchars($election['start_time']) . "</td>";
                                        echo "<td>" . htmlspecialchars($election['end_time']) . "</td>";
                                        echo "<td><a href='admin-operations/edit-election.php?id={$election['id']}'>Edit</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5'>No upcoming elections.</td></tr>";
                                }
                            } catch (PDOException $e) {
                                error_log("Upcoming elections query error: " . $e->getMessage());
                                echo "<tr><td colspan='5'>Error loading upcoming elections. Please try again later.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section" id="users">
                <h3>User Management</h3>
                <div class="user-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='admin-operations/add-user.php'">Add User</button>
                        <button onclick="window.location.href='admin-operations/edit-user.php'">Edit User</button>
                        <button onclick="window.location.href='admin-operations/assign-observer.php'">Assign Observer</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="analytics">
                <h3>Election Analytics</h3>
                <div class="analytics-section">
                    <div class="analytics-filter">
                        <label for="election-select">Select Election:</label>
                        <select id="election-select">
                            <option value="">All Elections</option>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT id, CONCAT(association, ' - ', start_time) AS name FROM elections ORDER BY start_time DESC");
                                $stmt->execute();
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                error_log("Election select query error: " . $e->getMessage());
                                echo "<option value=''>Error loading elections</option>";
                            }
                            ?>
                        </select>
                        <form method="POST" action="api/generate-report.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="election_id" id="report-election-id">
                            <button type="submit" id="download-report" disabled>Download Report</button>
                        </form>
                    </div>
                    <div id="vote-analytics" class="vote-analytics">
                        <p>Select an election to view analytics.</p>
                    </div>
                </div>
            </div>

            <div class="content-section" id="audit">
                <h3>Audit Logs</h3>
                <div class="audit-section">
                    <table class="audit-table">
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
        </div>
    </section>

    <footer>
        <p>© 2025 SmartUchaguzi | University of Dodoma</p>
    </footer>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute.</p>
            <button id="extend-session">Extend Session</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('.nav a[data-section]');
            const sections = document.querySelectorAll('.content-section');
            const electionSelect = document.getElementById('election-select');
            const voteAnalytics = document.getElementById('vote-analytics');
            const downloadButton = document.getElementById('download-report');
            const reportElectionId = document.getElementById('report-election-id');

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const sectionId = link.getAttribute('data-section');
                    sections.forEach(section => section.classList.remove('active'));
                    navLinks.forEach(l => l.classList.remove('active'));
                    document.getElementById(sectionId).classList.add('active');
                    link.classList.add('active');
                });
            });

            electionSelect.addEventListener('change', () => {
                const electionId = electionSelect.value;
                voteAnalytics.innerHTML = '<p>Loading analytics...</p>';
                downloadButton.disabled = !electionId;
                reportElectionId.value = electionId;

                if (!electionId) {
                    voteAnalytics.innerHTML = '<p>Select an election to view analytics.</p>';
                    return;
                }

                fetch(`api/vote-analytics.php?election_id=${electionId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            voteAnalytics.innerHTML = `<p class="error">${data.error}</p>`;
                            return;
                        }

                        const { positions, totalVotes } = data;
                        let html = '<h4>Vote Analytics</h4>';
                        positions.forEach(pos => {
                            html += `
                                <div>
                                    <h5>${pos.name}</h5>
                                    <canvas id="chart-${pos.id}"></canvas>
                                    <p>Total Votes: ${pos.totalVotes}</p>
                                    <p>Winner: ${pos.winner || 'None'}</p>
                                    <p>Blockchain Hash: ${pos.blockchain_hash || 'N/A'}</p>
                                </div>
                            `;
                        });

                        voteAnalytics.innerHTML = html;

                        positions.forEach(pos => {
                            const ctx = document.getElementById(`chart-${pos.id}`).getContext('2d');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: pos.candidates.map(c => c.name),
                                    datasets: [{
                                        label: 'Votes',
                                        data: pos.candidates.map(c => c.votes),
                                        backgroundColor: '#f4a261',
                                        borderColor: '#e76f51',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: { beginAtZero: true }
                                    },
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: `${pos.name} Vote Distribution`,
                                            color: '#2d3748',
                                            font: { size: 14 }
                                        },
                                        legend: {
                                            labels: { color: '#4a5568' }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: context => {
                                                    const votes = context.parsed.y;
                                                    const percentage = totalVotes ? ((votes / totalVotes) * 100).toFixed(2) : 0;
                                                    return `${votes} votes (${percentage}%)`;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { ticks: { color: '#4a5568' } },
                                        y: { ticks: { color: '#4a5568' } }
                                    }
                                }
                            });
                        });
                    })
                    .catch(error => {
                        voteAnalytics.innerHTML = '<p class="error">Failed to load analytics. Please try again later.</p>';
                        console.error('Fetch error:', error);
                    });
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

            document.addEventListener('mousemove', () => {
                lastActivity = Date.now();
            });
            document.addEventListener('keydown', () => {
                lastActivity = Date.now();
            });

            extendButton.addEventListener('click', () => {
                lastActivity = Date.now();
                modal.style.display = 'none';
            });

            setInterval(checkTimeouts, 1000);
        });
    </script>
</body>
</html>
<?php
// Closing the database connection
$pdo = null;
?>