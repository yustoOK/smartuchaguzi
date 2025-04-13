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
    error_log("Connection to smartuchaguzi_db failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

function connectToOriginalDB()
{
    $host = 'localhost';
    $dbname = 'original_db';
    $username = 'root';
    $password = 'Leonida1972@@@@';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Connection to original_db failed: " . $e->getMessage());
        die("Connection to original_db failed. Please try again later.");
    }
}

function sendVerificationEmail($email, $token)
{
    $subject = "Verify Your SmartUchaguzi Account";
    $verificationLink = "http://localhost/smartuchaguzi/verify_email.php?token=" . urlencode($token);
    $message = "Hello,\n\nPlease verify your email by clicking the link below:\n$verificationLink\n\nIf you did not register, please ignore this email.\n\nBest regards,\nSmartUchaguzi Team 2025";
    $headers = "From: yustobitalio20@gmail.com\r\n";

    return mail($email, $subject, $message, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $official_id = filter_input(INPUT_POST, 'official_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($official_id) || empty($email) || empty($password) || empty($confirm_password)) {
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

    $original_pdo = connectToOriginalDB();
    try {
        $stmt = $original_pdo->prepare("SELECT official_id, email, fname, mname, lname, college, association, role FROM all_users WHERE official_id = ? AND email = ?");
        $stmt->execute([$official_id, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header("Location: register.php?error=" . urlencode("User not found or email does not match official ID."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Query to original_db failed: " . $e->getMessage());
        header("Location: register.php?error=" . urlencode("Failed to verify user in original database. Please try again."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE official_id = ? OR email = ?");
        $stmt->execute([$official_id, $email]);
        if ($stmt->fetch()) {
            header("Location: register.php?error=" . urlencode("User already registered."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Check for existing user in smartuchaguzi_db failed: " . $e->getMessage());
        header("Location: register.php?error=" . urlencode("Failed to check existing user. Please try again."));
        exit;
    }

    $verification_token = bin2hex(random_bytes(16));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (official_id, email, fname, mname, lname, college, association, password_hash, verification_token, is_verified, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)"
        );
        $stmt->execute([
            $official_id,
            $email,
            $user['fname'],
            $user['mname'],
            $user['lname'],
            $user['college'],
            $user['association'],
            $password_hash,
            $verification_token,
            $user['role'] ?? 'voter'
        ]);

        if (sendVerificationEmail($email, $verification_token)) {
            header("Location: login.php?success=" . urlencode("Registration successful! Please check your email to verify your account."));
        } else {
            header("Location: register.php?error=" . urlencode("Failed to send verification email. Please try again."));
        }
    } catch (PDOException $e) {
        error_log("Insert into smartuchaguzi_db failed: " . $e->getMessage());
        header("Location: register.php?error=" . urlencode("Registration failed due to a server error. Please try again."));
        exit;
    }
    exit;
}
?>