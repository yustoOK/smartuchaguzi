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
    die("Connection failed. Please try again later.");
}

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Session validation failed: user_id or role not set. Redirecting to login.php");
    header('Location: login.php?error=' . urlencode('Please log in to access the admin dashboard.'));
    exit;
}

// Validating session integrity (e.g., check user agent to detect session hijacking)
if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("Session hijacking detected: user agent mismatch. Session destroyed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

// Session timeout settings
$inactivity_timeout = 5 * 60; // 5 minutes in seconds
$max_session_duration = 30 * 60; // 30 minutes in seconds
$warning_time = 60; // 1 minute before logout for warning

// Initialize session start time if not set
if (!isset($_SESSION['start_time'])) {
    error_log("Session start_time not set. Initializing now.");
    $_SESSION['start_time'] = time();
}

// Initialize last activity time if not set
if (!isset($_SESSION['last_activity'])) {
    error_log("Session last_activity not set. Initializing now.");
    $_SESSION['last_activity'] = time();
}

// Check session start time (maximum session duration)
$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= $max_session_duration) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to maximum duration. Please log in again.'));
    exit;
}

// Check for inactivity
$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= $inactivity_timeout) {
    error_log("Session expired due to inactivity: $inactive_time seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.'));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default profile picture
$profile_picture = 'images/general.png';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Dashboard</title>
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
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="nav">
            <a href="#" data-section="management" class="active">Management</a>
            <a href="#" data-section="upcoming">Upcoming Elections</a>
            <a href="#" data-section="users">Users</a>
            <a href="#" data-section="analytics">Analytics</a>
            <a href="#" data-section="audit">Audit Log</a>
        </div>
        <div class="user">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic">
            <div class="dropdown" id="user-dropdown">
                <span style="color: #e6e6e6; padding: 10px 20px;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Admin'); ?></span>
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
                        <button onclick="window.location.href='add-election.php'">Add New Election</button>
                        <button onclick="window.location.href='edit-election.php'">Edit Existing Election</button>
                        <button onclick="window.location.href='manage-candidates.php'">Manage Candidates</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="upcoming">
                <h3>Update Upcoming Elections</h3>
                <div class="upcoming-section">
                    <form action="./admin-operations/update-upcoming.php" method="POST">
                        <input type="text" name="title" placeholder="Election Title" required>
                        <input type="date" name="date" required>
                        <textarea name="description" placeholder="Election Description" rows="4" required></textarea>
                        <button type="submit">Add to Upcoming Elections</button>
                    </form>
                </div>
            </div>

            <div class="content-section" id="users">
                <h3>User Management</h3>
                <div class="user-section">
                    <div class="action-buttons">
                        <button onclick="window.location.href='add-user.php'">Add New User</button>
                        <button onclick="window.location.href='edit-user.php'">Edit User</button>
                        <button onclick="window.location.href='assign-observer.php'">Assign Observer</button>
                    </div>
                </div>
            </div>

            <div class="content-section" id="analytics">
                <h3>Election Analytics</h3>
                <div class="overview">
                    <div class="card">
                        <i class="fas fa-users"></i>
                        <span class="text">Total Candidates</span>
                        <span class="number">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM candidates");
                            echo $stmt->fetchColumn();
                            ?>
                        </span>
                    </div>
                    <div class="card">
                        <i class="fas fa-vote-yea"></i>
                        <span class="text">Total Votes</span>
                        <span class="number">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM votes");
                            echo $stmt->fetchColumn();
                            ?>
                        </span>
                    </div>
                    <div class="card">
                        <i class="fas fa-clock"></i>
                        <span class="text">Active Elections</span>
                        <span class="number">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM elections WHERE end_date > NOW()");
                            echo $stmt->fetchColumn();
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="content-section" id="audit">
                <h3>Audit Log</h3>
                <div class="audit-section">
                    <?php
                    $stmt = $pdo->prepare("SELECT action, created_at FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                    $stmt->execute([$user_id]);
                    while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<div class='log'>" . htmlspecialchars($log['created_at']) . " - " . htmlspecialchars($log['action']) . "</div>";
                    }
                    ?>
                </div>
            </div>

            <div class="quick-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="admin-profile.php">My Profile</a></li>
                    <li><a href="system-settings.php">System Settings</a></li>
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
        // Navigation section toggle
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

        // Dropdown toggle
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

        // Session timeout logic
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
                window.location.href = 'login.php?error=' + encodeURIComponent('Session expired due to maximum duration. Please log in again.');
            }

            if (inactiveTime >= inactivityTimeout - warningTime && inactiveTime < inactivityTimeout) {
                timeoutMessage.textContent = "You will be logged out in 1 minute due to inactivity.";
                modal.style.display = 'flex';
            } else if (inactiveTime >= inactivityTimeout) {
                window.location.href = 'login.php?error=' + encodeURIComponent('Session expired due to inactivity. Please log in again.');
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
    </script>
</body>

</html>