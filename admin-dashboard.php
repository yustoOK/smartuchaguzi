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

// 2FA Verification
if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: 2fa.php');
    exit;
}

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
        :root {
            --primary-color: #f4a261;
            --secondary-color: #e76f51;
            --text-color: #2d3748;
            --bg-color: rgba(255, 255, 255, 0.95);
            --dark-bg-color: #1a202c;
            --dark-text-color: #e2e8f0;
            --sidebar-width: 250px;
        }

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
            color: var(--text-color);
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background: #1a202c;
            color: var(--dark-text-color);
        }

        body.dark-mode .dash-content,
        body.dark-mode .overview .card,
        body.dark-mode .management-section,
        body.dark-mode .upcoming-section,
        body.dark-mode .user-section,
        body.dark-mode .analytics-section,
        body.dark-mode .audit-section,
        body.dark-mode .fraud-section,
        body.dark-mode .sidebar {
            background: var(--dark-bg-color);
            color: var(--dark-text-color);
        }

        body.dark-mode .upcoming-table td,
        body.dark-mode .audit-table td,
        body.dark-mode .fraud-table td {
            background: #2d3748;
            color: var(--dark-text-color);
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(26, 60, 52, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
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
            width: 3rem;
            height: 3rem;
            margin-right: 1rem;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }

        .header .logo h1 {
            font-size: 1.5rem;
            color: #e6e6e6;
            font-weight: 600;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .header .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: #e6e6e6;
            cursor: pointer;
        }

        .header .user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header .user span {
            font-size: 1rem;
            color: #e6e6e6;
        }

        .header .user img {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }

        .header .user a,
        .header .user button {
            background: var(--primary-color);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .header .user a:hover,
        .header .user button:hover {
            background: var(--secondary-color);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100%;
            background: rgba(26, 60, 52, 0.9);
            padding-top: 5rem;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 900;
        }

        .sidebar .nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
        }

        .sidebar .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 1rem;
            padding: 0.75rem 1rem;
            position: relative;
            transition: color 0.3s ease, background 0.3s ease;
            border-radius: 6px;
        }

        .sidebar .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .sidebar .nav a.active {
            color: var(--primary-color);
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav a.active::after,
        .sidebar .nav a:hover::after {
            width: 100%;
        }

        .sidebar .nav a:hover {
            color: var(--primary-color);
            background: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 5rem 1rem 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .dash-content {
            max-width: 80rem;
            width: 95%;
            background: var(--bg-color);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
            transition: background-color 0.3s, color 0.3s;
        }

        .dash-content h2 {
            font-size: 2rem;
            color: #1a3c34;
            margin-bottom: 1.5rem;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, var(--primary-color));
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
            grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .overview .card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .overview .card:hover {
            transform: translateY(-0.3rem);
        }

        .overview .card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .overview .card .text {
            font-size: 1rem;
            color: #4a5568;
            font-weight: 500;
        }

        .overview .card .number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a3c34;
            margin-top: 0.3rem;
        }

        h3 {
            font-size: 1.5rem;
            color: #1a3c34;
            margin-bottom: 1rem;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, var(--primary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .management-section,
        .upcoming-section,
        .user-section,
        .analytics-section,
        .audit-section,
        .fraud-section {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-buttons button {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .action-buttons button:hover {
            background: var(--secondary-color);
        }

        .upcoming-table,
        .audit-table,
        .fraud-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .upcoming-table th,
        .upcoming-table td,
        .audit-table th,
        .audit-table td,
        .fraud-table th,
        .fraud-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
        }

        .upcoming-table th,
        .audit-table th,
        .fraud-table th {
            background: #e0e0e0;
            color: #1a3c34;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .upcoming-table td,
        .audit-table td,
        .fraud-table td {
            color: #4a5568;
            font-size: 0.9rem;
            background: #ffffff;
        }

        .upcoming-table tr:hover,
        .audit-table tr:hover,
        .fraud-table tr:hover {
            background: #f5f5f5;
        }

        .upcoming-table td a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .upcoming-table td a:hover {
            color: var(--secondary-color);
        }

        .analytics-filter {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .analytics-filter label {
            font-size: 1rem;
            color: #4a5568;
        }

        .analytics-filter select {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            background: #ffffff;
            color: var(--text-color);
            font-size: 1rem;
            max-width: 18rem;
        }

        .analytics-filter button {
            background: var(--primary-color);
            border: none;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .analytics-filter button:hover {
            background: var(--secondary-color);
        }

        .analytics-filter button:disabled {
            background: #e0e0e0;
            cursor: not-allowed;
        }

        .vote-analytics {
            margin-top: 1rem;
        }

        .vote-analytics h4 {
            font-size: 1.25rem;
            color: #1a3c34;
            margin-bottom: 1rem;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, var(--primary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .vote-analytics h5 {
            font-size: 1rem;
            color: var(--text-color);
            margin: 1rem 0 0.5rem;
            text-align: center;
        }

        .vote-analytics p {
            font-size: 0.9rem;
            color: #4a5568;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .vote-analytics canvas {
            max-width: 100%;
            margin: 1rem auto;
            background: #ffffff;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .error {
            color: var(--secondary-color);
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        footer {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 1rem;
            text-align: center;
            margin-top: 2rem;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        footer p {
            font-size: 0.9rem;
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
            background: var(--bg-color);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            max-width: 25rem;
            width: 90%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-content p {
            font-size: 1rem;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .modal-content button {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .modal-content button:hover {
            background: var(--secondary-color);
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .header .menu-toggle {
                display: block;
            }

            .dash-content {
                width: 98%;
                padding: 1.5rem;
            }

            .overview {
                grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .header .logo h1 {
                font-size: 1.2rem;
            }

            .dash-content {
                padding: 1rem;
            }

            .dash-content h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1.25rem;
            }

            .overview .card {
                padding: 1rem;
            }

            .upcoming-table th,
            .upcoming-table td,
            .audit-table th,
            .audit-table td,
            .fraud-table th,
            .fraud-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .analytics-filter select,
            .analytics-filter button {
                font-size: 0.9rem;
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .overview {
                grid-template-columns: 1fr;
            }

            .action-buttons button {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-bars menu-toggle"></i>
            <img src="./Uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="user">
            <span><?php echo $admin_name . ($college_name ? ' (' . $college_name . ')' : ''); ?></span>
            <img src="images/default.png" alt="Profile" onerror="this.src='images/general.png';">
            <button id="dark-mode-toggle"><i class="fas fa-moon"></i></button>
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <aside class="sidebar">
        <div class="nav">
            <a href="#" data-section="overview" class="active">Overview</a>
            <a href="#" data-section="management">Election Management</a>
            <a href="#" data-section="upcoming">Upcoming Elections</a>
            <a href="#" data-section="users">User Management</a>
            <a href="#" data-section="analytics">Analytics</a>
            <a href="#" data-section="audit">Audit Logs</a>
            <a href="#" data-section="fraud">Fraud Incidents</a>
        </div>
    </aside>

    <main class="main-content">
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
                        <div class="action-buttons">
                            <button onclick="exportAuditLogs()">Export Audit Logs</button>
                        </div>
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

                <div class="content-section" id="fraud">
                    <h3>Fraud Incidents</h3>
                    <div class="fraud-section">
                        <table class="fraud-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SELECT f.timestamp, f.details, f.ip_address, u.fname, u.mname, u.lname 
                                                           FROM frauddetectionlogs f 
                                                           LEFT JOIN users u ON f.user_id = u.user_id 
                                                           WHERE f.is_fraudulent = 1 
                                                           ORDER BY f.timestamp DESC LIMIT 50");
                                    $stmt->execute();
                                    $fraud_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if ($fraud_logs) {
                                        foreach ($fraud_logs as $log) {
                                            $full_name = $log['fname'] ? ($log['fname'] . ' ' . ($log['mname'] ? $log['mname'] . ' ' : '') . $log['lname']) : 'Unknown';
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($log['timestamp']) . "</td>";
                                            echo "<td>" . htmlspecialchars($full_name) . "</td>";
                                            echo "<td>" . htmlspecialchars($log['details'] ?? 'N/A') . "</td>";
                                            echo "<td>" . htmlspecialchars($log['ip_address'] ?? 'N/A') . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4'>No fraud incidents detected.</td></tr>";
                                    }
                                } catch (PDOException $e) {
                                    error_log("Fraud logs query error: " . $e->getMessage());
                                    echo "<tr><td colspan='4'>Error loading fraud incidents. Please try again later.</td></tr>";
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
    </main>

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
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');

            // Navigation
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const sectionId = link.getAttribute('data-section');
                    sections.forEach(section => section.classList.remove('active'));
                    navLinks.forEach(l => l.classList.remove('active'));
                    document.getElementById(sectionId).classList.add('active');
                    link.classList.add('active');
                    if (window.innerWidth <= 1024) {
                        sidebar.classList.remove('active');
                    }
                });
            });

            // Sidebar Toggle
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Analytics
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

                        const {
                            positions,
                            totalVotes
                        } = data;
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
                                        y: {
                                            beginAtZero: true
                                        }
                                    },
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: `${pos.name} Vote Distribution`,
                                            color: '#2d3748',
                                            font: {
                                                size: 14
                                            }
                                        },
                                        legend: {
                                            labels: {
                                                color: '#4a5568'
                                            }
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
                                        x: {
                                            ticks: {
                                                color: '#4a5568'
                                            }
                                        },
                                        y: {
                                            ticks: {
                                                color: '#4a5568'
                                            }
                                        }
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

            // Dark Mode
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            darkModeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                if (document.body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                }
            });

            // Export Audit Logs
            function exportAuditLogs() {
                fetch('api/export-audit-logs.php')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'audit_logs.csv';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                    })
                    .catch(error => {
                        console.error('Export error:', error);
                        alert('Failed to export audit logs.');
                    });
            }

            // Session Timeout
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
$pdo = null;
?>