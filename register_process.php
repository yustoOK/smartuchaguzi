<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$original_host = 'localhost';
$original_dbname = 'original_db';
$original_username = 'root';
$original_password = 'Leonida1972@@@@';

$smart_host = 'localhost';
$smart_dbname = 'smartuchaguzi_db';
$smart_username = 'root';
$smart_password = 'Leonida1972@@@@';

try {
    $pdo_original = new PDO("mysql:host=$original_host;dbname=$original_dbname", $original_username, $original_password);
    $pdo_original->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo_smart = new PDO("mysql:host=$smart_host;dbname=$smart_dbname", $smart_username, $smart_password);
    $pdo_smart->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header("Location: register.php?error=" . urlencode("Database connection failed."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $official_id = filter_input(INPUT_POST, 'official_id', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $fname = filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_SPECIAL_CHARS);
    $lname = filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_SPECIAL_CHARS);
    $association = filter_input(INPUT_POST, 'association', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($official_id) || empty($email) || empty($fname) || empty($lname) || empty($association) || empty($password) || empty($confirm_password)) {
        header("Location: register.php?error=" . urlencode("All fields are required."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=" . urlencode("Invalid email format."));
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: register.php?error=" . urlencode("Passwords do not match."));
        exit;
    }

    if (strlen($password) < 8) {
        header("Location: register.php?error=" . urlencode("Password must be at least 8 characters long."));
        exit;
    }

    // Validating against original_db
    try {
        $stmt = $pdo_original->prepare("SELECT * FROM all_users WHERE official_id = ? AND email = ?");
        $stmt->execute([$official_id, $email]);
        $original_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original_user) {
            header("Location: register.php?error=" . urlencode("User not found in original database."));
            exit;
        }

        // Checking if user already exists in smartuchaguzi_db
        $stmt = $pdo_smart->prepare("SELECT user_id FROM users WHERE official_id = ? OR email = ?");
        $stmt->execute([$official_id, $email]);
        if ($stmt->fetch()) {
            header("Location: register.php?error=" . urlencode("User already registered."));
            exit;
        }

        // Generating verification token
        $verification_token = bin2hex(random_bytes(16));
        $token_expires_at = date('Y-m-d H:i:s', time() + 24 * 60 * 60); // 24 hours expiry
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Inserting into smartuchaguzi_db
        $stmt = $pdo_smart->prepare("
            INSERT INTO users (
                official_id, email, association, password, is_verified, verification_token,
                token_expires_at, role, fname, lname, hostel_id, college_id, active, wallet_address
            ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $official_id,
            $email,
            $original_user['association'],
            $hashed_password,
            $verification_token,
            $token_expires_at,
            $original_user['role'],
            $original_user['fname'],
            $original_user['lname'],
            $original_user['hostel_id'],
            $original_user['college_id'],
            $original_user['wallet_address']
        ]);

        // Log action
        $user_id = $pdo_smart->lastInsertId();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $action = "User registered: $email";
        $stmt = $pdo_smart->prepare("INSERT INTO auditlogs (user_id, action, ip_address, timestamp) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $ip_address]);

        $verification_link = "http://localhost/verify_email.php?token=$verification_token";
        mail($email, "Verify Your Email", "Click here to verify: $verification_link", "From: yustobitalio20@gmail.com");

        header("Location: register.php?success=" . urlencode("Registration successful! Please check your email to verify your account."));
        exit;
    } catch (PDOException $e) {
        error_log("Registration failed: " . $e->getMessage());
        header("Location: register.php?error=" . urlencode("Registration failed due to a server error."));
        exit;
    }
} else {
    header("Location: register.php?error=" . urlencode("Invalid request method."));
    exit;
}
?>