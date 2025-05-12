<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}

// Helper Functions for Feature Collection
function getUserVoteCount($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM blockchain WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['vote_count'];
}

function calculateVoteFrequency($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT created_at FROM blockchain WHERE user_id = ? ORDER BY created_at DESC LIMIT 2");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $timestamps = [];
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = strtotime($row['created_at']);
    }
    $stmt->close();
    if (count($timestamps) < 2) return 0;
    $time_diff = $timestamps[0] - $timestamps[1];
    return $time_diff > 0 ? 1 / $time_diff : 0;
}

function checkMultipleLogins($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as login_count FROM sessions WHERE user_id = ? AND login_time >= NOW() - INTERVAL 1 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['login_count'] > 1 ? 1 : 0;
}

function detectVPN($ip)
{
    // Simple heuristic: Check for known VPN provider IP ranges (simplified for demo)
    $ch = curl_init("http://ip-api.com/json/$ip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return (isset($data['isp']) && stripos($data['isp'], 'vpn') !== false) ? 1 : 0;
}

function isTanzaniaLocation($ip)
{
    $ch = curl_init("http://ipapi.co/$ip/json/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return (isset($data['country_code']) && $data['country_code'] === 'TZ') ? 0 : 1;
}

function logFraud($conn, $user_id, $election_id, $is_fraudulent, $confidence, $details, $action)
{
    $description = $is_fraudulent ? "Fraud detected with confidence $confidence" : "No fraud detected";
    $stmt = $conn->prepare(
        "INSERT INTO frauddetectionlogs (user_id, election_id, is_fraudulent, confidence, details, description, action, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("iiidsss", $user_id, $election_id, $is_fraudulent, $confidence, $details, $description, $action);
    $stmt->execute();
    $stmt->close();
}

function blockUser($conn, $user_id)
{
    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Session Validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    error_log("Session validation failed: user_id or role not set or invalid. Session: " . print_r($_SESSION, true));
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

$user_id = $_SESSION['user_id'];
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

$profile_picture = 'images/general.png';
$errors = [];
$elections = [];

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
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id, ep.hostel_id
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

                $cand_stmt = $conn->prepare(
                    "SELECT id, official_id, firstname, lastname
                     FROM candidates
                     WHERE election_id = ? AND position_id = ?"
                );
                $cand_stmt->bind_param('ii', $election_id, $position_id);
                $cand_stmt->execute();
                $cand_result = $cand_stmt->get_result();
                $candidates = $cand_result->fetch_all(MYSQLI_ASSOC);
                $cand_stmt->close();

                $position['candidates'] = $candidates;
                $positions[] = $position;
            }
            $stmt->close();

            $election['positions'] = $positions;
        }
    }
} catch (Exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections due to a server error: " . htmlspecialchars($e->getMessage());
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
            margin-bottom: 10px;
        }

        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .candidate-table th,
        .candidate-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .candidate-table th {
            background: #1a3c34;
            color: #e6e6e6;
            text-transform: uppercase;
            font-size: 14px;
        }

        .candidate-table td {
            background: #fff;
            font-size: 16px;
        }

        .candidate-table tr:hover {
            background: #f9f9f9;
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
            margin-top: 10px;
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

            .candidate-table th,
            .candidate-table td {
                padding: 8px;
                font-size: 14px;
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
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic">
            <div class="dropdown" id="user-dropdown">
                <span style="color: #e6e6e6; padding: 10px 20px;"><?php echo htmlspecialchars($user['fname'] ?? 'User'); ?></span>
                <a href="profile.php">My Profile</a>
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
                                        <form class="vote-form" data-election-id="<?php echo $election['election_id']; ?>" data-position-id="<?php echo $position['position_id']; ?>">
                                            <table class="candidate-table">
                                                <thead>
                                                    <tr>
                                                        <th>Official ID</th>
                                                        <th>First Name</th>
                                                        <th>Last Name</th>
                                                        <th>Association</th>
                                                        <th>Vote</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($position['candidates'] as $candidate): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($candidate['official_id']); ?></td>
                                                            <td><?php echo htmlspecialchars($candidate['firstname']); ?></td>
                                                            <td><?php echo htmlspecialchars($candidate['lastname']); ?></td>
                                                            <td><?php echo htmlspecialchars($association); ?></td>
                                                            <td>
                                                                <input type="radio" name="candidate_id" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>" required>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
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

    <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js" type="text/javascript"></script>
    <script>
        const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
        const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';
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
                        "internalType": "uint256",
                        "name": "candidateId",
                        "type": "uint256"
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
                        "internalType": "uint256",
                        "name": "candidateId",
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
                        "internalType": "uint256",
                        "name": "candidateId",
                        "type": "uint256"
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
                            "internalType": "uint256",
                            "name": "candidateId",
                            "type": "uint256"
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
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
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
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
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
                        "internalType": "uint256",
                        "name": "candidateId",
                        "type": "uint256"
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

        function showSuccess(message) {
            const successMessage = document.getElementById('success-message');
            successMessage.textContent = message;
            successMessage.style.display = 'block';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }

        function showError(message) {
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 5000);
        }

        document.querySelectorAll('.vote-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const electionId = form.getAttribute('data-election-id');
                const positionId = form.getAttribute('data-position-id');
                const candidateId = form.querySelector('input[name="candidate_id"]:checked')?.value;
                const candidateName = form.querySelector('input[name="candidate_id"]:checked')?.closest('tr').querySelector('td:nth-child(2)').textContent;
                const positionName = form.querySelector('h4').textContent.replace('Position: ', '');

                if (!candidateId) {
                    showError('Please select a candidate to vote for.');
                    return;
                }

                const submitButton = form.querySelector('button');
                submitButton.disabled = true;
                submitButton.textContent = 'Checking for Fraud...';

                // Fraud Detection API Call
                try {
                    const fraudData = {
                        time_diff: <?php echo time() - $_SESSION['last_activity']; ?>,
                        votes_per_user: <?php echo getUserVoteCount($conn, $user_id); ?>,
                        vote_frequency: <?php echo calculateVoteFrequency($conn, $user_id); ?>,
                        vpn_usage: <?php echo detectVPN($_SERVER['REMOTE_ADDR']); ?>,
                        multiple_logins: <?php echo checkMultipleLogins($conn, $user_id); ?>,
                        session_duration: <?php echo time() - $_SESSION['start_time']; ?>,
                        location_flag: <?php echo isTanzaniaLocation($_SERVER['REMOTE_ADDR']); ?>
                    };

                    const fraudResponse = await fetch('http://localhost:5000/predict', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(fraudData)
                    });
                    const fraudResult = await fraudResponse.json();

                    if (fraudResult.error) {
                        throw new Error('Fraud detection failed: ' + fraudResult.error);
                    }

                    const isFraudulent = fraudResult.fraud_label;
                    const confidence = fraudResult.fraud_probability;
                    let action = 'none';
                    const details = JSON.stringify(fraudData);

                    if (isFraudulent) {
                        action = confidence > 0.9 ? 'block_user' : 'logout';
                        // Log fraud
                        const fraudLogResponse = await fetch('log-fraud.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: <?php echo $user_id; ?>,
                                election_id: electionId,
                                is_fraudulent: isFraudulent,
                                confidence: confidence,
                                details: details,
                                action: action
                            })
                        });
                        const fraudLogResult = await fraudLogResponse.json();
                        if (!fraudLogResult.success) {
                            throw new Error('Failed to log fraud: ' + fraudLogResult.message);
                        }

                        // Take action
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
                            setTimeout(() => {
                                window.location.href = 'login.php?error=' + encodeURIComponent('Account blocked due to fraud detection.');
                            }, 3000);
                            return;
                        } else {
                            showError('Fraud detected. You will be logged out.');
                            setTimeout(() => {
                                window.location.href = 'login.php?error=' + encodeURIComponent('Logged out due to fraud detection.');
                            }, 3000);
                            return;
                        }
                    } else {
                        // Log non-fraudulent attempt
                        const fraudLogResponse = await fetch('log-fraud.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: <?php echo $user_id; ?>,
                                election_id: electionId,
                                is_fraudulent: isFraudulent,
                                confidence: confidence,
                                details: details,
                                action: action
                            })
                        });
                        const fraudLogResult = await fraudLogResponse.json();
                        if (!fraudLogResult.success) {
                            throw new Error('Failed to log fraud: ' + fraudLogResult.message);
                        }
                    }

                    // Proceed with vote casting if no fraud
                    submitButton.textContent = 'Submitting Vote...';
                    const signer = provider.getSigner();
                    const contractWithSigner = contract.connect(signer);
                    const tx = await contractWithSigner.castVote(
                        electionId,
                        positionId,
                        candidateId,
                        candidateName,
                        positionName, {
                            gasLimit: 300000
                        }
                    );
                    const receipt = await tx.wait();

                    // Log vote in MySQL database
                    const response = await fetch('log-vote.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: <?php echo $user_id; ?>,
                            election_id: electionId,
                            position_id: positionId,
                            candidate_id: candidateId,
                            transaction_hash: receipt.transactionHash
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
                } catch (error) {
                    showError('Error: ' + error.message);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Cast Vote';
                }
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

        extendSessionButton.addEventListener('click', () => {
            resetInactivityTimer();
        });

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
<?php
$conn->close();
?>