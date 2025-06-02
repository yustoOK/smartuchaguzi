<?php 
date_default_timezone_set('Africa/Dar_es_Salaam');

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

$token = isset($_GET['token']) ? htmlspecialchars($_GET['token'], ENT_QUOTES, 'UTF-8') : null;
$error_message = '';
$success_message = '';

if (!$token) {
    $error_message = "No reset token provided.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $error_message = "Invalid or expired reset token.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error_message = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$password_hash, $reset['email']]);

            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $success_message = "Password reset successfully! Redirecting to login page...";
            header("Refresh: 3; url=login.php");
            // No exit here; let the page render with the success message
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartUchaguzi | Reset Password</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
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

        .reset-container {
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

        .reset-title {
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
            .reset-container {
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
                <img src="./images/System Logo.jpg" alt="SmartUchaguzi Logo">
                <h1>SmartUchaguzi</h1>
            </div>
            <div class="breadcrumb">
                <a href="index.php">Home</a> / <a href="reset_password.php">Reset Password</a>
            </div>
        </div>
    </div>
    <div class="reset-container">
        <div class="reset-title">Reset Password</div>
        <?php if ($error_message): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php elseif ($success_message): ?>
            <p class="message success"><?php echo $success_message; ?></p>
        <?php else: ?>
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <div class="input-group">
                    <label for="password"><i class="fas fa-lock"></i></label>
                    <input type="password" id="password" name="password" placeholder="New Password" required>
                </div>
                <div class="input-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i></label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="submit-btn">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>