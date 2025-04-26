<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// Internal database connection
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
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id FROM users WHERE user_id = ?");
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
            .overview {
                grid-template-columns: 1fr;
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
            <a href="api/update-upcoming.php">Election</a>
            <a href="api/blockchain/submit-vote.php">Vote</a>
            <a href="api/blockchain/get-votes.php">Verify Vote</a>
            <a href="api/vote-analytics.php">Results</a>
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
            <h2>Election Dashboard</h2>

            <div class="overview">
                <div class="card">
                    <i class="fas fa-users"></i>
                    <span class="text">Election Candidates</span>
                    <span class="number">
                        <?php
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE election_id IN (SELECT id FROM elections WHERE association = ?)");
                            if (!$stmt) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }
                            $association = 'UDOSO';
                            $stmt->bind_param("s", $association);
                            if (!$stmt->execute()) {
                                throw new Exception("Execute failed: " . $stmt->error);
                            }
                            $result = $stmt->get_result();
                            echo $result->fetch_row()[0];
                        } catch (Exception $e) {
                            error_log("Candidates count query error: " . $e->getMessage());
                            echo "N/A";
                        }
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
        const electionIds = <?php
            try {
                $stmt = $conn->prepare("SELECT id FROM elections WHERE association = 'UDOSO' AND end_time > NOW()");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $elections = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode(array_column($elections, 'id'));
            } catch (Exception $e) {
                error_log("Election IDs query error: " . $e->getMessage());
                echo json_encode([]);
            }
        ?>;

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

        function updateTimer() {
            const now = new Date().getTime();
            let earliestEndTime = Infinity;
            <?php
            try {
                $stmt = $conn->prepare("SELECT end_time FROM elections WHERE association = 'UDOSO' AND end_time > NOW()");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $end_times = $result->fetch_all(MYSQLI_ASSOC);
                foreach ($end_times as $et) {
                    echo "earliestEndTime = Math.min(earliestEndTime, new Date('" . $et['end_time'] . "').getTime());";
                }
            } catch (Exception $e) {
                error_log("Timer query error: " . $e->getMessage());
            }
            ?>
            if (earliestEndTime === Infinity) {
                document.getElementById('timer').innerHTML = 'No Active Elections';
                return;
            }
            const distance = earliestEndTime - now;
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

        async function fetchUserVote() {
            try {
                let hashFound = false;
                for (const electionId of electionIds) {
                    const response = await fetch(`/api/blockchain/get-votes.php?user_id=${userId}&election_id=${electionId}`);
                    const data = await response.json();
                    if (data.votes && data.votes.length > 0) {
                        document.getElementById('voting-status').textContent = 'Voted';
                        hashFound = true;
                        break;
                    }
                }
                if (!hashFound) {
                    document.getElementById('voting-status').textContent = 'Not Voted';
                }
            } catch (error) {
                document.getElementById('voting-status').textContent = 'Error';
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
<?php
// Closing the database connection
$conn->close();
?>