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
    die("Connection failed: " . $e->getMessage());
}

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default profile picture if none exists
$profile_picture = $user['profile_picture'] ?? 'images/general.png';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = 'uploads/passports/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['profile_picture'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png'];

    if (in_array($file_ext, $allowed_exts)) {
        $new_file_name = $user_id . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $destination)) {
            // Update the database with the new profile picture path
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$destination, $user_id]);

            // Log the action
            $action = "User updated profile picture: {$user['email']}";
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $action]);

            // Update the session variable
            $profile_picture = $destination;
            header("Location: admin-dashboard.php?success=" . urlencode("Profile picture updated successfully."));
            exit;
        } else {
            $error = "Failed to upload the profile picture.";
        }
    } else {
        $error = "Only JPG, JPEG, and PNG files are allowed.";
    }
}
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
            top: 50px;
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

        .header .user .dropdown a,
        .header .user .dropdown label {
            color: #e6e6e6;
            padding: 10px 20px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .header .user .dropdown a:hover,
        .header .user .dropdown label:hover {
            background: rgba(244, 162, 97, 0.3);
        }

        .header .user .dropdown input[type="file"] {
            display: none;
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
            margin: 10px 0;
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

        .success-message {
            color: #1a3c34;
            background: rgba(26, 60, 52, 0.2);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #1a3c34;
            text-align: center;
        }

        .error-message {
            color: #e76f51;
            background: rgba(231, 111, 81, 0.2);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #e76f51;
            text-align: center;
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
                <form action="admin-dashboard.php" method="POST" enctype="multipart/form-data">
                    <label for="profile_picture_upload">Upload Profile Picture</label>
                    <input type="file" id="profile_picture_upload" name="profile_picture" accept="image/jpeg,image/png">
                </form>
                <a href="admin-profile.php">My Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <section class="dashboard">
        <div class="dash-content">
            <h2>Admin Dashboard</h2>
            <?php if (isset($_GET['success'])): ?>
                <p class="success-message">
                    <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
                </p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </p>
            <?php endif; ?>

            <div class="content-section active" id="management">
                <h3>Election Management</h3>
                <div class="management-section">
                    <button onclick="window.location.href='add-election.php'">Add New Election</button>
                    <button onclick="window.location.href='edit-election.php'">Edit Existing Election</button>
                    <button onclick="window.location.href='manage-candidates.php'">Manage Candidates</button>
                </div>
            </div>

            <div class="content-section" id="upcoming">
                <h3>Update Upcoming Elections</h3>
                <div class="upcoming-section">
                    <form action="/api/update-upcoming.php" method="POST">
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
                    <button onclick="window.location.href='add-user.php'">Add New User</button>
                    <button onclick="window.location.href='edit-user.php'">Edit User</button>
                    <button onclick="window.location.href='assign-observer.php'">Assign Observer</button>
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
        profilePic.addEventListener('click', () => {
            dropdown.classList.toggle('active');
        });

        // Auto-submit form when file is selected
        const fileInput = document.getElementById('profile_picture_upload');
        fileInput.addEventListener('change', () => {
            fileInput.closest('form').submit();
        });
    </script>
</body>

</html>