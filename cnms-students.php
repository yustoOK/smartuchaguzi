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

if (isset($_SESSION['college_id']) && $_SESSION['college_id'] != 3) {
    error_log("College ID mismatch: expected 3, got " . $_SESSION['college_id']);
    header('Location: login.php?error=' . urlencode('Invalid college for this dashboard.'));
    exit;
}

if (isset($_SESSION['association']) && $_SESSION['association'] !== 'UDOSO') {
    error_log("Association mismatch: expected UDOSO, got " . $_SESSION['association']);
    header('Location: login.php?error=' . urlencode('Invalid association for this dashboard.'));
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch; possible session hijacking attempt.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed.'));
    exit;
}

$inactivity_timeout = 5 * 60 * 60;
$max_session_duration = 12 * 60 * 60;
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

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCsrfToken();

$profile_picture = 'uploads/passports/general.png';   
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
    $errors[] = "Failed to load elections due to a server error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNMS UDOSO | Dashboard</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cnms.jpeg'); background-size: cover; color: #2d3748; min-height: 100vh; display: flex; flex-direction: column; }
        .header { background: #1a3c34; color: #e6e6e6; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .logo { display: flex; align-items: center; }
        .logo img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; }
        .logo h1 { font-size: 24px; font-weight: 600; }
        .nav a { color: #e6e6e6; text-decoration: none; margin: 0 15px; font-size: 16px; transition: color 0.3s ease; }
        .nav a:hover { color: #f4a261; }
        .user { display: flex; align-items: center; }
        .user img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; cursor: pointer; }
        .dropdown { display: none; position: absolute; top: 60px; right: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .dropdown a, .dropdown span { display: block; padding: 10px 20px; color: #2d3748; text-decoration: none; font-size: 16px; }
        .dropdown a:hover { background: #f4a261; color: #fff; }
        .logout-link { display: none; color: #e6e6e6; text-decoration: none; font-size: 16px; }
        .dashboard { margin-top: 80px; padding: 30px; display: flex; justify-content: center; flex: 1; }
        .dash-content { background: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 12px; width: 100%; max-width: 1200px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15); }
        .dash-content h2 { font-size: 28px; color: #1a3c34; margin-bottom: 20px; text-align: center; }
        .my-votes-section { margin-bottom: 30px; }
        .my-votes-section h3 { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .vote-item { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 10px; }
        .election-section { margin-bottom: 30px; }
        .election-section h3 { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .position-section { margin-bottom: 20px; }
        .position-section h4 { font-size: 18px; color: #2d3748; margin-bottom: 15px; border-bottom: 2px solid #1a3c34; padding-bottom: 5px; }
        .candidate-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .candidate-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: space-between; transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; overflow: hidden; }
        .candidate-card:hover { transform: translateY(-5px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); }
        .candidate-img { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid #1a3c34; margin-right: 15px; transition: border-color 0.3s ease; }
        .candidate-card:hover .candidate-img { border-color: #f4a261; }
        .candidate-details { flex: 1; display: flex; flex-direction: column; gap: 5px; }
        .candidate-details h5 { font-size: 16px; font-weight: 600; color: #2d3748; margin: 0; }
        .candidate-details p { font-size: 14px; color: #666; margin: 0; }
        .error, .success { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 16px; }
        .error { background: #ffe6e6; color: #e76f51; border: 1px solid #e76f51; }
        .success { background: #e6fff5; color: #2a9d8f; border: 1px solid #2a9d8f; }
        .verify-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1001; justify-content: center; align-items: center; }
        .verify-modal-content { background: #fff; padding: 20px; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; }
        .verify-modal-content label { display: block; margin: 10px 0 5px; color: #2d3748; }
        .verify-modal-content input { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #e0e0e0; border-radius: 4px; }
        .verify-modal-content button { background: #f4a261; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 16px; margin: 0 10px; }
        .verify-modal-content button:hover { background: #e76f51; }
        @media (max-width: 768px) {
            .header { flex-direction: column; padding: 10px 20px; }
            .logo h1 { font-size: 20px; }
            .nav { margin: 10px 0; text-align: center; }
            .nav a { margin: 0 10px; font-size: 14px; }
            .user img { display: none; }
            .dropdown { display: block; position: static; box-shadow: none; background: none; text-align: center; }
            .dropdown a, .dropdown span { color: #e6e6e6; padding: 5px 10px; }
            .dropdown a:hover { background: none; color: #f4a261; }
            .logout-link { display: block; margin-top: 10px; }
            .dash-content { padding: 20px; }
            .dash-content h2 { font-size: 24px; }
            .election-section h3 { font-size: 18px; }
            .position-section h4 { font-size: 16px; }
            .candidate-grid { grid-template-columns: 1fr; }
            .candidate-card { flex-direction: column; align-items: flex-start; padding: 15px; }
            .candidate-img { width: 60px; height: 60px; margin-bottom: 10px; margin-right: 0; }
            .candidate-details h5 { font-size: 14px; }
            .candidate-details p { font-size: 12px; }
        }
        @media (min-width: 600px) {
            .candidate-card { flex-direction: row; align-items: center; }
            .candidate-img { margin-bottom: 0; }
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
            <a href="process-vote.php?csrf_token=<?php echo htmlspecialchars($csrf_token); ?>" id="cast-vote-link">Cast Vote</a>
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

            <!-- My Votes Section -->
            <div class="my-votes-section">
                <h3>My Votes</h3>
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
                                                if ($position['scope'] !== 'hostel' && count($candidateGroup) == 2) {
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
            <input type="number" id="verify-candidate-id" placeholder="Enter Candidate ID" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 14px; outline: none;" onfocus="this.style.borderColor='#1a3c34';" onblur="this.style.borderColor='#e0e0e0';">
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

    <div class="results-modal" id="results-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1001; justify-content: center; align-items: center;">
        <div class="verify-modal-content" style="background: #fff; padding: 20px; border-radius: 8px; text-align: center; max-width: 400px; width: 90%;">
            <h3>Results</h3>
            <p id="results-content">Results will be displayed here.</p>
            <button onclick="closeResultsModal()">Close</button>
        </div>
    </div>

    <script>
        const inactivityTimeout = <?php echo $inactivity_timeout; ?>;
        const warningTime = <?php echo $warning_time; ?>;
        let inactivityTimer;
        let warningTimer;
        const timeoutModal = document.getElementById('timeout-modal');
        const timeoutMessage = document.getElementById('timeout-message');
        const extendSessionButton = document.getElementById('extend-session');
        const verifyVoteLink = document.getElementById('verify-vote-link');
        const verifyModal = document.getElementById('verify-modal');
        const myVotesSection = document.getElementById('my-votes');
        const castVoteLink = document.getElementById('cast-vote-link');

        const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';
        const abi = [
    {
      "inputs": [],
      "stateMutability": "nonpayable",
      "type": "constructor"
    },
    {
      "anonymous": false,
      "inputs": [
        {
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
      "outputs": [
        {
          "internalType": "address",
          "name": "",
          "type": "address"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
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
      "inputs": [
        {
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
      "outputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "electionId",
          "type": "uint256"
        }
      ],
      "name": "getVotesByElection",
      "outputs": [
        {
          "components": [
            {
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
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
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
      "outputs": [
        {
          "internalType": "bool",
          "name": "",
          "type": "bool"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
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
      "name": "voteCount",
      "outputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "name": "votes",
      "outputs": [
        {
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
        const alchemyApiKey = '1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B';
        const provider = new Web3.providers.HttpProvider(`https://eth-sepolia.g.alchemy.com/v2/${alchemyApiKey}`);
        const web3 = new Web3(provider);
        const contract = new web3.eth.Contract(abi, contractAddress);

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

        // Open verify modal
        verifyVoteLink.addEventListener('click', (e) => {
            e.preventDefault();
            verifyModal.style.display = 'flex';
        });

        const resultsLink = document.getElementById('results-link');
        const resultsModal = document.getElementById('results-modal');
        const resultsContent = document.getElementById('results-content');

        // Function to display results based on electionId
        async function displayResults(electionId) {
            try {
                if (!electionId || isNaN(electionId) || electionId <= 0) {
                    resultsContent.innerHTML = '<p class="error">Please enter a valid Election ID.</p>';
                    return;
                }

                const votes = await contract.methods.getVotesByElection(electionId).call();
                const positionVoteCounts = {};

                for (const vote of votes) {
                    const positionId = vote.positionId;
                    const candidateId = vote.candidateId;
                    const candidateName = vote.candidateName;
                    const positionName = vote.positionName;

                    if (!positionVoteCounts[positionId]) {
                        positionVoteCounts[positionId] = { positionName, candidates: {} };
                    }
                    if (!positionVoteCounts[positionId].candidates[candidateId]) {
                        positionVoteCounts[positionId].candidates[candidateId] = { name: candidateName, count: 0 };
                    }
                    positionVoteCounts[positionId].candidates[candidateId].count++;
                }

                let html = '<h3>Election Results</h3>';
                if (Object.keys(positionVoteCounts).length === 0) {
                    html += '<p>No votes recorded for this election.</p>';
                } else {
                    html += `
                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <thead style="background: #1a3c34; color: #fff;">
                                <tr>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Position</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Candidate</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Votes</th>
                                </tr>
                            </thead>
                            <tbody>`;
                    for (const positionId in positionVoteCounts) {
                        const position = positionVoteCounts[positionId];
                        const candidates = position.candidates;
                        for (const candidateId in candidates) {
                            const candidate = candidates[candidateId];
                            html += `
                                <tr style="background: #fff;">
                                    <td style="padding: 12px; border: 1px solid #ddd;">${position.positionName}</td>
                                    <td style="padding: 12px; border: 1px solid #ddd;">${candidate.name}</td>
                                    <td style="padding: 12px; border: 1px solid #ddd;">${candidate.count}</td>
                                </tr>`;
                        }
                    }
                    html += `</tbody></table>`;
                }
                resultsContent.innerHTML = html;
            } catch (error) {
                resultsContent.innerHTML = `<p class="error">Error fetching results: ${error.message}</p>`;
                console.error(error);
            }
        }

        // Update the results modal event listener with styled search input
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

            const fetchResultsBtn = document.getElementById('fetch-results-btn');
            fetchResultsBtn.addEventListener('click', () => {
                const electionId = document.getElementById('election-id-input').value;
                displayResults(electionId);
            });
        });

        // Close verify modal
        function closeVerifyModal() {
            verifyModal.style.display = 'none';
            document.getElementById('verify-result').textContent = '';
            document.getElementById('verify-election-id').value = '';
            document.getElementById('verify-position-id').value = '';
            document.getElementById('verify-candidate-id').value = '';
        }

        // Close results modal
        function closeResultsModal() {
            resultsModal.style.display = 'none';
            document.getElementById('results-content').textContent = '';
        }

        // Verify vote function
        async function verifyVote() {
            const electionId = document.getElementById('verify-election-id').value;
            const positionId = document.getElementById('verify-position-id').value;
            const candidateId = document.getElementById('verify-candidate-id').value;
            const voterAddress = '<?php echo $_SESSION['wallet_address'] ?? '0x0000000000000000000000000000000000000000'; ?>';

            try {
                const hasVoted = await contract.methods.hasVoted(voterAddress, electionId, positionId).call();
                if (hasVoted) {
                    const votes = await contract.methods.getVotesByElection(electionId).call();
                    let found = false;
                    for (let i = 0; i < votes.length; i++) {
                        if (votes[i].voter === voterAddress && votes[i].positionId == positionId && votes[i].candidateId == candidateId) {
                            document.getElementById('verify-result').textContent = `Vote verified! You voted for ${votes[i].candidateName} in position ${votes[i].positionName} on ${new Date(votes[i].timestamp * 1000).toLocaleString()}.`;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        document.getElementById('verify-result').textContent = 'No matching vote found for the provided details.';
                    }
                } else {
                    document.getElementById('verify-result').textContent = 'You have not voted for this position in this election.';
                }
            } catch (error) {
                document.getElementById('verify-result').textContent = 'Error verifying vote: ' + error.message;
                console.error(error);
            }
        }

        // Load my votes
        async function loadMyVotes() {
            const voterAddress = '<?php echo $_SESSION['wallet_address'] ?? '0x0000000000000000000000000000000000000000'; ?>';
            try {
                const allVotes = await contract.methods.getVotesByElection(1).call(); // Adjust electionId dynamically if needed
                let myVotesHtml = '';
                for (let i = 0; i < allVotes.length; i++) {
                    if (allVotes[i].voter === voterAddress) {
                        myVotesHtml += `<div class="vote-item">
                            <p>Election ID: ${allVotes[i].electionId}</p>
                            <p>Position: ${allVotes[i].positionName}</p>
                            <p>Candidate: ${allVotes[i].candidateName}</p>
                            <p>Time: ${new Date(allVotes[i].timestamp * 1000).toLocaleString()}</p>
                        </div>`;
                    }
                }
                if (myVotesHtml === '') {
                    myVotesHtml = '<p>No votes cast by you yet.</p>';
                }
                myVotesSection.innerHTML = myVotesHtml;
            } catch (error) {
                myVotesSection.innerHTML = '<p class="error">Error loading votes: ' + error.message + '</p>';
                console.error(error);
            }
        }

        // Handling cast vote link (redirect)
        castVoteLink.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.href = `process-vote.php?csrf_token=<?php echo htmlspecialchars($csrf_token); ?>`;
});

        // Load votes on page load
        window.addEventListener('load', loadMyVotes);
    </script>
</body>
</html>
<?php
$conn->close();
?>