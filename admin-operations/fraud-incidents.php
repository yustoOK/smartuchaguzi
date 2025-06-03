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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $resolve_id = $_POST['resolve_id'];
    try {
        $stmt = $pdo->prepare("UPDATE frauddetectionlogs SET resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE id = ? AND resolved = 0");
        $stmt->execute([$user_id, $resolve_id]);
        header('Location: fraud-incidents.php?success=' . urlencode('Fraud incident resolved successfully.'));
        exit;
    } catch (PDOException $e) {
        error_log("Resolve fraud incident error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Fraud Incidents | SmartUchaguzi</title>
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
        .fraud-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .fraud-table th,
        .fraud-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e7ea;
        }
        .fraud-table th {
            background: #f4a261;
            color: #fff;
            font-weight: 600;
        }
        .fraud-table td {
            color: #2d3748;
            font-size: 14px;
            background: #fff;
        }
        .fraud-table tr:hover td {
            background: rgba(244, 162, 97, 0.1);
        }
        .fraud-table td button {
            color: #f4a261;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }
        .fraud-table td button:hover {
            color: #e76f51;
        }
        .success {
            color: #2ecc71;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
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
            <img src="../images/System Logo.jpg" alt="SmartUchaguzi Logo">
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
            <a href="fraud-incidents.php" class="active"><i class="fas fa-exclamation-triangle"></i> Fraud Incidents</a>
            <a href="security-settings.php"><i class="fas fa-shield-alt"></i> Security Settings</a>
            <a href="audit-logs.php"><i class="fas fa-file-alt"></i> Audit Logs</a>
        </div>
    </aside>

    <main class="main-content">
        <section class="dashboard">
            <div class="dash-content">
                <h3>Fraud Incidents</h3>
                <?php if (isset($_GET['success'])): ?>
                    <p class="success"><?php echo htmlspecialchars($_GET['success']); ?></p>
                <?php endif; ?>
                <table class="fraud-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Election ID</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                            <th>Resolved</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT id, election_id, details, created_at, resolved, action FROM frauddetectionlogs");
                            if ($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['election_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                                    echo "<td>" . ($row['resolved'] ? 'Yes' : 'No') . "</td>";
                                    echo "<td><button onclick=\"if(confirm('Are you sure you want to resolve this incident?')) { resolveIncident(" . $row['id'] . "); }\">Resolve</button></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No fraud incidents found.</td></tr>";
                            }
                        } catch (PDOException $e) {
                            error_log("Query error: " . $e->getMessage());
                            echo "<tr><td colspan='6'>Error loading fraud incidents.</td></tr>";
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

            function resolveIncident(incidentId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'resolve_id';
                input.value = incidentId;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    </script>
</body>
</html>
<?php $pdo = null; ?>