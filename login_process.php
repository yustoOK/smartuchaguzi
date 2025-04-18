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
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

function redirectUser($role, $college_id, $association) {
    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
    } elseif ($role === 'observer') {
        header('Location: observer-dashboard.php');
    } elseif ($role === 'voter' && $association === 'UDOSO') {
        if ($college_id === 1) { // CIVE
            header('Location: cive-students.php');
        } elseif ($college_id === 3) { // CNMS
            header('Location: cnms-students.php');
        } elseif ($college_id === 2) { // COED
            header('Location: coed-students.php'); 
        } else {
            header('Location: index.html');
        }
    } elseif ($role === 'teacher-voter' && $association === 'UDOMASA') {
        if ($college_id === 1) { // CIVE
            header('Location: cive-teachers.php');
        } elseif ($college_id === 3) { // CNMS
            header('Location: cnms-teachers.php');
        } elseif ($college_id === 2) { // COED
            header('Location: coed-teachers.php'); 
        } else {
            header('Location: index.html');
        }
    } else {
        header('Location: index.html'); 
    }
    exit;
}

if (isset($_SESSION['user_id'])) {
    redirectUser($_SESSION['role'], $_SESSION['college_id'], $_SESSION['association']);
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
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role, college, association, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_verified'] == 0) {
                header("Location: login.php?error=" . urlencode("Please verify your email before logging in."));
                exit;
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['college_id'] = $user['college'];
            $_SESSION['association'] = $user['association'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['start_time'] = time();
            $_SESSION['last_activity'] = time();

            $action = "User logged in: {$user['email']}";
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user['id'], $action]);

            redirectUser($user['role'], $user['college'], $user['association']);
        } else {
            header("Location: login.php?error=" . urlencode("Invalid email or password."));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login query failed: " . $e->getMessage());
        header("Location: login.php?error=" . urlencode("Login failed due to a server error. Please try again."));
        exit;
    }
}
?>