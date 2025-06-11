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
    error_log("Connected to $host/$dbname with user $username");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed.");
}

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function redirectUser($role, $college_id, $association) {
    $csrf_token = generateCsrfToken();
    header('Location: post-login.php?role=' . urlencode($role) . '&college_id=' . urlencode($college_id ?? '') . '&association=' . urlencode($association ?? '') . '&csrf_token=' . urlencode($csrf_token));
    exit;
}

if (isset($_SESSION['user_id'])) { 
    error_log("Existing session found: " . print_r($_SESSION, true));
    redirectUser($_SESSION['role'], $_SESSION['college_id'] ?? null, $_SESSION['association'] ?? null);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=" . urlencode("All fields are required."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.php?error=" . urlencode("Invalid email format."));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, email, password, role, college_id, association, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        error_log("Query executed for email: $email, rows returned: " . $stmt->rowCount());
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 0) {
                header("Location: login.php?error=" . urlencode("Please verify your email."));
                exit;
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['college_id'] = $user['college_id'] ?? null;
            $_SESSION['association'] = $user['association'] ?? null;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['start_time'] = time();
            $_SESSION['last_activity'] = time();

            $csrf_token = generateCsrfToken();

            $session_token = bin2hex(random_bytes(32));
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, ip_address, login_time, last_activity) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user['user_id'], $session_token, $ip_address]);
            error_log("Session set success after login for user_id: " . $user['user_id']);

            $action = "User logged in: {$user['email']}";
            $stmt = $pdo->prepare("INSERT INTO auditlogs (user_id, action, ip_address, timestamp) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user['user_id'], $action, $ip_address]);

            redirectUser($user['role'], $user['college_id'] ?? null, $user['association'] ?? null);
        } else {
            header("Location: login.php?error=" . urlencode("Invalid email or password."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login query failed: " . $e->getMessage());
        header("Location: login.php?error=" . urlencode("Login failed due to a server error."));
        exit;
    }
}
?>