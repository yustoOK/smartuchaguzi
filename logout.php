<?php
session_start();

try {
    session_unset();
    unset($_SESSION['wallet_address']);
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();
    error_log("Session destroyed and cookie cleared for user: " . ($_SESSION['user_id'] ?? 'unknown'));

    header('Location: login.php?success=' . urlencode('Logged out successfully'));
    exit;
} catch (Exception $e) {
    error_log("Error during logout: " . $e->getMessage());
    header('Location: login.php?error=' . urlencode('Logout failed: ' . $e->getMessage()));
    exit;
}
?>