<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once 'config.php';

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

$inactivity_timeout = 30 * 60; //30 minutes
$max_session_duration = 3 * 60 * 60; // 3 hours
$warning_time = 28;

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
        $stmt->execute($user['college_id']);
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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blockchainrecords");
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
    <title>Admin | SmartUchaguzi</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('uploads/background.png');
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

        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: #e6e6e6;
            cursor: pointer;
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

        .user a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
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

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100%;
            background: #1a3c34;
            padding-top: 80px;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 900;
        }

        .sidebar .nav {
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        .sidebar .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }

        .sidebar .nav a.active {
            background: #f4a261;
            color: #fff;
        }

        .sidebar .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 260px;
            padding: 80px 1rem 2rem;
            min-height: 100vh;
        }

        .dash-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 1200px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
        }

        .dash-content h2 {
            font-size: 28px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
        }

        h3 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .overview {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .overview .card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .overview .card:hover {
            transform: translateY(-5px);
        }

        .overview .card i {
            font-size: 2rem;
            color: #f4a261;
            margin-bottom: 10px;
        }

        .overview .card .text {
            font-size: 1rem;
            color: #2d3748;
        }

        .overview .card .number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a3c34;
            margin-top: 5px;
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
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .action-buttons button:hover {
            background: #e76f51;
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
            font-size: 1rem;
            color: #2d3748;
        }

        .analytics-filter select {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }

        .analytics-filter button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }

        .analytics-filter button:hover {
            background: #e76f51;
        }

        .analytics-filter button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        .vote-analytics {
            margin-top: 20px;
        }

        .vote-analytics h4 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }

        .vote-analytics p {
            font-size: 14px;
            color: #2d3748;
            text-align: center;
        }

        .vote-analytics canvas {
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .error {
            color: #e76f51;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .blockchain-table,
        .audit-table,
        .fraud-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .blockchain-table th,
        .blockchain-table td,
        .audit-table th,
        .audit-table td,
        .fraud-table th,
        .fraud-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e7ea;
        }

        .blockchain-table th,
        .audit-table th,
        .fraud-table th {
            background: #f4a261;
            color: #fff;
            font-weight: 600;
        }

        .blockchain-table td,
        .audit-table td,
        .fraud-table td {
            color: #2d3748;
            font-size: 14px;
            background: #fff;
        }

        .blockchain-table tr:hover td,
        .audit-table tr:hover td,
        .fraud-table tr:hover td {
            background: rgba(244, 162, 97, 0.1);
        }

        .blockchain-table td a,
        .fraud-table td button {
            color: #f4a261;
            text-decoration: none;
            font-weight: 500;
            background: none;
            border: none;
            cursor: pointer;
        }

        .blockchain-table td a:hover,
        .fraud-table td button:hover {
            color: #e76f51;
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

        footer {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 15px;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
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
            .menu-toggle {
                display: block;
            }
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
            .overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .overview {
                grid-template-columns: 1fr;
            }
            .action-buttons button {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-bars menu-toggle"></i>
            <img src="./images/System Logo.jpg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        
        <div class="user">
            <span><?php echo $admin_name . ($college_name ? ' (' . $college_name . ')' : ''); ?></span>
            <img src="images/default.png" alt="Profile" onerror="this.src='images/general.png';">
            <div class="dropdown" id="user-dropdown">
                <span><?php echo htmlspecialchars($admin_name); ?></span>
                <a href="#">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </header>

    <aside class="sidebar">
        <div class="nav">
            <a href="#" class="active"><i class="fas fa-home"></i> Overview</a>
            <a href="admin-operations/manage-elections.php"><i class="fas fa-cog"></i> Election Management</a>
            <a href="admin-operations/blockchain-verification.php"><i class="fas fa-chain"></i> Blockchain Verification</a>
            <a href="admin-operations/user-management.php"><i class="fas fa-users"></i> User Management</a>
            <a href="admin-operations/votes-analytics.php"><i class="fas fa-chart-bar"></i> Votes Analytics</a>
            <a href="admin-operations/fraud-incidents.php"><i class="fas fa-exclamation-triangle"></i> Fraud Incidents</a>
            <a href="admin-operations/security-settings.php"><i class="fas fa-shield-alt"></i> Security Settings</a>
            <a href="admin-operations/audit-logs.php"><i class="fas fa-file-alt"></i> Audit Logs</a>
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
        const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
        const contractAddress = '0xC046c854C85e56DB6AF41dF3934DD671831d9d09';
        const contractABI = [{
                "inputs": [],
                "stateMutability": "nonpayable",
                "type": "constructor"
            },
            {
                "anonymous": false,
                "inputs": [{
                        "indexed": false,
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "indexed": true,
                        "internalType": "address",
                        "name": "voter",
                        "type": "address"
                    },
                    {
                        "indexed": false,
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "name": "VoteCast",
                "type": "event"
            },
            {
                "inputs": [],
                "name": "admin",
                "outputs": [{
                    "internalType": "address",
                    "name": "",
                    "type": "address"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "name": "castVote",
                "outputs": [],
                "stateMutability": "nonpayable",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    }
                ],
                "name": "getVoteCount",
                "outputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                    "internalType": "uint256",
                    "name": "electionId",
                    "type": "uint256"
                }],
                "name": "getVotesByElection",
                "outputs": [{
                    "components": [{
                            "internalType": "uint256",
                            "name": "electionId",
                            "type": "uint256"
                        },
                        {
                            "internalType": "address",
                            "name": "voter",
                            "type": "address"
                        },
                        {
                            "internalType": "uint256",
                            "name": "positionId",
                            "type": "uint256"
                        },
                        {
                            "internalType": "string",
                            "name": "candidateId",
                            "type": "string"
                        },
                        {
                            "internalType": "uint256",
                            "name": "timestamp",
                            "type": "uint256"
                        },
                        {
                            "internalType": "string",
                            "name": "candidateName",
                            "type": "string"
                        },
                        {
                            "internalType": "string",
                            "name": "positionName",
                            "type": "string"
                        }
                    ],
                    "internalType": "struct VoteContract.Vote[]",
                    "name": "",
                    "type": "tuple[]"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "address",
                        "name": "",
                        "type": "address"
                    },
                    {
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "",
                        "type": "string"
                    }
                ],
                "name": "hasVoted",
                "outputs": [{
                    "internalType": "bool",
                    "name": "",
                    "type": "bool"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "",
                        "type": "string"
                    }
                ],
                "name": "voteCount",
                "outputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "name": "votes",
                "outputs": [{
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "address",
                        "name": "voter",
                        "type": "address"
                    },
                    {
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "internalType": "uint256",
                        "name": "timestamp",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "stateMutability": "view",
                "type": "function"
            }
        ];
        const contract = new ethers.Contract(contractAddress, contractABI, provider);

        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const profilePic = document.querySelector('.user img');
            const userDropdown = document.getElementById('user-dropdown');

            // Sidebar Toggle
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Profile Dropdown
            if (profilePic) {
                profilePic.addEventListener('click', () => {
                    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', (e) => {
                    if (!profilePic.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.style.display = 'none';
                    }
                });
            }

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
                } else if (inactiveTime >= inactivity_timeout) {
                    window.location.href = 'logout.php';
                }
            }

            document.addEventListener('mousemove', () => { lastActivity = Date.now(); });
            document.addEventListener('keydown', () => { lastActivity = Date.now(); });
            extendButton.addEventListener('click', () => {
                lastActivity = Date.now();
                modal.style.display = 'none';
            });

            setInterval(checkTimeouts, 1000);
        });
    </script>
</body>
</html>
<?php $pdo = null; ?>