<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    error_log("Initial session validation failed: user_id or role not set. Session: " . print_r($_SESSION, true));
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

if ($_SESSION['role'] !== 'voter') {
    error_log("Role mismatch: expected voter, got " . ($_SESSION['role'] ?? 'unset'));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: 2fa.php');
    exit;
}

error_log("Session after validation: user_id=" . ($_SESSION['user_id'] ?? 'unset') .
    ", role=" . ($_SESSION['role'] ?? 'unset') .
    ", college_id=" . ($_SESSION['college_id'] ?? 'unset') .
    ", association=" . ($_SESSION['association'] ?? 'unset'));

if (!isset($_SESSION['wallet_address']) || empty($_SESSION['wallet_address'])) {
    error_log("Wallet address not set in session for user_id: " . ($_SESSION['user_id'] ?? 'unset'));
    header('Location: post-login.php?role=' . urlencode($_SESSION['role']) . '&college_id=' . urlencode($_SESSION['college_id'] ?? '') . '&association=' . urlencode($_SESSION['association'] ?? '') . '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? ''));
    exit;
}

if (isset($_SESSION['college_id']) && $_SESSION['college_id'] != 1) {
    error_log("College ID mismatch: expected 1, got " . $_SESSION['college_id']);
    header('Location: login.php?error=' . urlencode('Invalid college for this dashboard.'));
    exit;
}

if (isset($_SESSION['association']) && $_SESSION['association'] !== 'UDOMASA') {
    error_log("Association mismatch: expected UDOMASA, got " . $_SESSION['association']);
    header('Location: login.php?error=' . urlencode('Invalid association for this dashboard.'));
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch detected: Session UA: " . $_SESSION['user_agent'] . ", Current UA: " . $_SERVER['HTTP_USER_AGENT']);
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    error_log("User agent updated in session.");
}

$inactivity_timeout = 30 * 60; // 30 minutes
$max_session_duration = 1 * 60 * 60; // 1 hour
$warning_time = 28;

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
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No user found for user_id: " . $user_id);
    }
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('User not found or server error. Please log in again.'));
    exit;
}

function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCsrfToken();

