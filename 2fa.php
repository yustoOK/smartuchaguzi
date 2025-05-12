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

// Session validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['voter', 'admin'])) {
    error_log("Session validation failed: user_id or role not set or invalid. Session: " . print_r($_SESSION, true));
    session_unset();
    session_destroy();
    header('Location: /smartuchaguzi/login.php?error=' . urlencode('Access Denied.')); // Relative URL
    exit;
}



if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch; possible session hijacking attempt.");
    session_unset();
    session_destroy();
    header('Location: /smartuchaguzi/login.php?error=' . urlencode('Session validation failed.')); // Relative URL
    exit;
}

// Session timeout handling
$inactivity_timeout = 5 * 60;
$max_session_duration = 30 * 60;

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
    header('Location: /smartuchaguzi/login.php?error=' . urlencode('Session expired. Please log in again.')); // Relative URL
    exit;
}

$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= $inactivity_timeout) {
    error_log("Session expired due to inactivity: $inactive_time seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: /smartuchaguzi/login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.')); // Relative URL
    exit;
}

$_SESSION['last_activity'] = time();

// Fetch user TOTP secret
$user_id = $_SESSION['user_id'];
$totp_secret = '';
try {
    $stmt = $conn->prepare("SELECT totp_secret FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $totp_secret = $user['totp_secret'] ?? '';
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: /smartuchaguzi/login.php?error=' . urlencode('Server error. Please log in again.')); // Relative URL
    exit;
}

// Include TOTP and QR Code libraries
require 'C:/xampp/htdocs/smartuchaguzi/2fa/vendor/autoload.php';
use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Debug: Check if GD is loaded
if (!extension_loaded('gd')) {
    $errors[] = "GD extension is not enabled. Please enable it in php.ini and restart Apache.";
}

$setup_needed = empty($totp_secret);
$errors = [];
$success = '';
$qrCodeDataUri = '';

if ($setup_needed && isset($_POST['setup_totp'])) {
    $totp = TOTP::create();
    $totp->setLabel("SmartUchaguzi-{$user_id}");
    $totp->setIssuer('SmartUchaguzi');
    $totp_secret = $totp->getSecret();
    $provisioning_uri = $totp->getProvisioningUri();

    try {
        $stmt = $conn->prepare("UPDATE users SET totp_secret = ? WHERE user_id = ?");
        $stmt->bind_param("si", $totp_secret, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update TOTP secret: " . $conn->error);
        }
        $success = "Please scan the QR code below with your authenticator app (e.g., Google Authenticator) and enter the code.";

        // Generate QR code using Endroid\QrCode
        $qrCode = new QrCode($provisioning_uri);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $qrCodeDataUri = $result->getDataUri();
    } catch (Exception $e) {
        $errors[] = "Failed to set up TOTP: " . $e->getMessage();
    }
}

if (!$setup_needed && isset($_POST['verify_totp'])) {
    $user_totp = trim($_POST['totp'] ?? '');
    if (empty($user_totp)) {
        $errors[] = "Please enter the TOTP code.";
    } else {
        $totp = TOTP::create($totp_secret);
        if ($totp->verify($user_totp)) {
            $_SESSION['2fa_verified'] = true;
            $college_id = $_SESSION['college_id'] ?? 0;
            $role = $_SESSION['role'] ?? '';
            $association = $_SESSION['association'] ?? '';
            $dashboard = '';
            if ($role === 'admin') {
                $dashboard = '/smartuchaguzi/admin-dashboard.php'; // Relative URL
            } elseif ($role === 'voter') {
                if ($college_id == 1 && $association === 'UDOSO') $dashboard = '/smartuchaguzi/cive-students.php';
                elseif ($college_id == 2 && $association === 'UDOSO') $dashboard = '/smartuchaguzi/coed-students.php';
                elseif ($college_id == 3 && $association === 'UDOSO') $dashboard = '/smartuchaguzi/cnms-students.php';
                elseif ($college_id == 1 && $association === 'UDOMASA') $dashboard = '/smartuchaguzi/cive-teachers.php';
                elseif ($college_id == 2 && $association === 'UDOMASA') $dashboard = '/smartuchaguzi/coed-teachers.php';
                elseif ($college_id == 3 && $association === 'UDOMASA') $dashboard = '/smartuchaguzi/cnms-teachers.php';
            }
            if ($dashboard) {
                header("Location: $dashboard");
                exit;
            } else {
                $errors[] = "Invalid dashboard configuration.";
            }
        } else {
            $errors[] = "Invalid TOTP code. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification | SmartUchaguzi</title>
    <link rel="icon" href="/smartuchaguzi/Uploads/Vote.jpeg" type="image/x-icon"> <!-- Relative URL -->
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
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('/smartuchaguzi/images/cive.jpeg'); /* Relative URL */
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 400px;
            width: 90%;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
        }
        .container h2 {
            font-size: 28px;
            color: #1a3c34;
            margin-bottom: 20px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .container p {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            border-color: #f4a261;
        }
        button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
            margin: 10px 5px;
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
        .qr-code {
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 200px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .container h2 { font-size: 24px; }
            .form-group input { padding: 10px; font-size: 14px; }
            button { padding: 10px 20px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Two-Factor Authentication</h2>
        <?php if ($setup_needed): ?>
            <p>Please set up your authenticator app (e.g., Google Authenticator).</p>
            <form method="POST">
                <button type="submit" name="setup_totp">Generate QR Code</button>
            </form>
            <?php if ($success && !empty($qrCodeDataUri)): ?>
                <div class="success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
                <div class="qr-code">
                    <img src="<?php echo htmlspecialchars($qrCodeDataUri); ?>">
                </div>
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="totp" placeholder="Enter TOTP Code" maxlength="6" required>
                    </div>
                    <button type="submit" name="verify_totp">Verify TOTP</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Enter the 6-digit code from your authenticator app.</p>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="totp" placeholder="Enter TOTP Code" maxlength="6" required>
                </div>
                <button type="submit" name="verify_totp">Verify TOTP</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success && !$setup_needed): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>