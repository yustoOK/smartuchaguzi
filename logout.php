<?php
session_start();

try {
    // Unset all session variables
    session_unset(); // Explicitly free all session variables
    $_SESSION = array(); // Ensure no residual data remains
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Log the logout action
    error_log("Session destroyed successfully for user: " . ($_SESSION['user_id'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));
    
    // Redirect to login page
    header('Location: login.php?success=' . urlencode('Logged out successfully'));
    exit;
} catch (Exception $e) {
    error_log("Error during logout: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    header('Location: login.php?error=' . urlencode('Logout failed: ' . $e->getMessage()));
    exit;
}
?>