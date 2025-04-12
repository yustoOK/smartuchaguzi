<?php
// Setting session timeouts
session_start();

// Session timeout settings
$inactivity_timeout = 5 * 60; // 5 minutes in seconds of inactivity
$absolute_timeout = 30 * 60; // 30 minutes in seconds of general timeout time

// Check absolute timeout (30 minutes since login)
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

if (time() - $_SESSION['login_time'] > $absolute_timeout) {
    session_destroy();
    header("Location: login.html?message=Session expired please log in again.");
    exit();
}

// Check inactivity timeout (5 minutes)
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

if (time() - $_SESSION['last_activity'] > $inactivity_timeout) {
    session_destroy();
    header("Location: login.html?message=Session expired please log in again.");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['category'] !== 'user-L3') {
    header("Location: login.html");
    exit();
}

require 'db.php';
require 'tcpdf/tcpdf.php'; // Includes TCPDF

// Fetch elections
$stmt = $pdo->prepare("SELECT * FROM elections");
$stmt->execute();
$elections = $stmt->fetchAll();

// Default to the first election if none selected
$selected_election_id = $_SESSION['selected_election_id'] ?? $elections[0]['id'] ?? null;
if (isset($_POST['election_id'])) {
    $selected_election_id = $_POST['election_id'];
    $_SESSION['selected_election_id'] = $selected_election_id;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$username = $user['username'];
$profile_picture = $user['profile_picture'] ?? 'uploads/passports/default.png';

// Fetch candidates for the selected election
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ?");
$stmt->execute([$selected_election_id]);
$candidates = $stmt->fetchAll();

// Fetch voting results for the selected election
$stmt = $pdo->prepare("SELECT c.id, c.full_name, c.party, COUNT(v.id) as vote_count 
                       FROM candidates c 
                       LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ? 
                       WHERE c.election_id = ?
                       GROUP BY c.id");
$stmt->execute([$selected_election_id, $selected_election_id]);
$results = $stmt->fetchAll();

// Prepare data for the chart
$labels = [];
$vote_counts = [];
$colors = [];
foreach ($results as $result) {
    $labels[] = $result['full_name'] . ' (' . $result['party'] . ')';
    $vote_counts[] = $result['vote_count'];
    $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Fetch users (for user management)
$stmt = $pdo->prepare("SELECT * FROM users");
$stmt->execute();
$users = $stmt->fetchAll();

// Fetch logs
$stmt = $pdo->prepare("SELECT l.*, u.username FROM logs l JOIN users u ON l.user_id = u.id ORDER BY l.timestamp DESC");
$stmt->execute();
$logs = $stmt->fetchAll();

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle candidate addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_candidate'])) {
    if (!isset($_SESSION['csrf_token'])) {
        $message = "<p style='color: red; text-align: center;'>Session expired. Please log in again.</p>";
        session_destroy();
        header("Refresh: 2; url=login.html");
        exit();
    }

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "<p style='color: red; text-align: center;'>CSRF token validation failed.</p>";
    } else {
        // Password confirmation for critical operation
        if (!isset($_POST['confirm_password']) || !password_verify($_POST['confirm_password'], $user['password'])) {
            $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
        } else {
            $full_name = trim($_POST['full_name']);
            $party = trim($_POST['party']);

            if (empty($full_name) || empty($party)) {
                $message = "<p style='color: red; text-align: center;'>All fields are required.</p>";
            } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] == UPLOAD_ERR_NO_FILE) {
                $message = "<p style='color: red; text-align: center;'>Profile photo is required.</p>";
            } else {
                $photo = $_FILES['profile_photo'];
                $allowedTypes = ['image/jpeg', 'image/png'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                if (!in_array($photo['type'], $allowedTypes)) {
                    $message = "<p style='color: red; text-align: center;'>Profile photo must be a JPEG or PNG image.</p>";
                } elseif ($photo['size'] > $maxSize) {
                    $message = "<p style='color: red; text-align: center;'>Profile photo must be less than 2MB.</p>";
                } else {
                    $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid('candidate_') . '.' . $ext;
                    $photoPath = 'uploads/candidates/' . $fileName;
                    if (move_uploaded_file($photo['tmp_name'], $photoPath)) {
                        $stmt = $pdo->prepare("INSERT INTO candidates (full_name, party, profile_photo, election_id) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$full_name, $party, $photoPath, $selected_election_id]);
                        logAction($pdo, $_SESSION['user_id'], 'Add Candidate', "Added candidate: $full_name ($party) to election ID: $selected_election_id");
                        header("Refresh:0");
                        exit();
                    } else {
                        $message = "<p style='color: red; text-align: center;'>Failed to upload profile photo.</p>";
                    }
                }
            }
        }
    }
}

// Handle candidate deletion
if (isset($_GET['delete_candidate'])) {
    if (!isset($_GET['confirm_password']) || !password_verify($_GET['confirm_password'], $user['password'])) {
        $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
    } else {
        $candidate_id = $_GET['delete_candidate'];
        $stmt = $pdo->prepare("SELECT full_name, profile_photo FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        $candidate = $stmt->fetch();
        if ($candidate) {
            unlink($candidate['profile_photo']);
            $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
            $stmt->execute([$candidate_id]);
            logAction($pdo, $_SESSION['user_id'], 'Delete Candidate', "Deleted candidate: {$candidate['full_name']}");
            header("Location: admin_dashboard.php");
            exit();
        }
    }
}

// Handle election deletion
if (isset($_GET['delete_election'])) {
    if (!isset($_GET['confirm_password']) || !password_verify($_GET['confirm_password'], $user['password'])) {
        $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
    } else {
        $election_id = $_GET['delete_election'];
        $stmt = $pdo->prepare("SELECT name FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        if ($election) {
            $stmt = $pdo->prepare("DELETE FROM elections WHERE id = ?");
            $stmt->execute([$election_id]);
            logAction($pdo, $_SESSION['user_id'], 'Delete Election', "Deleted election: {$election['name']}");
            // If the deleted election was the selected one, reset the selection
            if ($selected_election_id == $election_id) {
                unset($_SESSION['selected_election_id']);
                $stmt = $pdo->prepare("SELECT id FROM elections LIMIT 1");
                $stmt->execute();
                $first_election = $stmt->fetch();
                if ($first_election) {
                    $_SESSION['selected_election_id'] = $first_election['id'];
                }
            }
            header("Location: admin_dashboard.php");
            exit();
        }
    }
}

// Handle candidate update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_candidate'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    if (!isset($_POST['confirm_password']) || !password_verify($_POST['confirm_password'], $user['password'])) {
        $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
    } else {
        $candidate_id = $_POST['candidate_id'];
        $full_name = trim($_POST['full_name']);
        $party = trim($_POST['party']);

        if (empty($full_name) || empty($party)) {
            $message = "<p style='color: red; text-align: center;'>All fields are required.</p>";
        } else {
            $stmt = $pdo->prepare("UPDATE candidates SET full_name = ?, party = ? WHERE id = ?");
            $stmt->execute([$full_name, $party, $candidate_id]);
            logAction($pdo, $_SESSION['user_id'], 'Update Candidate', "Updated candidate: $full_name ($party)");
            header("Refresh:0");
            exit();
        }
    }
}

