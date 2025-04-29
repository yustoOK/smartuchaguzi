<?php
date_default_timezone_set('Africa/Dar_es_Salaam');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=" . urlencode("Invalid email format."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: forgot_password.php?error=" . urlencode("Email not found."));
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

    // Store token in password_resets table
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $token, $expires_at]);

    $subject = "Reset Your SmartUchaguzi Password";
    $resetLink = "http://localhost/smartuchaguzi/reset_password.php?token=" . urlencode($token);
    $message = "Hello,\n\nYou requested a password reset. Click the link below to reset your password:\n$resetLink\n\nThis link will expire in 1 hour.\nIf you did not request this, kindly ignore the link!. \n\nBest regards,\nSmartUchaguzi Team";
    $headers = "From: smartuchaguzi1@gmail.com\r\n";

    if (mail($email, $subject, $message, $headers)) {
        header("Location: forgot_password.php?success=" . urlencode("Password reset link sent! Please check your email."));
    } else {
        header("Location: forgot_password.php?error=" . urlencode("Failed to send reset email. Please try again."));
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SmartUchaguzi</title>
    <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .header {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            width: 100%;
            padding: 15px 40px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header .logo {
            display: flex;
            align-items: center;
        }

        .header .logo img {
            width: 40px;
            height: 40px;
            margin-right: 15px;
        }

        .header .logo h1 {
            font-size: 20px;
            font-weight: 600;
        }

        .breadcrumb {
            font-size: 14px;
        }

        .breadcrumb a {
            color: #e6e6e6;
            text-decoration: none;
            padding: 0 5px;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #f4a261;
        }

        .forgot-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin-top: 120px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .forgot-title {
            font-size: 28px;
            color: #1a3c34;
            margin-bottom: 20px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .input-group {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .input-group:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 10px rgba(244, 162, 97, 0.3);
        }

        .input-group label {
            color: #e6e6e6;
            padding: 0 10px;
        }

        .input-group input {
            border: none;
            background: transparent;
            outline: none;
            color: #e6e6e6;
            flex: 1;
            font-size: 16px;
            padding: 8px 0;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-bottom: 2px solid #f4a261;
        }

        ::placeholder {
            color: #b0b0b0;
            font-size: 16px;
            opacity: 1;
        }

        .submit-btn {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            border: none;
            color: #fff;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 8px 0;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .submit-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
        }

        .message.success {
            color: #1a3c34;
        }

        .message.error {
            color: #e76f51;
        }

        @media (max-width: 480px) {
            .forgot-container {
                width: 90%;
                padding: 20px;
            }

            .header {
                padding: 10px 20px;
            }

            .header .logo h1 {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo">
                <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
                <h1>SmartUchaguzi</h1>
            </div>
            <div class="breadcrumb">
                <a href="index.php">Home</a> / <a href="forgot_password.php">Forgot Password</a>
            </div>
        </div>
    </div>
    <div class="forgot-container">
        <div class="forgot-title">Forgot Password</div>
        <?php if (isset($_GET['success'])): ?>
            <p class="message success"><?php echo htmlspecialchars(urldecode($_GET['success'])); ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p class="message error"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></p>
        <?php endif; ?>
        <form action="forgot_password.php" method="POST">
            <div class="input-group">
                <label for="email"><i class="fas fa-envelope"></i></label>
                <input type="email" id="email" name="email" placeholder="Email ID" required>
            </div>
            <button type="submit" class="submit-btn">Request Reset Link</button>
        </form>
    </div>
</body>

</html>