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

error_log("Session after validation: user_id=" . ($_SESSION['user_id'] ?? 'unset') . 
          ", role=" . ($_SESSION['role'] ?? 'unset') . 
          ", college_id=" . ($_SESSION['college_id'] ?? 'unset') . 
          ", association=" . ($_SESSION['association'] ?? 'unset'));

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
try {
    $stmt = $conn->prepare("SELECT fname, college_id FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
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

$profile_picture = 'images/general.png';

// Fetch user details for voting
$errors = [];
$elections = [];
$user_details = [];

try {
    $stmt = $conn->prepare(
        "SELECT u.association, u.college_id, c.name AS college_name
         FROM users u
         LEFT JOIN colleges c ON u.college_id = c.college_id
         WHERE u.user_id = ? AND u.active = 1"
    );
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $user_details = $result->fetch_assoc();
    $stmt->close();

    if (!$user_details) {
        $errors[] = "User not found or not active.";
    }
} catch (Exception $e) {
    error_log("Fetch user details failed: " . $e->getMessage());
    $errors[] = "Failed to load user details due to a server error.";
}

if (empty($errors)) {
    $association = $user_details['association'];
    $college_id = $user_details['college_id'];

    // Fetching active elections using prepared statement
    try {
        $stmt = $conn->prepare(
            "SELECT election_id, title
             FROM elections
             WHERE status = ? AND end_time > NOW() AND association = ?
             ORDER BY start_time ASC"
        );
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $status = 'ongoing';
        $stmt->bind_param('ss', $status, $association);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $elections = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // fetching eligible positions and candidates
        foreach ($elections as &$election) {
            $election_id = $election['election_id'];
            $positions = [];

            
            $query = "
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id
                FROM electionpositions ep
                WHERE ep.election_id = ?
                AND ep.position_id IN (
                    SELECT c.position_id
                    FROM candidates c
                    WHERE c.election_id = ? AND c.association = ?
                )
                AND (
                    ep.scope = 'university'
                    OR (ep.scope = 'college' AND ep.college_id = ?)
                )
                ORDER BY ep.position_id";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('iisi', $election_id, $election_id, $association, $college_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            while ($position = $result->fetch_assoc()) {
                $position_id = $position['position_id'];

                $vote_stmt = $conn->prepare(
                    "SELECT 1 FROM votes 
                     WHERE user_id = ? AND election_id = ? AND candidate_id IN (
                         SELECT id FROM candidates WHERE position_id = ?
                     )"
                );
                if (!$vote_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $vote_stmt->bind_param('iii', $user_id, $election_id, $position_id);
                if (!$vote_stmt->execute()) {
                    throw new Exception("Execute failed: " . $vote_stmt->error);
                }
                $vote_result = $vote_stmt->get_result();
                if ($vote_result->num_rows > 0) {
                    $position['already_voted'] = true;
                } else {
                    $position['already_voted'] = false;
                }
                $vote_stmt->close();

                 $cand_stmt = $conn->prepare(
                    "SELECT id, official_id, firstname, lastname, association
                     FROM candidates
                     WHERE election_id = ? AND position_id = ? AND association = ?"
                );
                if (!$cand_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $cand_stmt->bind_param('iis', $election_id, $position_id, $association);
                if (!$cand_stmt->execute()) {
                    throw new Exception("Execute failed: " . $cand_stmt->error);
                }
                $cand_result = $cand_stmt->get_result();
                $candidates = $cand_result->fetch_all(MYSQLI_ASSOC);
                $cand_stmt->close();

                $position['candidates'] = $candidates;
                $positions[] = $position;
            }
            $stmt->close();

            $election['positions'] = $positions;
        }
    } catch (Exception $e) {
        error_log("Fetch elections failed: " . $e->getMessage());
        $errors[] = "Failed to load elections due to a server error: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIVE UDOMASA | Dashboard</title>
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
        }
        .header .user .logout-link:hover {
            background: #e76f51;
        }
        .dashboard {
            padding: 0 20px 40px;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .dash-content {
            max-width: 1200px;
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
            color: transparent;
        }
        .election-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .election-section h3 {
            font-size: 24px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
        }
        .position-section {
            margin: 20px 0;
            padding: 15px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .position-section h4 {
            font-size: 20px;
            color: #1a3c34;
            margin-bottom: 15px;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .candidate-table th, .candidate-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .candidate-table th {
            background: #e0e0e0;
            color: #1a3c34;
            font-weight: 600;
        }
        .candidate-table td {
            background: #ffffff;
        }
        .candidate-table tr:hover {
            background: #f5f5f5;
        }
        button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
            transition: background 0.3s ease;
            display: block;
            margin-left: auto;
        }
        button:hover {
            background: #e76f51;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .error, .success {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
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
            .candidate-table th, .candidate-table td {
                padding: 8px 10px;
                font-size: 14px;
            }
            .dash-content h2 {
                font-size: 24px;
            }
            .election-section h3 {
                font-size: 20px;
            }
            .position-section h4 {
                font-size: 18px;
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
            <a href="#">Verify Vote</a><!--api/blockchain/get-votes.php-->
            <a href="#">Results</a><!--api/vote-analytics.php-->
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
            <h2>Vote for Candidates</h2>

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
                                    <?php if ($position['already_voted']): ?>
                                        <div class="success">
                                            <p>You have already voted for this position.</p>
                                        </div>
                                    <?php elseif (empty($position['candidates'])): ?>
                                        <div class="error">
                                            <p>No candidates available for this position.</p>
                                        </div>
                                    <?php else: ?>
                                        <form class="vote-form" data-election-id="<?php echo $election['election_id']; ?>" data-position-id="<?php echo $position['position_id']; ?>">
                                            <table class="candidate-table">
                                                <thead>
                                                    <tr>
                                                        <th>Official ID</th>
                                                        <th>Full Name</th>
                                                        <th>Association</th>
                                                        <th>Position</th>
                                                        <th>Vote</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($position['candidates'] as $candidate): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($candidate['official_id'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></td>
                                                            <td><?php echo htmlspecialchars($candidate['association'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($position['position_name']); ?></td>
                                                            <td>
                                                                <input type="radio" name="candidate_id" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>" required <?php echo $position['already_voted'] ? 'disabled' : ''; ?>>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="submit" <?php echo $position['already_voted'] ? 'disabled' : ''; ?>>Cast Vote</button>
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

    <script>
        const userId = <?php echo $user_id; ?>;

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

        document.querySelectorAll('.vote-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const electionId = form.getAttribute('data-election-id');
                const positionId = form.getAttribute('data-position-id');
                const candidateId = form.querySelector('input[name="candidate_id"]:checked')?.value;

                if (!candidateId) {
                    showError('Please select a candidate to vote for.');
                    return;
                }

                const submitButton = form.querySelector('button');
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';

                try {
                    const response = await fetch('api/process-vote.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            election_id: electionId,
                            position_id: positionId,
                            candidate_id: candidateId
                        })
                    });

                    const result = await response.json();
                    if (response.ok && result.success) {
                        showSuccess('Vote cast successfully! Transaction Hash: ' + result.txHash);
                        form.querySelectorAll('input[name="candidate_id"]').forEach(radio => {
                            radio.disabled = true;
                        });
                        submitButton.disabled = true;
                        submitButton.textContent = 'Vote Cast';
                    } else {
                        throw new Error(result.error || 'Failed to cast vote.');
                    }
                } catch (error) {
                    showError(error.message);
                } finally {
                    if (!submitButton.disabled) {
                        submitButton.textContent = 'Cast Vote';
                    }
                }
            });
        });

        function showSuccess(message) {
            const successDiv = document.getElementById('success-message');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
        }

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            document.getElementById('success-message').style.display = 'none';
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
$conn->close();
?>