// Handle user deletion
if (isset($_GET['delete_user'])) {
    if (!isset($_GET['confirm_password']) || !password_verify($_GET['confirm_password'], $user['password'])) {
        $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
    } else {
        $user_id = $_GET['delete_user'];
        if ($user_id == $_SESSION['user_id']) {
            $message = "<p style='color: red; text-align: center;'>You cannot delete your own account.</p>";
        } else {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();
            if ($target_user) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                logAction($pdo, $_SESSION['user_id'], 'Delete User', "Deleted user: {$target_user['username']}");
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }
}

// Handle vote reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_votes'])) {
    if (!isset($_SESSION['csrf_token'])) {
        $message = "<p style='color: red; text-align: center;'>Session expired. Please log in again.</p>";
        session_destroy();
        header("Refresh: 2; url=login.html");
        exit();
    }

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "<p style='color: red; text-align: center;'>CSRF token validation failed.</p>";
    } else {
        if (!isset($_POST['confirm_password']) || !password_verify($_POST['confirm_password'], $user['password'])) {
            $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM votes WHERE election_id = ?");
            $stmt->execute([$selected_election_id]);
            logAction($pdo, $_SESSION['user_id'], 'Reset Votes', "Reset all votes for election ID: $selected_election_id");
            header("Refresh:0");
            exit();
        }
    }
}

