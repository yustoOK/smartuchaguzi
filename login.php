<?php
// login.php
include 'db.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role'], $_SESSION['college'], $_SESSION['association']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Fetch user
        $stmt = $db->prepare("SELECT id, email, password_hash, role, college, association, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email before logging in.";
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['college'] = $user['college'];
                $_SESSION['association'] = $user['association'];

                // Log login action
                $action = "User logged in: {$user['email']}";
                $db->query("INSERT INTO audit_log (user_id, action) VALUES ('{$user['id']}', '$action')");

                // Redirect based on role, college, and association
                redirectUser($user['role'], $user['college'], $user['association']);
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}

// Function to redirect users based on role, college, and association
function redirectUser($role, $college, $association) {
    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
    } elseif ($role === 'observer') {
        header('Location: observer-dashboard.php');
    } elseif ($role === 'voter') {
        // College-based redirection
        if ($college === 'CIVE') {
            header('Location: cive-dashboard.php');
        } elseif ($college === 'COED') {
            header('Location: coed-dashboard.php');
        } elseif ($college === 'CNMS') {
            header('Location: cnms-dashboard.php');
        }
        // Association-based redirection
        elseif ($association === 'TAHLISO') {
            header('Location: tahliso-dashboard.php');
        } elseif ($association === 'UDOMASA') {
            header('Location: udomasa-dashboard.php');
        } else {
            // Default redirect if no specific college or association
            header('Location: index.html');
        }
    } else {
        // Unknown role, redirect to login
        header('Location: login.html');
    }
}
?>