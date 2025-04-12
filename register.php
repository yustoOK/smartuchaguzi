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

// Function to connect to the original_db for user verification (This is to simulate the original university database connection in order to fetch the user details)
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
        die("Connection to original_db failed: " . $e->getMessage());
    }
}

// Function to send verification email
function sendVerificationEmail($email, $token)
{
    $subject = "Verify Your SmartUchaguzi Account";
    $verificationLink = "http://localhost/smartuchaguzi/verify_email.php?token=" . urlencode($token);
    $message = "Hello,\n\nPlease verify your email by clicking the link below:\n$verificationLink\n\nIf you did not register, please ignore this email.\n\nBest regards,\nSmartUchaguzi Team 2025";
    $headers = "From: yustobitalio20@gmail.com\r\n"; //We shall create this email to match the system email address

    // For testing, we can use mail() or a library like PHPMailer for production
    if (mail($email, $subject, $message, $headers)) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $official_id = filter_input(INPUT_POST, 'official_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($official_id) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: register.html?error=" . urlencode("All fields are required."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.html?error=" . urlencode("Invalid email format."));
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: register.html?error=" . urlencode("Passwords do not match."));
        exit;
    }

    // Connect to original_db to verify user
    $original_pdo = connectToOriginalDB();
    $stmt = $original_pdo->prepare("SELECT * FROM all_users WHERE official_id = ? AND email = ?");
    $stmt->execute([$official_id, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: register.html?error=" . urlencode("User not found or email does not match."));
        exit;
    }

    // Check if user already exists in smartuchaguzi database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE official_id = ? OR email = ?");
    $stmt->execute([$official_id, $email]);
    if ($stmt->fetch()) {
        header("Location: register.html?error=" . urlencode("User already registered."));
        exit;
    }

    // Generate verification token
    $verification_token = bin2hex(random_bytes(16));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into smartuchaguzi database
    $stmt = $pdo->prepare("INSERT INTO users (official_id, email, full_name, college, association, password_hash, verification_token, created_at, role) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([
        $official_id,
        $email,
        $user['full_name'],
        $user['college'],
        $user['association'],
        $password_hash,
        $verification_token,
        $user['role'] ?? 'voter' // Default to 'voter' if role is not set
    ]);

    // Send verification email
    if (sendVerificationEmail($email, $verification_token)) {
        header("Location: register.html?success=" . urlencode("Registration successful! Please check your email to verify your account."));
    } else {
        header("Location: register.html?error=" . urlencode("Failed to send verification email. Please try again."));
    }
    exit;
}
