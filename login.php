<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam'); //We set this because the default timezone for PHP is alywas Europe/London so we need to set it to our timezone in order to make sure that the tokens will not expire due to time differences i.e if the token is generated at 10:00 AM in Dar es salaam and the server is in London, the token will expire at 8:00 AM London time which is not the same as 10:00 AM Dar es salaam time.
// This is the login page for the Smart Uchaguzi system. It handles user authentication and redirects users based on their roles, colleges, and associations.

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

// Function to redirect users based on role, college, and association
function redirectUser($role, $college, $association) {
    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
    } elseif ($role === 'observer') {
        header('Location: observer-dashboard.php');
    } elseif ($college === 'CIVE' && $association === 'UDOSO') {
        header('Location: cive-students.php');
    } elseif ($college === 'CNMS' && $association === 'UDOSO') {
        header('Location: cnms-students.php');
    } elseif ($college === 'COED' && $association === 'UDOSO') {
        header('Location: coed-students.php');
    } elseif ($college === 'CIVE' && $association === 'UDOMASA') {
        header('Location: cive-teachers.php');
    } elseif ($college === 'CNMS' && $association === 'UDOMASA') {
        header('Location: cnms-teachers.php');
    } elseif ($college === 'COED' && $association === 'UDOMASA') {
        header('Location: coed-teachers.php');
    } else {
        // Default redirect if no specific role, college, or association matches
        header('Location: index.html');
    }
    exit;
}

// Redirecting if already logged in
if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role'], $_SESSION['college'], $_SESSION['association']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitization and validatio of inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        header("Location: login.html?error=" . urlencode("All fields are required."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.html?error=" . urlencode("Invalid email format."));
        exit;
    }

    try {
        // Fetch user from the database
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role, college, association, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_verified'] == 0) {
                header("Location: login.html?error=" . urlencode("Please verify your email before logging in."));
                exit;
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['college'] = $user['college'];
            $_SESSION['association'] = $user['association'];

            // Log login action in audit_log table
            $action = "User logged in: {$user['email']}";
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user['id'], $action]);

            // Redirect based on role, college, and association
            redirectUser($user['role'], $user['college'], $user['association']);
        } else {
            header("Location: login.html?error=" . urlencode("Invalid email or password."));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: login.html?error=" . urlencode("Login failed due to a server error. Please try again."));
        exit;
    }
}
?>