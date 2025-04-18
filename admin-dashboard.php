<?php
/*
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Session validation failed: user_id or role not set. Session: " . print_r($_SESSION, true));
    header('Location: login.php?error=' . urlencode('Please log in to access the admin dashboard.'));
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch. Session: " . print_r($_SESSION, true));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

$inactivity_timeout = 5 * 60;
$max_session_duration = 30 * 60;
$warning_time = 60;

if (!isset($_SESSION['start_time'])) {
    error_log("Session start_time not set. Initializing now.");
    $_SESSION['start_time'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    error_log("Session last_activity not set. Initializing now.");
    $_SESSION['last_activity'] = time();
}

$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= $max_session_duration) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to maximum duration. Please log in again.'));
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

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT fname, college FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("User not found for ID: $user_id");
        session_unset();
        session_destroy();
        header('Location: login.php?error=' . urlencode('User not found. Please log in again.'));
        exit;
    }
} catch (PDOException $e) {
    error_log("User query failed: " . $e->getMessage());
    header('Location: login.php?error=' . urlencode('Failed to fetch user data. Please try again.'));
    exit;
}

$college_name = '';
if ($user['college']) {
    try {
        $college_stmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
        $college_stmt->execute([$user['college']]);
        $college_result = $college_stmt->fetchColumn();
        $college_name = $college_result ?: '';
    } catch (PDOException $e) {
        error_log("College query failed: " . $e->getMessage());
        $college_name = 'Unknown';
    }
}

$profile_picture = 'images/default.png';
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Dashboard</title>
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
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #2d3748;
            line-height: 1.6;
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
            animation: gradientShift 5s infinite alternate;
        }

        @keyframes gradientShift {
            0% {
                background: rgba(26, 60, 52, 0.9);
            }

            100% {
                background: rgba(44, 82, 76, 0.9);
            }
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
            font-weight: 500;
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

        .header .nav a:hover::after,
        .header .nav a.active::after {
            width: 100%;
        }

        .header .nav a.active {
            color: #f4a261;
        }

        .header .nav a:hover {
            color: #f4a261;
        }

        .header .user {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header .user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #f4a261;
            cursor: pointer;
        }

        .header .user .dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            width: 200px;
            z-index: 1000;
        }

        .header .user .dropdown.active {
            display: flex;
        }

        .header .user .dropdown a {
            color: #e6e6e6;
            padding: 10px 20px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s ease;
            cursor: pointer;
        }

        .header .user .dropdown a:hover {
            background: rgba(244, 162, 97, 0.3);
        }

        .header .user .logout-link {
            background: #f4a261;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .header .user .logout-link:hover {
            background: #e76f51;
            box-shadow: 0 0 10px rgba(231, 111, 81, 0.5);
        }

        .dashboard {
            padding: 100px 20px 20px;
        }

        .dash-content {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .dash-content h2 {
            font-size: 28px;
            color: #1a3c34;
            margin-bottom: 30px;
            text-align: center;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
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
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
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

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .management-section,
        .upcoming-section,
        .user-section,
        .audit-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .management-section h3,
        .upcoming-section h3,
        .user-section h3,
        .audit-section h3 {
            font-size: 22px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
        }

        .management-section .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .management-section button,
        .upcoming-section button,
        .user-section button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 500;
        }

        .management-section button:hover,
        .upcoming-section button:hover,
        .user-section button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .upcoming-section form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 500px;
            margin: 20px auto;
        }

        .upcoming-section input,
        .upcoming-section textarea {
            padding: 10px;
            border: 1px solid #e8ecef;
            border-radius: 6px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.1);
            color: #2d3748;
        }

        .audit-section .log {
            padding: 10px;
            border-bottom: 1px solid #e8ecef;
            font-size: 14px;
            color: #2d3748;
        }

        .quick-links {
            margin-top: 40px;
            text-align: center;
        }

        .quick-links h3 {
            font-size: 22px;
            color: #1a3c34;
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
                gap: 10px;
            }

            .header .nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }

            .overview {
                grid-template-columns: 1fr;
            }

            .header .user .dropdown {
                width: 150px;
                top: 120px;
            }
        }

        @media (max-width: 480px) {
            .header .logo h1 {
                font-size: 20px;
            }

            .header .nav a {
                padding: 8px 14px;
                font-size: 14px;
            }

            .header .user img {
                width: 30px;
                height: 30px;
            }
        }

        .audit-section {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .audit-table th,
        .audit-table td {
            padding: 10px;
            border: 1px solid #e8ecef;
            text-align: left;
        }

        .audit-table th {
            background: #f4a261;
            color: #fff;
        }

        .audit-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1);
        }

        .error {
            color: #e76f51;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .no-logs {
            color: #2d3748;
            font-size: 16px;
            text-align: center;
            padding: 20px;
        }

        .analytics-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
        }

        .analytics-filter label {
            font-weight: 500;
            color: #2d3748;
        }

        .analytics-filter select {
            padding: 8px;
            border: 1px solid #e8ecef;
            border-radius: 6px;
            font-size: 16px;
            min-width: 200px;
        }

        .analytics-filter button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        .analytics-filter button:hover {
            background: #e76f51;
        }

        .overview {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 30%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card i {
            font-size: 24px;
            color: #f4a261;
            margin-bottom: 10px;
        }

        .card .text {
            display: block;
            font-size: 16px;
            color: #2d3748;
        }

        .card .number {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
        }

        .vote-analytics {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .vote-analytics canvas {
            max-width: 100%;
            margin: 20px auto;
        }

        .vote-analytics .error {
            color: #e76f51;
            font-size: 16px;
        }

        .analytics-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
        }

        .analytics-filter label {
            font-weight: 500;
            color: #2d3748;
        }

        .analytics-filter select {
            padding: 8px;
            border: 1px solid #e8ecef;
            border-radius: 6px;
            font-size: 16px;
            min-width: 200px;
        }

        .analytics-filter button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        .analytics-filter button:hover {
            background: #e76f51;
        }

        .overview {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 30%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card i {
            font-size: 24px;
            color: #f4a261;
            margin-bottom: 10px;
        }

        .card .text {
            display: block;
            font-size: 16px;
            color: #2d3748;
        }

        .card .number {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
        }

        .vote-analytics {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .vote-analytics canvas {
            max-width: 100%;
            margin: 20px auto;
        }

        .vote-analytics .error {
            color: #e76f51;
            font-size: 16px;
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
            <a href="#" data-section="management" class="active">Management</a>
            <a href="./api/update-upcoming.php" data-section="upcoming">Upcoming Elections</a>
            <a href="#" data-section="users">Users</a>
            <a href="#" data-section="analytics">Analytics</a>
            <a href="#" data-section="audit">Audit Log</a>
        </div>
        <div class="user">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic">
            <div class="dropdown" id="user-dropdown">
                <span style="color: #e6e6e6; padding: 10px 20px;"><?php echo htmlspecialchars($user['fname'] ?? 'Admin') . ($college_name ? ' (' . htmlspecialchars($college_name) . ')' : ''); ?></span>
                <a href="admin-profile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </header>

    <section class="dashboard">
        <div class="dash-content">
            <h2>Admin Dashboard</h2>

            <div class="content-section active" id="management">
                <h3>Election Management</h3>
                <div class="management-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='./admin-operations/add-election.php'">Add New Election</button>
                        <button onclick="window.location.href='./admin-operations/edit-election.php'">Edit Existing Election</button>
                        <button onclick="window.location.href='./admin-operations/manage-candidates.php'">Manage Candidates</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="upcoming">
                <h3>Upcoming Elections</h3>
                <div class="upcoming-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='./api/update-upcoming.php'">Manage Upcoming Elections</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="users">
                <h3>User Management</h3>
                <div class="user-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='./admin-operations/add-user.php'">Add New User</button>
                        <button onclick="window.location.href='./admin-operations/edit-user.php'">Edit User</button>
                        <button onclick="window.location.href='./admin-operations/assign-observer.php'">Assign Observer</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="analytics">
                <h3>Election Analytics</h3>
                <div class="analytics-filter">
                    <label for="election-select">Select Election:</label>
                    <select id="election-select">
                        <option value="">All Elections</option>
                        <?php
                        include 'db.php';
                        try {
                            $result = $db->query("SELECT id, CONCAT(association, ' - ', start_time) AS name FROM elections ORDER BY start_time DESC");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                            }
                            $result->free();
                        } catch (Exception $e) {
                            error_log("Election select query failed: " . $e->getMessage());
                            echo "<option value=''>Failed to load elections</option>";
                        }
                        ?>
                    </select>
                    <button id="download-report">Download Report</button>
                </div>
                <div class="overview">
                    <div class="card">
                        <i class="fas fa-users"></i>
                        <span class="text">Total Candidates</span>
                        <span class="number">
                            <?php
                            try {
                                $result = $db->query("SELECT COUNT(*) AS count FROM candidates");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                $result->free();
                            } catch (Exception $e) {
                                error_log("Candidates count query failed: " . $e->getMessage());
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="card">
                        <i class="fas fa-vote-yea"></i>
                        <span class="text">Total Votes</span>
                        <span class="number">
                            <?php
                            try {
                                $result = $db->query("SELECT COUNT(*) AS count FROM votes");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                $result->free();
                            } catch (Exception $e) {
                                error_log("Votes count query failed: " . $e->getMessage());
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="card">
                        <i class="fas fa-clock"></i>
                        <span class="text">Active Elections</span>
                        <span class="number">
                            <?php
                            try {
                                $result = $db->query("SELECT COUNT(*) AS count FROM elections WHERE end_time > NOW()");
                                $row = $result->fetch_assoc();
                                echo $row['count'];
                                $result->free();
                            } catch (Exception $e) {
                                error_log("Elections count query failed: " . $e->getMessage());
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div id="vote-analytics" class="vote-analytics">
                    <p>Select an election to view detailed analytics.</p>
                </div>
            </div>

            <div class="content-section" id="audit">
                <h3>Audit Log</h3>
                <div class="audit-section">
                    <?php
                    include 'db.php';
                    $logs = [];
                    $error_message = '';

                    try {
                        $result = $db->query(
                            "SELECT action, timestamp 
                FROM auditlogs 
                ORDER BY timestamp DESC 
                LIMIT 10"
                        );
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $logs[] = $row;
                            }
                            $result->free();
                        } else {
                            error_log("Audit log query failed: " . $db->error);
                            $error_message = "Failed to load audit logs due to a database error.";
                        }
                    } catch (Exception $e) {
                        error_log("Audit log query failed: " . $e->getMessage());
                        $error_message = "Failed to load audit logs due to a server error.";
                    }
                    ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php elseif (empty($logs)): ?>
                        <div class="no-logs">No recent activity.</div>
                    <?php else: ?>
                        <table class="audit-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($log['timestamp']))); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute due to inactivity.</p>
            <button id="extend-session">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const electionSelect = document.getElementById('election-select');
            const voteAnalytics = document.getElementById('vote-analytics');
            const downloadButton = document.getElementById('download-report');

            const navLinks = document.querySelectorAll('.nav a[data-section]');
            const sections = document.querySelectorAll('.content-section');

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (link.getAttribute('href') !== '#') {
                        return;
                    }
                    e.preventDefault();
                    const sectionId = link.getAttribute('data-section');
                    sections.forEach(section => section.classList.remove('active'));
                    const targetSection = document.getElementById(sectionId);
                    if (targetSection) {
                        targetSection.classList.add('active');
                    }
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                });
            });

            const defaultLink = document.querySelector('.nav a.active');
            if (defaultLink && defaultLink.getAttribute('href') === '#') {
                const defaultSectionId = defaultLink.getAttribute('data-section');
                document.getElementById(defaultSectionId).classList.add('active');
            }

            const profilePic = document.getElementById('profile-pic');
            const dropdown = document.getElementById('user-dropdown');
            profilePic.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (!dropdown.contains(e.target) && e.target !== profilePic) {
                    dropdown.classList.remove('active');
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
                    timeoutMessage.textContent = "Your session will expire in 1 minute due to maximum duration.";
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

            electionSelect.addEventListener('change', () => {
                const electionId = electionSelect.value;
                voteAnalytics.innerHTML = '<p>Loading analytics...</p>';

                fetch(`./api/vote-analytics.php?election_id=${electionId}`)
                    .then(response => response.json())
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
                            <p>Winner: ${pos.winner ? pos.winner : 'None'}</p>
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
                                            text: `${pos.name} Vote Distribution`
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: (context) => {
                                                    const votes = context.parsed.y;
                                                    const percentage = totalVotes ? ((votes / totalVotes) * 100).toFixed(2) : 0;
                                                    return `${votes} votes (${percentage}%)`;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    })
                    .catch(error => {
                        voteAnalytics.innerHTML = '<p class="error">Failed to load analytics.</p>';
                        console.error('Fetch error:', error);
                    });
            });

            downloadButton.addEventListener('click', () => {
                const electionId = electionSelect.value;
                if (!electionId) {
                    alert('Please select an election to download the report.');
                    return;
                }
                window.location.href = `./api/generate-report.php?election_id=${electionId}`;
            });
        });
    </script>
</body>

</html>