$profile_picture = 'Uploads/passports/general.png';
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
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id, ep.is_vice
                FROM electionpositions ep
                WHERE ep.election_id = ?
                AND (
                    ep.scope = 'university'
                    OR (ep.scope = 'college' AND ep.college_id = ?)
                )
                ORDER BY ep.position_id";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $election_id, $college_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($position = $result->fetch_assoc()) {
                $position_id = $position['position_id'];
                $scope = $position['scope'];
                $is_vice = $position['is_vice'];

                $candidates = [];

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

                $position['candidates'] = $candidates;
                $positions[] = $position;
            }
            $stmt->close();

            $election['positions'] = $positions;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections due to a server error.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cive UDOMASA | Dashboard</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
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
            display: flex;
            flex-direction: column;
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
            position: relative;
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
            z-index: 1002;
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
            flex: 1;
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

        .my-votes-section {
            margin-bottom: 30px;
        }

        .my-votes-section h3 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .vote-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
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

        .verify-modal {
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

        .verify-modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .verify-modal-content label {
            display: block;
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .verify-modal-content input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
        }

        .verify-modal-content input:focus {
            border-color: #1a3c34;
        }

        .verify-modal-content input:blur {
            border-color: #e0e0e0;
        }

        .verify-modal-content button {
            background: #1a3c34;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            margin: 0 10px;
        }

        .verify-modal-content button:hover {
            background: #f4a261;
        }

        .results-modal {
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

        .results-modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            max-width: 90%;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
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

        .modal-content button {
            background: #1a3c34;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .modal-content button:hover {
            background: #f4a261;
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
            <img src="./images/System Logo.jpg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="nav">
            <a href="process-vote.php" id="cast-vote-link">Cast Vote</a>
            <a href="#" id="verify-vote-link">Verify Vote</a>
            <a href="#" id="results-link">Results</a>
        </div>
        <div class="user">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic">
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
            <h2>The Candidate Details</h2>

            <div class="my-votes-section">
                <h3>Votes</h3>
                <div id="my-votes"></div>
            </div>

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
                                        <div class="candidate-grid">
                                            <?php
                                            foreach ($position['candidates'] as $key => $candidateGroup) {
                                                if (count($candidateGroup) == 2) {
                                                    $mainCandidate = $candidateGroup[0]['is_vice'] == 0 ? $candidateGroup[0] : $candidateGroup[1];
                                                    $viceCandidate = $candidateGroup[0]['is_vice'] == 1 ? $candidateGroup[0] : $candidateGroup[1];
                                                    $pair_id = $mainCandidate['pair_id'];
                                            ?>
                                                    <div class="candidate-card">
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
                                                    </div>
                                                <?php
                                                } else {
                                                    $candidate = $candidateGroup[0];
                                                    $candidate_id = $candidate['id'];
                                                ?>
                                                    <div class="candidate-card">
                                                        <div style="display: flex; align-items: center;">
                                                            <img src="<?php echo htmlspecialchars($candidate['passport'] ?: 'images/general.png'); ?>" alt="Candidate <?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?>" class="candidate-img">
                                                            <div class="candidate-details">
                                                                <h5><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></h5>
                                                                <p>Official ID: <?php echo htmlspecialchars($candidate['official_id']); ?></p>
                                                                <p>Association: <?php echo htmlspecialchars($association); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="verify-modal" id="verify-modal">
        <div class="verify-modal-content" style="background: #fff; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);">
            <h3 style="font-size: 24px; color: #1a3c34; margin-bottom: 20px;">Verify Your Vote</h3>
            <div style="margin-bottom: 15px;">
                <label for="verify-election-id" style="display: block; font-size: 14px; color: #2d3748; margin-bottom: 5px;">Election ID:</label>
                <input type="number" id="verify-election-id" placeholder="Enter Election ID" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 14px; outline: none;" onfocus="this.style.borderColor='#1a3c34';" onblur="this.style.borderColor='#e0e0e0';">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="verify-position-id" style="display: block; font-size: 14px; color: #2d3748; margin-bottom: 5px;">Position ID:</label>
                <input type="number" id="verify-position-id" placeholder="Enter Position ID" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 14px; outline: none;" onfocus="this.style.borderColor='#1a3c34';" onblur="this.style.borderColor='#e0e0e0';">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="verify-candidate-id" style="display: block; font-size: 14px; color: #2d3748; margin-bottom: 5px;">Candidate ID:</label>
                <input type="text" id="verify-candidate-id" placeholder="Enter Candidate ID" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 14px; outline: none;" onfocus="this.style.borderColor='#1a3c34';" onblur="this.style.borderColor='#e0e0e0';">
            </div>
            <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
                <button onclick="verifyVote()" style="padding: 10px 20px; background: #1a3c34; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background 0.3s;">Verify</button>
                <button onclick="closeVerifyModal()" style="padding: 10px 20px; background: #e76f51; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background 0.3s;">Cancel</button>
            </div>
            <p id="verify-result" style="margin-top: 15px; font-size: 14px; color: #2d3748;"></p>
        </div>
    </div>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute due to inactivity.</p>
            <button id="extend-session">OK</button>
        </div>
    </div>

    <div class="results-modal" id="results-modal">
        <div class="results-modal-content" id="results-content">
            <!-- Results content here -->
        </div>
    </div>

    <script>
        const inactivityTimeout = <?php echo $inactivity_timeout; ?>;
        const warningTime = <?php echo $warning_time; ?>;
        const isDevMode = <?php echo json_encode(getenv('DEV_MODE') === 'true' || isset($_GET['dev_mode'])); ?>;
        let inactivityTimer;
        let warningTimer;
        const timeoutModal = document.getElementById('timeout-modal');
        const timeoutMessage = document.getElementById('timeout-message');
        const extendSessionButton = document.getElementById('extend-session');
        const verifyVoteLink = document.getElementById('verify-vote-link');
        const verifyModal = document.getElementById('verify-modal');
        const myVotesSection = document.getElementById('my-votes');
        const castVoteLink = document.getElementById('cast-vote-link');
        const resultsLink = document.getElementById('results-link');
        const resultsModal = document.getElementById('results-modal');
        const resultsContent = document.getElementById('results-content');

        const contractAddress = '0xC046c854C85e56DB6AF41dF3934DD671831d9d09';
        const abi = [{
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

        const alchemyApiKey = '1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B';
        let provider = new Web3.providers.WebsocketProvider(`wss://eth-sepolia.g.alchemy.com/v2/${alchemyApiKey}`);
        let web3 = new Web3(provider);
        let contract = new web3.eth.Contract(abi, contractAddress);

        async function getAndValidateWalletAddress() {
            try {
                if (typeof window.ethereum === 'undefined') {
                    console.error('MetaMask is not installed.');
                    alert('Please install MetaMask to use this voting platform.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask not detected.');
                    return null;
                }

                const accounts = await window.ethereum.request({
                    method: 'eth_requestAccounts'
                });
                if (accounts.length === 0) {
                    console.error('No MetaMask accounts available.');
                    alert('Please connect an account in MetaMask.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('No MetaMask account connected.');
                    return null;
                }

                const currentAddress = accounts[0];
                const sessionAddress = '<?php echo htmlspecialchars($_SESSION['wallet_address'] ?? '0x0'); ?>';

                if (currentAddress.toLowerCase() !== sessionAddress.toLowerCase()) {
                    console.error('Wallet address mismatch. Session locked to initial wallet.');
                    alert('Your MetaMask account does not match the logged-in wallet. Please log in with the correct account.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('Wallet mismatch detected.');
                    return null;
                }

                return currentAddress;
            } catch (error) {
                console.error('Error accessing MetaMask wallet:', error);
                alert('Failed to connect to MetaMask: ' + error.message);
                window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask connection failed.');
                return null;
            }
        }

        async function loadMyVotes() {
            const voterAddress = await getAndValidateWalletAddress();
            if (!voterAddress) {
                myVotesSection.innerHTML = '<p class="error">Unable to load votes: Wallet validation failed.</p>';
                return;
            }
            try {
                const association = '<?php echo htmlspecialchars($_SESSION['association'] ?? ''); ?>';
                let electionId = 2; // UDOMASA election ID
                console.log('Fetching votes for electionId:', electionId, 'voter:', voterAddress);

                const allVotes = await contract.methods.getVotesByElection(electionId).call();
                console.log('Votes returned:', allVotes);
                let myVotesHtml = '';
                let hasVotes = false;

                for (let vote of allVotes) {
                    if (web3.utils.toChecksumAddress(vote.voter) === web3.utils.toChecksumAddress(voterAddress)) {
                        myVotesHtml += `<div class="vote-item">
                            <p>Election ID: ${vote.electionId}</p>
                            <p>Position: ${vote.positionName}</p>
                            <p>Candidate: ${vote.candidateName}</p>
                            <p>Time: ${new Date(vote.timestamp * 1000).toLocaleString()}</p>
                        </div>`;
                        hasVotes = true;
                    }
                }

                if (!hasVotes) {
                    myVotesHtml = `<p>No votes cast by you.</p>`;
                }
                myVotesSection.innerHTML = '<h3>The Votes Summary</h3>' + myVotesHtml;
            } catch (error) {
                console.error('Error loading votes:', error.code, error.message);
                myVotesSection.innerHTML = '<p class="error">Error loading votes: ' + (error.message || 'Unknown error') + '. Please try again later.</p>';
            }
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(warningTimer);
            timeoutModal.style.display = 'none';

            warningTimer = setTimeout(() => {
                timeoutMessage.textContent = 'You will be logged out in 1 minute due to inactivity.';
                timeoutModal.style.display = 'flex';
            }, (inactivityTimeout - warningTime) * 1000);

            inactivityTimer = setTimeout(() => {
                window.location.href = 'login.php?error=' + encodeURIComponent('Session expired due to inactivity.');
            }, inactivityTimeout * 1000);
        }

        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        document.addEventListener('click', resetInactivityTimer);
        document.addEventListener('scroll', resetInactivityTimer);

        extendSessionButton.addEventListener('click', resetInactivityTimer);

        window.addEventListener('load', async () => {
            await loadMyVotes();
            resetInactivityTimer();
        });

        verifyVoteLink.addEventListener('click', (e) => {
            e.preventDefault();
            verifyModal.style.display = 'flex';
        });

        resultsLink.addEventListener('click', (e) => {
            e.preventDefault();
            resultsModal.style.display = 'flex';
            resultsContent.innerHTML = `
            <h3>Election Results</h3>
            <div style="margin-bottom: 20px;">
                <input type="number" id="election-id-input" placeholder="Search Election ID..." style="padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; width: 100%; max-width: 300px; font-size: 14px; outline: none;" onfocus="this.style.borderColor='#1a3c34';" onblur="this.style.borderColor='#e0e0e0';">
                <button id="fetch-results-btn" style="padding: 10px 20px; background: #1a3c34; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px; font-size: 14px; transition: background 0.3s;">Fetch Results</button>
            </div>
            <div id="results-display"></div>
        `;

            document.getElementById('fetch-results-btn').addEventListener('click', displayResults);
        });

        castVoteLink.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = 'process-vote.php';
        });

        async function verifyVote() {
            const electionId = document.getElementById('verify-election-id').value;
            const positionId = document.getElementById('verify-position-id').value;
            const candidateId = document.getElementById('verify-candidate-id').value;
            const resultDiv = document.getElementById('verify-result');

            if (!electionId || !positionId || !candidateId) {
                resultDiv.innerHTML = '<p class="error">Please fill in all fields.</p>';
                return;
            }

            try {
                const voterAddress = await getAndValidateWalletAddress();
                if (!voterAddress) {
                    resultDiv.innerHTML = '<p class="error">Wallet validation failed.</p>';
                    return;
                }

                const allVotes = await contract.methods.getVotesByElection(electionId).call();
                let voteFound = false;

                for (let vote of allVotes) {
                    if (
                        web3.utils.toChecksumAddress(vote.voter) === web3.utils.toChecksumAddress(voterAddress) &&
                        vote.positionId === positionId &&
                        vote.candidateId === candidateId
                    ) {
                        resultDiv.innerHTML = `<p class="success">Vote verified! You voted for Candidate ID ${candidateId} for ${vote.positionName} in Election ID ${electionId} at ${new Date(vote.timestamp * 1000).toLocaleString()}</p>`;
                        voteFound = true;
                        break;
                    }
                }

                if (!voteFound) {
                    resultDiv.innerHTML = '<p class="error">No matching vote found for the provided details.</p>';
                }
            } catch (error) {
                console.error('Error verifying vote:', error.code, error.message);
                resultDiv.innerHTML = '<p class="error">Error verifying vote: ' + (error.message || 'Unknown error') + '</p>';
            }
        }

        function closeVerifyModal() {
            verifyModal.style.display = 'none';
            document.getElementById('verify-election-id').value = '';
            document.getElementById('verify-position-id').value = '';
            document.getElementById('verify-candidate-id').value = '';
            document.getElementById('verify-result').innerHTML = '';
        }

        async function displayResults() {
            const electionIdInput = document.getElementById('election-id-input');
            const electionId = electionIdInput.value.trim();
            const resultsDisplay = document.getElementById('results-display');

            if (!electionId) {
                resultsDisplay.innerHTML = '<p class="error">Please enter a valid Election ID.</p>';
                return;
            }

            try {
                const voterAddress = await getAndValidateWalletAddress();
                if (!voterAddress) {
                    resultsDisplay.innerHTML = '<p class="error">Unable to fetch results: Wallet validation failed.</p>';
                    return;
                }

                const allVotes = await contract.methods.getVotesByElection(electionId).call({
                    from: voterAddress
                });
                let resultsHtml = `
                <h4>Results for Election ID: ${electionId}</h4>
                <div class="candidate-grid" style="margin-top: 20px;">
                    <div class="candidate-card" style="padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #1a3c34; color: #fff;">
                                    <th style="padding: 10px; text-align: left;">Candidate ID</th>
                                    <th style="padding: 10px; text-align: left;">Name</th>
                                    <th style="padding: 10px; text-align: left;">Position</th>
                                    <th style="padding: 10px; text-align: left;">Votes</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

                const voteCounts = {};
                allVotes.forEach(vote => {
                    const candidateId = vote.candidateId;
                    voteCounts[candidateId] = voteCounts[candidateId] || {
                        count: 0,
                        name: vote.candidateName,
                        position: vote.positionName
                    };
                    voteCounts[candidateId].count += 1;
                });

                for (let candidateId in voteCounts) {
                    resultsHtml += `
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 10px;">${candidateId}</td>
                        <td style="padding: 10px;">${voteCounts[candidateId].name}</td>
                        <td style="padding: 10px;">${voteCounts[candidateId].position}</td>
                        <td style="padding: 10px;">${voteCounts[candidateId].count} vote(s)</td>
                    </tr>
                `;
                }

                if (Object.keys(voteCounts).length === 0) {
                    resultsHtml += `
                    <td colspan="4" style="padding: 10px; text-align: center; color: #e76f51;">No votes recorded yet.</td>
                </tr>`;
                }

                resultsHtml += `
                            </tbody>
                        </table>
                        <button onclick="closeResultsModal()" style="margin-top: 20px; padding: 10px 20px; background: #e76f51; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background 0.3s;">Close</button>
                    </div>
                </div>
            `;

                resultsDisplay.innerHTML = resultsHtml;
            } catch (error) {
                console.error('Error fetching results:', error.code, error.message);
                resultsDisplay.innerHTML = '<p class="error">Error fetching results: ' + (error.message || 'Unknown error') + '</p>';
            }
        }

        function closeResultsModal() {
            resultsModal.style.display = 'none';
        }

        const profilePic = document.getElementById('profile-pic');
        const userDropdown = document.getElementById('user-dropdown');

        profilePic.addEventListener('click', (e) => {
            e.preventDefault();
            const isVisible = userDropdown.style.display === 'block';
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });

        document.addEventListener('click', (event) => {
            if (!profilePic.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>