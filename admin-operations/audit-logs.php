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
    die("Unable to connect to the database");
}

$required_role = 'admin';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
    header('Location: ../login.php?error=' . urlencode('Please log in as an admin.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: ../2fa.php');
    exit;
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT fname, mname, lname, college_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_name = htmlspecialchars($user['fname'] . ' ' . ($user['mname'] ? $user['mname'] . ' ' : '') . $user['lname']);
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    header('Location: ../login.php?error=' . urlencode('Error logging in'));
    exit;
}

$college_name = '';
if ($user['college_id']) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM colleges WHERE college_id = ?");
        $stmt->execute($user['college_id']);
        $college_name = $stmt->fetchColumn() ?: 'Unknown';
    } catch (PDOException $e) {
        error_log("College query failed: " . $e->getMessage());
        $college_name = 'Unknown';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_logs'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'Action', 'Details', 'IP Address', 'Timestamp']);
    try {
        $stmt = $pdo->query("SELECT user_id, action, details, ip_address, timestamp FROM auditlogs ORDER BY timestamp DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['user_id'],
                $row['action'],
                $row['details'],
                $row['ip_address'],
                $row['timestamp']
            ]);
        }
    } catch (PDOException $e) {
        error_log("Export audit logs error: " . $e->getMessage());
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Audit Logs | SmartUchaguzi</title>
    <link rel="icon" href="../images/System Logo.jpg" type="image/x-icon">
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
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('../images/cive.jpeg');
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
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: #e6e6e6;
            cursor: pointer;
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
        .user a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
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
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100%;
            background: #1a3c34;
            padding-top: 80px;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 900;
        }
        .sidebar .nav {
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }
        .sidebar .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .sidebar .nav a.active {
            background: #f4a261;
            color: #fff;
        }
        .sidebar .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            margin-left: 260px;
            padding: 80px 1rem 2rem;
            min-height: 100vh;
        }
        .dash-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 1200px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
        }
        h3 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .audit-table th,
        .audit-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e7ea;
        }
        .audit-table th {
            background: #f4a261;
            color: #fff;
            font-weight: 600;
        }
        .audit-table td {
            color: #2d3748;
            font-size: 14px;
            background: #fff;
        }
        .audit-table tr:hover td {
            background: rgba(244, 162, 97, 0.1);
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .action-buttons button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }
        .action-buttons button:hover {
            background: #e76f51;
        }
        footer {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 15px;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .menu-toggle {
                display: block;
            }
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
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-bars menu-toggle"></i>
            <img src="../Uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="user">
            <span><?php echo $admin_name . ($college_name ? ' (' . $college_name . ')' : ''); ?></span>
            <img src="../images/default.png" alt="Profile" onerror="this.src='../images/general.png';">
            <div class="dropdown" id="user-dropdown">
                <span><?php echo htmlspecialchars($admin_name); ?></span>
                <a href="../profile.php">My Profile</a>
                <a href="../logout.php">Logout</a>
            </div>
            <a href="../logout.php" class="logout-link">Logout</a>
        </div>
    </header>

    <aside class="sidebar">
        <div class="nav">
            <a href="../admin-dashboard.php"><i class="fas fa-home"></i> Overview</a>
            <a href="manage-elections.php"><i class="fas fa-cog"></i> Election Management</a>
            <a href="blockchain-verification.php"><i class="fas fa-chain"></i> Blockchain Verification</a>
            <a href="user-management.php"><i class="fas fa-users"></i> User Management</a>
            <a href="votes-analytics.php"><i class="fas fa-chart-bar"></i> Votes Analytics</a>
            <a href="fraud-incidents.php"><i class="fas fa-exclamation-triangle"></i> Fraud Incidents</a>
            <a href="security-settings.php"><i class="fas fa-shield-alt"></i> Security Settings</a>
            <a href="audit-logs.php" class="active"><i class="fas fa-file-alt"></i> Audit Logs</a>
        </div>
    </aside>

    <main class="main-content">
        <section class="dashboard">
            <div class="dash-content">
                <h3>Audit Logs</h3>
                <div class="action-buttons">
                    <form method="POST">
                        <button type="submit" name="export_logs">Export to CSV</button>
                    </form>
                </div>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT user_id, action, details, ip_address, timestamp FROM auditlogs ORDER BY timestamp DESC LIMIT 100");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['action']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log("Audit logs query error: " . $e->getMessage());
                            echo "<tr><td colspan='5'><p class='error'>Error loading audit logs.</p></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
        <footer>
            <p>© 2025 SmartUchaguzi | University of Dodoma</p>
        </footer>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const profilePic = document.querySelector('.user img');
            const userDropdown = document.getElementById('user-dropdown');

            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            if (profilePic) {
                profilePic.addEventListener('click', () => {
                    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', (e) => {
                    if (!profilePic.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php $pdo = null; ?>