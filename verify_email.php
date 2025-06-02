<?php
session_start();

date_default_timezone_set('Africa/Dar_es_Salaam');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

$error_message = '';
$success_message = '';

if (isset($_GET['token'])) {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    
    if (!ctype_xdigit($token) || strlen($token) !== 32) { // Assuming 16 bytes from bin2hex
        $error_message = "Invalid verification token format.";
        error_log("Verification failed: Invalid token format - " . $token);
        header("Location: register.php?error=" . urlencode($error_message));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ? AND is_verified = 0 AND (token_expires_at > NOW() OR token_expires_at IS NULL)");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE verification_token = ?");
            $stmt->execute([$token]);

            $success_message = "Email verified successfully! Please log in.";
          
            echo "<!DOCTYPE html><html><head><meta http-equiv='refresh' content='3;url=login.php?success=" . urlencode($success_message) . "'></head><body><p style='color: #1a3c34; text-align: center;'>$success_message</p></body></html>";
        } catch (PDOException $e) {
            $error_message = "Failed to verify email due to a server error.";
            error_log("Verification update failed: " . $e->getMessage());
            header("Location: register.php?error=" . urlencode($error_message));
        }
    } else {
        $error_message = "Invalid or expired verification token.";
        error_log("Verification failed: Invalid or expired token - " . $token);
        header("Location: register.php?error=" . urlencode($error_message));
    }
    exit;
} else {
    $error_message = "No verification token provided.";
    error_log("Verification failed: No token provided.");
    header("Location: register.php?error=" . urlencode($error_message));
    exit;
}
?>