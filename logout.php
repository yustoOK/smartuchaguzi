<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$currentTime = date('Y-m-d H:i:s');
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

error_log("Logout attempt at $currentTime from IP: $ipAddress, User Agent: $userAgent, Session: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    error_log("No valid session found during logout attempt.");
    header('Location: login.php?error=' . urlencode('No active session to log out from.'));
    exit;
}

function destroySession() {
    global $currentTime, $ipAddress;
    
    session_unset();
     unset($_SESSION['wallet_address']);
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    error_log("Session destroyed at $currentTime from IP: $ipAddress");
}

try {
     destroySession();

     if (isset($_GET['csrf_token']) && isset($_SESSION['csrf_token']) && $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Potential CSRF attack detected during logout from IP: $ipAddress");
        header('Location: login.php?error=' . urlencode('Invalid logout request detected.'));
        exit;
    }

     $redirectMessage = 'Logged out successfully';
    if (isset($_GET['timeout']) && $_GET['timeout'] === 'true') {
        $redirectMessage = 'Session expired due to inactivity. Please log in again.';
    } elseif (isset($_GET['max_duration']) && $_GET['max_duration'] === 'true') {
        $redirectMessage = 'Session expired due to maximum duration. Please log in again.';
    }

    header('Location: login.php?success=' . urlencode($redirectMessage));
    exit;

} catch (Exception $e) {
    error_log("Error during logout process: " . $e->getMessage()); 
    destroySession();
    header('Location: login.php?error=' . urlencode('Logout failed due to a server error: ' . $e->getMessage()));
    exit;
}
?>