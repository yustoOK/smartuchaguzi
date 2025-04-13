<?php
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

$required_college = 'CNMS';
$required_association = 'UDOSO';
$required_role = 'voter';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role || $_SESSION['college'] !== $required_college || $_SESSION['association'] !== $required_association) {
    error_log("Session validation failed: user_id, role, college, or association not set or invalid. Redirecting to login.php");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Please log in to access the ' . $required_college . ' ' . $required_association . ' dashboard.'));
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch. Session destroyed.");
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
$stmt = $pdo->prepare("SELECT fname FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profile_picture = 'images/general.png';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $required_college . ' ' . $required_association; ?> | Dashboard</title>
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
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/<?php echo strtolower($required_college); ?>.jpeg');
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

        .content-section h3 {
            font-size: 22px;
            color: #1a3c34;
            margin-bottom: 20px;
            text-align: center;
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
            transition: transform 0.3s ease;
        }

        .election-card:hover {
            transform: translateY(-5px);
        }

        .election-card h3 {
            font-size: 20px;
            color: #1a3c34;
            margin-bottom: 15px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .election-card .candidate {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .election-card .candidate:hover {
            background: rgba(244, 162, 97, 0.3);
        }

        .election-card .candidate span {
            font-size: 16px;
            color: #2d3748;
        }

        .election-card .candidate a {
            color: #f4a261;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
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
        }

        .vote-section button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .vote-section button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.5);
        }

        .verify-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .verify-section p {
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

        .analytics-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .analytics-section .chart {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .analytics-section .chart:hover {
            transform: translateY(-5px);
        }

        .analytics-section .chart h4 {
            font-size: 18px;
            color: #1a3c34;
            margin-bottom: 10px;
        }

        .analytics-section .chart p {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
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

            .overview,
            .election-cards,
            .analytics-section {
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
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="nav">
            <a href="#" data-section="election" class="active">Election</a>
            <a href="#" data-section="vote">Vote</a>
            <a href="#" data-section="verify">Verify Vote</a>
            <a href="#" data-section="analytics">Analytics</a>
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
            <h2><?php echo $required_college . ' ' . $required_association; ?> Election Dashboard</h2>

            <div class="overview">
                <div class="card">
                    <i class="fas fa-users"></i>
                    <span class="text">Election Candidates</span>
                    <span class="number">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE association = ?");
                        $stmt->execute([$required_association]);
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
                <div class="card">
                    <i class="fas fa-clock"></i>
                    <span class="text">Remaining Time</span>
                    <span class="number" id="timer">Loading...</span>
                </div>
                <div class="card">
                    <i class="fas fa-check-circle"></i>
                    <span class="text">Voting Status</span>
                    <span class="number" id="voting-status">Loading...</span>
                </div>
            </div>

            <div class="content-section active" id="election">
                <h3>Current Election</h3>
                <div class="election-cards">
                    <?php
                    $stmt = $pdo->prepare("SELECT DISTINCT position FROM candidates WHERE association = ?");
                    $stmt->execute([$required_association]);
                    while ($pos = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<div class='election-card'>";
                        echo "<h3>" . htmlspecialchars($pos['position']) . "</h3>";
                        $cand_stmt = $pdo->prepare("SELECT id, firstname, middlename, lastname FROM candidates WHERE position = ? AND association = ?");
                        $cand_stmt->execute([$pos['position'], $required_association]);
                        while ($cand = $cand_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $full_name = $cand['firstname'] . ' ' . ($cand['middlename'] ? $cand['middlename'] . ' ' : '') . $cand['lastname'];
                            echo "<div class='candidate'><span>" . htmlspecialchars($full_name) . "</span><a href='candidate-details.php?id=" . $cand['id'] . "'>Details</a></div>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <div class="content-section" id="vote">
                <h3>Cast Your Vote</h3>
                <div class="vote-section">
                    <table>
                        <tr>
                            <th>Position</th>
                            <th>Candidate</th>
                            <th>Action</th>
                        </tr>
                        <?php
                        $stmt = $pdo->prepare("SELECT DISTINCT position FROM candidates WHERE association = ?");
                        $stmt->execute([$required_association]);
                        while ($pos = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($pos['position']) . "</td>";
                            echo "<td><select name='candidate_" . htmlspecialchars($pos['position']) . "'>";
                            $cand_stmt = $pdo->prepare("SELECT id, firstname, middlename, lastname FROM candidates WHERE position = ? AND association = ?");
                            $cand_stmt->execute([$pos['position'], $required_association]);
                            while ($cand = $cand_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $full_name = $cand['firstname'] . ' ' . ($cand['middlename'] ? $cand['middlename'] . ' ' : '') . $cand['lastname'];
                                echo "<option value='" . $cand['id'] . "'>" . htmlspecialchars($full_name) . "</option>";
                            }
                            echo "</select></td>";
                            echo "<td><button onclick='submitVote(\"" . htmlspecialchars($pos['position']) . "\")'>Vote</button></td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>

            <div class="content-section" id="verify">
                <h3>Verify Your Vote</h3>
                <div class="verify-section">
                    <p>Check your vote's blockchain record to ensure its integrity.</p>
                    <div id="vote-hash" class="hash">Loading...</div>
                </div>
            </div>

            <div class="content-section" id="analytics">
                <h3>Election Analytics</h3>
                <div class="analytics-section">
                    <div class="chart">
                        <h4>Voter Turnout</h4>
                        <p id="voter-turnout">Loading...</p>
                    </div>
                    <div class="chart">
                        <h4>Total Votes</h4>
                        <p id="total-votes">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="quick-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="election-rules.php">Election Rules</a></li>
                    <li><a href="contact.php">Support</a></li>
                </ul>
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
        const college = '<?php echo $required_college; ?>';
        const association = '<?php echo $required_association; ?>';

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

        const electionEnd = new Date('2025-05-15T23:59:59').getTime();

        function updateTimer() {
            const now = new Date().getTime();
            const distance = electionEnd - now;
            if (distance < 0) {
                document.getElementById('timer').innerHTML = 'Election Ended';
                return;
            }
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            document.getElementById('timer').innerHTML = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateTimer, 1000);
        updateTimer();

        async function submitVote(position) {
            const candidateId = document.querySelector(`select[name="candidate_${position}"]`).value;
            const response = await fetch('/api/blockchain/submit-vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    candidate_id: candidateId
                })
            });
            if (response.ok) {
                const result = await response.json();
                alert('Vote submitted successfully! Transaction Hash: ' + result.blockchain_hash);
                location.reload();
            } else {
                const error = await response.json();
                alert('Error submitting vote: ' + (error.message || 'Unknown error'));
            }
        }

        async function fetchUserVote() {
            const response = await fetch(`/api/blockchain/get-votes.php?user_id=${userId}`);
            if (response.ok) {
                const data = await response.json();
                if (data.votes && data.votes.length > 0) {
                    document.getElementById('vote-hash').textContent = data.votes[0].blockchain_hash;
                    document.getElementById('voting-status').textContent = 'Voted';
                } else {
                    document.getElementById('vote-hash').textContent = 'No vote recorded yet.';
                    document.getElementById('voting-status').textContent = 'Not Voted';
                }
            } else {
                document.getElementById('vote-hash').textContent = 'Error fetching vote data.';
                document.getElementById('voting-status').textContent = 'Error';
            }
        }

        async function fetchAnalytics() {
            const response = await fetch('/api/blockchain/get-votes.php');
            if (response.ok) {
                const data = await response.json();
                const totalVotes = data.votes ? data.votes.length : 0;

                const totalUsersResponse = await fetch(`/api/get-total-users.php?college=${college}&association=${association}`);
                const totalUsersData = await totalUsersResponse.json();
                const totalUsers = totalUsersData.total_users || 0;

                document.getElementById('total-votes').textContent = totalVotes;
                document.getElementById('voter-turnout').textContent = totalUsers > 0 ? (totalVotes / totalUsers * 100).toFixed(1) + '%' : '0%';
            } else {
                document.getElementById('total-votes').textContent = 'Error';
                document.getElementById('voter-turnout').textContent = 'Error';
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

        fetchUserVote();
        fetchAnalytics();
    </script>
</body>

</html>