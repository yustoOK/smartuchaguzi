<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter' || $_SESSION['college'] !== 'CIVE' || $_SESSION['association'] !== 'UDOSO') {
    error_log("Session validation failed: user_id, role, college, or association not set or invalid.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Please log in to access the CIVE UDOSO dashboard.'));
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
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
$stmt = $pdo->prepare("SELECT fname, college_id, hostel_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = 'images/general.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIVE UDOSO | Dashboard</title>
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
            padding: 100px 20px 20px;
        }
        .dash-content {
            max-width: 800px;
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
            color: transparent;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .content-section h3 {
            font-size: 22px;
            color: #1a3c34;
            margin-bottom: 20px;
        }
        .election-card, .results-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .election-card h4, .results-card h4 {
            font-size: 18px;
            color: #1a3c34;
            margin-bottom: 10px;
        }
        .election-card .candidate {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .election-card .candidate span {
            font-size: 16px;
            color: #2d3748;
        }
        .election-card .candidate a {
            color: #f4a261;
            text-decoration: none;
            font-weight: 500;
        }
        .election-card .candidate a:hover {
            color: #e76f51;
        }
        .vote-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .vote-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .vote-section th {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 12px;
        }
        .vote-section td {
            padding: 12px;
            border-bottom: 1px solid #e8ecef;
        }
        .vote-section select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #e8ecef;
            background: rgba(255, 255, 255, 0.1);
            color: #2d3748;
            width: 100%;
        }
        .vote-section button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        .vote-section button:hover {
            transform: scale(1.05);
        }
        .verify-section, .results-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .verify-section p, .results-section p {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 20px;
        }
        .verify-section .hash {
            font-family: monospace;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 8px;
            word-break: break-all;
            color: #1a3c34;
        }
        .results-card p {
            font-size: 16px;
            color: #2d3748;
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
            <a href="#" data-section="election" class="active">Election</a>
            <a href="#" data-section="vote">Vote</a>
            <a href="#" data-section="verify">Verify Vote</a>
            <a href="#" data-section="results">Results</a>
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
            <h2>CIVE UDOSO Election Dashboard</h2>

            <div class="content-section active" id="election">
                <h3>All UDOSO Elections</h3>
                <?php
                $stmt = $pdo->prepare("SELECT e.id, e.association, e.end_time, c.name AS college_name 
                                       FROM elections e 
                                       LEFT JOIN colleges c ON e.college_id = c.id 
                                       WHERE e.association = 'UDOSO' AND e.end_time > NOW()");
                $stmt->execute();
                $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($elections) {
                    foreach ($elections as $election) {
                        echo "<div class='election-card'>";
                        echo "<h4>" . ($election['college_name'] ? htmlspecialchars($election['college_name']) : 'University-Wide') . " Election</h4>";
                        $stmt = $pdo->prepare("SELECT ep.id, ep.name FROM election_positions ep WHERE ep.election_id = ?");
                        $stmt->execute([$election['id']]);
                        while ($pos = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<h5>" . htmlspecialchars($pos['name']) . "</h5>";
                            $cand_stmt = $pdo->prepare("SELECT c.id, u.fname, u.lname 
                                                       FROM candidates c 
                                                       JOIN users u ON c.user_id = u.id 
                                                       WHERE c.election_id = ? AND c.position_id = ?");
                            $cand_stmt->execute([$election['id'], $pos['id']]);
                            while ($cand = $cand_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $full_name = $cand['fname'] . ' ' . $cand['lname'];
                                echo "<div class='candidate'><span>" . htmlspecialchars($full_name) . "</span><a href='candidate-details.php?id=" . $cand['id'] . "'>Details</a></div>";
                            }
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>No active UDOSO elections.</p>";
                }
                ?>
            </div>

            <div class="content-section" id="vote">
                <h3>Cast Your Vote</h3>
                <div class="vote-section">
                    <?php
                    $stmt = $pdo->prepare("SELECT e.id, e.end_time 
                                           FROM elections e 
                                           WHERE e.association = 'UDOSO' AND e.end_time > NOW() 
                                           AND (e.college_id = ? OR e.college_id IS NULL)");
                    $stmt->execute([$user['college_id']]);
                    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($elections) {
                        echo "<table>";
                        echo "<tr><th>Position</th><th>Candidate</th><th>Action</th></tr>";
                        foreach ($elections as $election) {
                            $stmt = $pdo->prepare("SELECT ep.id, ep.name 
                                                   FROM election_positions ep 
                                                   WHERE ep.election_id = ?");
                            $stmt->execute([$election['id']]);
                            while ($pos = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $is_hostel_position = strpos(strtolower($pos['name']), 'hostel') !== false;
                                if ($is_hostel_position && $user['hostel_id']) {
                                    $cand_stmt = $pdo->prepare("SELECT c.id 
                                                               FROM candidates c 
                                                               WHERE c.election_id = ? AND c.position_id = ? 
                                                               AND c.hostel_id = ?");
                                    $cand_stmt->execute([$election['id'], $pos['id'], $user['hostel_id']]);
                                } else {
                                    $cand_stmt = $pdo->prepare("SELECT c.id 
                                                               FROM candidates c 
                                                               WHERE c.election_id = ? AND c.position_id = ?");
                                    $cand_stmt->execute([$election['id'], $pos['id']]);
                                }
                                if (!$cand_stmt->fetch()) {
                                    continue;
                                }

                                $vote_check = $pdo->prepare("SELECT id 
                                                             FROM votes 
                                                             WHERE user_id = ? AND election_id = ? 
                                                             AND candidate_id IN (SELECT id FROM candidates WHERE position_id = ?)");
                                $vote_check->execute([$user_id, $election['id'], $pos['id']]);
                                if ($vote_check->fetch(PDO::FETCH_ASSOC)) {
                                    continue;
                                }

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($pos['name']) . "</td>";
                                echo "<td><select name='candidate_" . $pos['id'] . "' id='candidate_" . $pos['id'] . "'>";
                                $cand_stmt = $pdo->prepare("SELECT c.id, u.fname, u.lname 
                                                           FROM candidates c 
                                                           JOIN users u ON c.user_id = u.id 
                                                           WHERE c.election_id = ? AND c.position_id = ?" . ($is_hostel_position && $user['hostel_id'] ? " AND c.hostel_id = ?" : ""));
                                $params = [$election['id'], $pos['id']];
                                if ($is_hostel_position && $user['hostel_id']) {
                                    $params[] = $user['hostel_id'];
                                }
                                $cand_stmt->execute($params);
                                while ($cand = $cand_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $full_name = $cand['fname'] . ' ' . $cand['lname'];
                                    echo "<option value='" . $cand['id'] . "'>" . htmlspecialchars($full_name) . "</option>";
                                }
                                echo "</select></td>";
                                echo "<td><button onclick='submitVote(" . $pos['id'] . ")'>Vote</button></td>";
                                echo "</tr>";
                            }
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No active UDOSO elections to vote in for your college or hostel.</p>";
                    }
                    ?>
                </div>
            </div>

            <div class="content-section" id="verify">
                <h3>Verify Your Vote</h3>
                <div class="verify-section">
                    <p>Check your vote's blockchain record.</p>
                    <div id="vote-hash" class="hash">Loading...</div>
                </div>
            </div>

            <div class="content-section" id="results">
                <h3>UDOSO Election Results</h3>
                <div class="results-section">
                    <?php
                    $stmt = $pdo->prepare("SELECT id, end_time 
                                           FROM elections 
                                           WHERE association = 'UDOSO' AND end_time < NOW() 
                                           ORDER BY end_time DESC");
                    $stmt->execute();
                    $past_elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($past_elections) {
                        foreach ($past_elections as $past_election) {
                            $analytics_response = file_get_contents("http://localhost/smartuchaguzi/api/vote-analytics.php?election_id=" . $past_election['id']);
                            $analytics_data = json_decode($analytics_response, true);
                            if (isset($analytics_data['positions']) && !empty($analytics_data['positions'])) {
                                echo "<div class='results-card'>";
                                echo "<h4>Election " . $past_election['id'] . "</h4>";
                                foreach ($analytics_data['positions'] as $position) {
                                    echo "<p>" . htmlspecialchars($position['name']) . ": " . ($position['winner'] ? htmlspecialchars($position['winner']) : 'None') . "</p>";
                                }
                                echo "</div>";
                            }
                        }
                    } else {
                        echo "<p>No UDOSO elections have concluded.</p>";
                    }
                    ?>
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
        const userId = <?php echo $user_id; ?>;
        const electionIds = <?php echo json_encode(array_column($elections, 'id')); ?>;

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

        async function submitVote(positionId) {
            const candidateId = document.getElementById(`candidate_${positionId}`).value;
            try {
                const response = await fetch('/api/blockchain/submit-vote.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, candidate_id: candidateId })
                });
                const result = await response.json();
                if (result.success) {
                    alert(`Vote submitted successfully! Transaction Hash: ${result.blockchain_hash}`);
                    location.reload();
                } else {
                    alert(`Error submitting vote: ${result.error}`);
                }
            } catch (error) {
                alert('Error submitting vote: Network issue');
            }
        }

        async function fetchUserVote() {
            try {
                let hashFound = false;
                for (const electionId of electionIds) {
                    const response = await fetch(`/api/blockchain/get-votes.php?user_id=${userId}&election_id=${electionId}`);
                    const data = await response.json();
                    if (data.votes && data.votes.length > 0) {
                        document.getElementById('vote-hash').textContent = data.votes[0].block_hash;
                        hashFound = true;
                        break;
                    }
                }
                if (!hashFound) {
                    document.getElementById('vote-hash').textContent = 'No vote recorded yet.';
                }
            } catch (error) {
                document.getElementById('vote-hash').textContent = 'Error fetching vote data.';
            }
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
        fetchUserVote();
    </script>
</body>
</html>