<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=login_required");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $message = $_POST['message'];

    // Insert feedback
    $stmt = $pdo->prepare("INSERT INTO feedback (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $message]);

    // Fetch user's email for notification
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_email = $user['email'];
    $username = $user['username'];

    // Send email notification
    $to = "yustobitalio20@gmail.com";
    $subject = "New Feedback from SmartUchaguzi";
    $body = "User ID: $user_id\nUsername: $username\nEmail: $user_email\nMessage: $message";
    $headers = "From: no-reply@smartuchaguzi.com";
    mail($to, $subject, $body, $headers);

    header("Location: contact.html?success=1");
    exit;
}