// Handle report generation
if (isset($_GET['generate_report'])) {
    if (!isset($_GET['confirm_password']) || !password_verify($_GET['confirm_password'], $user['password'])) {
        $message = "<p style='color: red; text-align: center;'>Incorrect password. Operation terminated.</p>";
    } else {
        // Fetch the selected election name
        $stmt = $pdo->prepare("SELECT name FROM elections WHERE id = ?");
        $stmt->execute([$selected_election_id]);
        $election = $stmt->fetch();
        $election_name = $election['name'] ?? 'Unknown Election';

        // Create new PDF document
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('We Vote System');
        $pdf->SetTitle('Election Results Report');
        $pdf->SetSubject('Election Results');
        $pdf->SetKeywords('Election, Results, Report');

        // Set default header data
        $pdf->SetHeaderData('', 0, 'Election Results Report', 'Generated on ' . date('Y-m-d H:i:s'));

        // Set margins
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 12);

        // Add content
        $html = '<h1>General Election Results Report</h1>';
        $html .= '<p>Election: ' . htmlspecialchars($election_name) . '</p>';
        $html .= '<p>Generated by: ' . htmlspecialchars($username) . '</p>';
        $html .= '<p>Date: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '<h2>Election Results</h2>';
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr><th>Candidate</th><th>Party</th><th>Votes</th></tr>';
        foreach ($results as $result) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($result['full_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($result['party']) . '</td>';
            $html .= '<td>' . $result['vote_count'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Log the action
        logAction($pdo, $_SESSION['user_id'], 'Generate Report', "Generated election results report");

        // Output the PDF
        $pdf->Output('election_results_report.pdf', 'D');
        exit();
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.html");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard - SmartUchaguzi</title>
    <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('./uploads/Vote.jpeg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            transition: background-color 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body.dark-mode {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('./uploads/Vote.jpeg') no-repeat center center fixed;
            color: #e0e0e0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2c7873;
            color: white;
            padding: 8px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            height: 60px;
        }

        .header .left {
            display: flex;
            align-items: center;
        }

        .header h1 {
            font-size: 20px;
            margin-left: 10px;
        }

        .welcome-message {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-size: 16px;
            opacity: 0;
            animation: fadeInWelcome 1.5s ease-in forwards;
        }

        @keyframes fadeInWelcome {
            0% {
                opacity: 0;
                transform: translateX(-50%) translateY(-10px);
            }

            100% {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            transition: transform 0.3s ease;
        }

        .profile-pic:hover {
            transform: scale(1.1);
        }

        .profile p {
            margin: 0;
            font-size: 14px;
        }

        .logout-btn {
            background-color: #ff6f61;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #e65b50;
            transform: translateY(-2px);
        }

        .sidebar {
            position: fixed;
            top: 60px;
            left: 0;
            width: 250px;
            height: calc(100vh - 60px);
            background-color: #1f5a54;
            color: white;
            padding-top: 20px;
            transform: translateX(-250px);
            /* Fixed typo: translatorsX -> translateX */
            transition: transform 0.3s ease;
            z-index: 999;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .hamburger {
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: white;
            margin-right: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .sidebar ul li {
            padding: 10px 20px;
        }

        .sidebar ul li a {
            display: block;
            text-decoration: none;
            color: white;
            background: #2c7873;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
            text-align: center;
        }

        .sidebar ul li a:hover {
            background: #1f5a54;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .main-content {
            margin-top: 60px;
            margin-left: 0;
            padding: 20px;
            transition: margin-left 0.3s ease;
            flex: 1;
        }

        .main-content.shifted {
            margin-left: 250px;
        }

        .section {
            display: none;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: opacity 0.3s ease;
        }

        body.dark-mode .section {
            background: rgba(42, 42, 42, 0.95);
        }

        .section.active {
            display: block;
            opacity: 1;
        }

        h2 {
            color: #2c7873;
            margin-bottom: 20px;
        }

        body.dark-mode h2 {
            color: #66b0a8;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }

        body.dark-mode .form-group label {
            color: #b0b0b0;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: border-color 0.3s ease;
        }

        .form-group input[type="file"] {
            padding: 3px;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2c7873;
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            background-color: #2c7873;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .btn:hover {
            background-color: #1f5a54;
            transform: translateY(-2px);
        }

        .delete-btn {
            color: #ff6f61;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .delete-btn:hover {
            color: #e65b50;
            text-decoration: underline;
        }

        .result-item,
        .log-item,
        .user-item,
        .election-item {
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-item:last-child,
        .log-item:last-child,
        .user-item:last-child,
        .election-item:last-child {
            border-bottom: none;
        }

        .result-item p,
        .log-item p,
        .user-item p,
        .election-item p {
            margin: 5px 0;
            color: #666;
        }

        body.dark-mode .result-item p,
        body.dark-mode .log-item p,
        body.dark-mode .user-item p,
        body.dark-mode .election-item p {
            color: #b0b0b0;
        }

        .analytics-btn {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #ff6f61;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .analytics-btn:hover {
            background-color: #e65b50;
            transform: translateY(-2px);
        }

        .chart-container {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: opacity 0.3s ease;
        }

        body.dark-mode .chart-container {
            background: rgba(42, 42, 42, 0.95);
        }

        .chart-container.active {
            display: block;
            opacity: 1;
        }

        canvas {
            max-width: 100%;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            transition: opacity 0.3s ease;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 300px;
            margin: 15% auto;
            text-align: center;
            transition: transform 0.3s ease;
        }

        body.dark-mode .modal-content {
            background: #2a2a2a;
        }

        .modal-content input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .modal-content button {
            margin: 5px;
        }

        .error {
            color: #ff6f61;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 18px;
            }

            .welcome-message {
                font-size: 14px;
            }

            .profile-pic {
                width: 40px;
                height: 40px;
            }

            .profile p {
                font-size: 12px;
            }

            .logout-btn {
                padding: 4px 8px;
                font-size: 10px;
            }

            .sidebar {
                width: 200px;
            }

            .main-content.shifted {
                margin-left: 200px;
            }

            .vote-table table,
            .candidate-list,
            .user-list,
            .election-list {
                font-size: 14px;
            }

            .vote-table th,
            .vote-table td {
                padding: 8px;
            }

            .vote-table .candidate-pic {
                width: 40px;
                height: 40px;
            }

            .btn,
            .analytics-btn {
                padding: 8px 16px;
                font-size: 14px;
            }

            .modal-content {
                max-width: 250px;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 6px 15px;
                height: 50px;
            }

            .header h1 {
                font-size: 16px;
            }

            .welcome-message {
                font-size: 12px;
            }

            .hamburger {
                font-size: 20px;
            }

            .profile-pic {
                width: 35px;
                height: 35px;
            }

            .profile p {
                font-size: 10px;
            }

            .logout-btn {
                padding: 3px 6px;
                font-size: 9px;
            }

            .sidebar {
                width: 180px;
            }

            .main-content.shifted {
                margin-left: 180px;
            }

            .section {
                padding: 15px;
            }

            h2 {
                font-size: 20px;
            }

            .form-group input,
            .form-group select {
                padding: 8px;
                font-size: 14px;
            }

            .vote-table table,
            .candidate-list,
            .user-list,
            .election-list {
                font-size: 12px;
            }

            .vote-table th,
            .vote-table td {
                padding: 6px;
            }

            .vote-table .candidate-pic {
                width: 30px;
                height: 30px;
            }

            .btn,
            .analytics-btn {
                padding: 6px 12px;
                font-size: 12px;
            }

            .modal-content {
                max-width: 200px;
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="left">
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
            <h1>SmartUchaguzi</h1>
        </div>
        <span class="welcome-message">Welcome, <?php echo htmlspecialchars($username); ?>! 👋</span>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
            <p><?php echo htmlspecialchars($username); ?></p>
            <form method="POST" style="display: inline;">
                <button type="submit" name="logout" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <div style="padding: 15px 20px;">
            <label for="election_select" style="color: white;">Select Election:</label>
            <form method="POST">
                <select id="election_select" name="election_id" onchange="this.form.submit()" style="width: 100%; padding: 5px; border-radius: 4px;">
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>" <?php echo $selected_election_id == $election['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($election['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <ul>
            <li><a href="#" onclick="showSection('manage-candidates')">Manage Candidates</a></li>
            <li><a href="#" onclick="showSection('manage-users')">Manage Users</a></li>
            <li><a href="#" onclick="showSection('view-results')">View Results</a></li>
            <li><a href="#" onclick="showSection('system-logs')">System Logs</a></li>
            <li><a href="#" onclick="showSection('system-settings')">System Settings</a></li>
            <li><a href="#" onclick="showSection('create-election')">Create Election</a></li>
            <li><a href="#" onclick="showSection('manage-elections')">Manage Elections</a></li>
        </ul>
    </div>

    <div class="main-content" id="mainContent">
        <?php if (isset($message)) echo "<p class='error'>$message</p>"; ?>
        <div class="section active" id="manage-candidates">
            <h2>Manage Candidates</h2>
            <form method="POST" enctype="multipart/form-data" class="candidate-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="party">Party:</label>
                    <input type="text" id="party" name="party" required>
                </div>
                <div class="form-group">
                    <label for="profile_photo">Profile Photo:</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png" required>
                </div>
                <button type="button" class="btn" onclick="showPasswordModal('add_candidate')">Add Candidate</button>
            </form>
            <div class="candidate-list">
                <h3>Current Candidates</h3>
                <?php if (empty($candidates)): ?>
                    <p>No candidates available.</p>
                <?php else: ?>
                    <?php foreach ($candidates as $candidate): ?>
                        <div class="candidate-item">
                            <div>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($candidate['full_name']); ?></p>
                                <p><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party']); ?></p>
                            </div>
                            <div>
                                <button class="btn" onclick="showUpdateForm(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($candidate['full_name']); ?>', '<?php echo htmlspecialchars($candidate['party']); ?>')">Update</button>
                                <a href="#" class="delete-btn" onclick="showPasswordModal('delete_candidate_<?php echo $candidate['id']; ?>')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="section" id="manage-users">
            <h2>Manage Users</h2>
            <div class="user-list">
                <?php if (empty($users)): ?>
                    <p>No users available.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-item">
                            <div>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($user['category']); ?></p>
                            </div>
                            <div>
                                <a href="#" class="delete-btn" onclick="showPasswordModal('delete_user_<?php echo $user['id']; ?>')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="section" id="manage-elections">
            <h2>Manage Elections</h2>
            <?php if (empty($elections)): ?>
                <p>No elections available.</p>
            <?php else: ?>
                <div class="election-list">
                    <?php foreach ($elections as $election): ?>
                        <div class="election-item">
                            <div>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($election['name']); ?></p>
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($election['description'] ?? 'N/A'); ?></p>
                                <p><strong>Created At:</strong> <?php echo $election['created_at']; ?></p>
                            </div>
                            <div>
                                <a href="#" class="delete-btn" onclick="showPasswordModal('delete_election_<?php echo $election['id']; ?>')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section" id="create-election">
            <h2>Create Election</h2>
            <p>Click the button below to create a new election.</p>
            <a href="create_elections.php" class="btn">Go to Create Election</a>
        </div>

        <div class="section" id="view-results">
            <h2>Voting Results</h2>
            <?php if (empty($results)): ?>
                <p>No votes have been cast yet.</p>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <div class="result-item">
                        <p><strong>Candidate:</strong> <?php echo htmlspecialchars($result['full_name']); ?></p>
                        <p><strong>Party:</strong> <?php echo htmlspecialchars($result['party']); ?></p>
                        <p><strong>Votes:</strong> <?php echo $result['vote_count']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <button class="analytics-btn" onclick="toggleAnalytics()">View More Analytics</button>
            <div class="chart-container" id="chartContainer">
                <canvas id="voteChart"></canvas>
            </div>
            <button class="btn" onclick="showPasswordModal('generate_report')">Generate Report (PDF)</button>
        </div>

        <div class="section" id="system-logs">
            <h2>System Logs</h2>
            <?php if (empty($logs)): ?>
                <p>No logs available.</p>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <p><strong>User:</strong> <?php echo htmlspecialchars($log['username']); ?></p>
                        <p><strong>Action:</strong> <?php echo htmlspecialchars($log['action']); ?></p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></p>
                        <p><strong>Timestamp:</strong> <?php echo $log['timestamp']; ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section" id="system-settings">
            <h2>System Settings</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="button" class="btn" onclick="showPasswordModal('reset_votes')">Reset All Votes</button>
            </form>
        </div>
    </div>

    <!-- Password Confirmation Modal -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <h3>Confirm Your Password</h3>
            <input type="password" id="confirmPassword" placeholder="Enter your password">
            <button class="btn" onclick="confirmAction()">Confirm</button>
            <button class="btn" style="background-color: #ff6f61;" onclick="closeModal()">Cancel</button>
        </div>
    </div>

    <!-- Update Candidate Modal -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <h3>Update Candidate</h3>
            <form id="updateForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="candidate_id" id="updateCandidateId">
                <div class="form-group">
                    <label for="update_full_name">Full Name:</label>
                    <input type="text" id="update_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="update_party">Party:</label>
                    <input type="text" id="update_party" name="party" required>
                </div>
                <button type="button" class="btn" onclick="showPasswordModal('update_candidate')">Update</button>
                <button type="button" class="btn" style="background-color: #ff6f61;" onclick="closeUpdateModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted');
        }

        // Auto-hide sidebar on click outside or on menu item click
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger');
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnHamburger = hamburger.contains(event.target);

            if (!isClickInsideSidebar && !isClickOnHamburger && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });

        // Section navigation
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            toggleSidebar();
        }

        // Chart for analytics
        const labels = <?php echo json_encode($labels); ?>;
        const voteCounts = <?php echo json_encode($vote_counts); ?>;
        const colors = <?php echo json_encode($colors); ?>;

        const ctx = document.getElementById('voteChart').getContext('2d');
        const voteChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Vote Count',
                    data: voteCounts,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Votes'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Candidates'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Election Results Histogram'
                    }
                }
            }
        });

        function toggleAnalytics() {
            const chartContainer = document.getElementById('chartContainer');
            chartContainer.classList.toggle('active');
            const button = document.querySelector('.analytics-btn');
            button.textContent = chartContainer.classList.contains('active') ? 'Hide Analytics' : 'View More Analytics';
        }

        // Password confirmation modal
        let currentAction = '';

        function showPasswordModal(action) {
            currentAction = action;
            document.getElementById('passwordModal').style.display = 'block';
            document.getElementById('confirmPassword').value = '';
        }

        function closeModal() {
            document.getElementById('passwordModal').style.display = 'none';
            currentAction = '';
        }

        function confirmAction() {
            const password = document.getElementById('confirmPassword').value;
            if (!password) {
                alert('Please enter your password.');
                return;
            }

            if (currentAction.startsWith('delete_candidate_')) {
                const candidateId = currentAction.split('_')[2];
                window.location.href = `admin_dashboard.php?delete_candidate=${candidateId}&confirm_password=${encodeURIComponent(password)}`;
            } else if (currentAction.startsWith('delete_user_')) {
                const userId = currentAction.split('_')[2];
                window.location.href = `admin_dashboard.php?delete_user=${userId}&confirm_password=${encodeURIComponent(password)}`;
            } else if (currentAction.startsWith('delete_election_')) {
                const electionId = currentAction.split('_')[2];
                window.location.href = `admin_dashboard.php?delete_election=${electionId}&confirm_password=${encodeURIComponent(password)}`;
            } else if (currentAction === 'generate_report') {
                window.location.href = `admin_dashboard.php?generate_report=1&confirm_password=${encodeURIComponent(password)}`;
            } else {
                let form;
                if (currentAction === 'add_candidate') {
                    form = document.querySelector('.candidate-form');
                } else if (currentAction === 'update_candidate') {
                    form = document.getElementById('updateForm');
                } else if (currentAction === 'reset_votes') {
                    form = document.querySelector('#system-settings form');
                } else {
                    alert('Invalid action.');
                    return;
                }

                if (!form) {
                    alert('Form not found.');
                    return;
                }

                const passwordInput = document.createElement('input');
                passwordInput.type = 'hidden';
                passwordInput.name = 'confirm_password';
                passwordInput.value = password;
                form.appendChild(passwordInput);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = currentAction;
                input.value = '1';
                form.appendChild(input);

                form.submit();
            }
        }

        // Update candidate modal
        function showUpdateForm(id, fullName, party) {
            document.getElementById('updateCandidateId').value = id;
            document.getElementById('update_full_name').value = fullName;
            document.getElementById('update_party').value = party;
            document.getElementById('updateModal').style.display = 'block';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // Dark mode toggle
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>

</html>