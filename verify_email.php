<?php
session_start();

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

if (isset($_GET['token'])) {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Check if token exists and user is not verified
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Mark user as verified
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
        $stmt->execute([$token]);

        header("Location: login.html?success=" . urlencode("Email verified successfully! Please log in."));
    } else {
        header("Location: register.html?error=" . urlencode("Invalid or expired verification token."));
    }
    exit;
} else {
    header("Location: register.html?error=" . urlencode("No verification token provided."));
    exit;
}
?>