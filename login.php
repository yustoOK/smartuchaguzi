<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartUchaguzi | Login</title>
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

        .login-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
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

        .login-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            padding: 8px 16px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            display: inline-block;
        }

        .divider-lines {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .divider-lines .line {
            width: 100px;
            height: 2px;
            background: #e76f51;
            margin: 0 10px;
        }

        .avatar {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .avatar img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border: 2px solid #f4a261;
            transition: transform 0.3s ease;
        }

        .avatar img:hover {
            transform: scale(1.1);
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

        .options {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #e6e6e6;
            margin: 15px 0;
        }

        .options input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            vertical-align: middle;
            accent-color: #f4a261;
        }

        .options a {
            color: #f4a261;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .options a:hover {
            color: #e76f51;
        }

        .login-btn,
        .signup-btn {
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

        .login-btn:hover,
        .signup-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .login-btn::before,
        .signup-btn::before {
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

        .login-btn:hover::before,
        .signup-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .success-message {
            color: #1a3c34;
            background: rgba(26, 60, 52, 0.2);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #1a3c34;
        }

        .error-message {
            color: #e76f51;
            background: rgba(231, 111, 81, 0.2);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #e76f51;
        }

        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 20px;
            }

            .header {
                padding: 10px 20px;
            }

            .header .logo h1 {
                font-size: 18px;
            }

            .login-title {
                font-size: 24px;
                padding: 6px 12px;
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
                <a href="index.html">Home</a> / <a href="login.php">Login</a>
            </div>
        </div>
    </div>
    <div class="login-container">
        <div class="login-title">SmartUchaguzi Login</div>
        <?php if (isset($_GET['success'])): ?>
            <p class="success-message">
                <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
            </p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p class="error-message">
                <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
            </p>
        <?php endif; ?>
        <div class="divider-lines">
            <div class="line"></div>
            <div class="line"></div>
        </div>
        <div class="avatar">
            <img src="./uploads/passports/general.png" alt="User Icon">
        </div>
        <form class="login-form" action="login_process.php" method="POST" onsubmit="return validateForm()">
            <div class="input-group">
                <label for="email" aria-label="Email Address"><i class="fas fa-user"></i></label>
                <input type="email" id="email" name="email" placeholder="Email ID" required>
            </div>
            <div class="input-group">
                <label for="password" aria-label="Password"><i class="fas fa-lock"></i></label>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="options">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            <button type="submit" class="login-btn">LOGIN</button>
            <button type="button" class="signup-btn" onclick="window.location.href='register.php'">SIGN UP</button>
        </form>
    </div>

    <script>
        function validateForm() {
            const email = document.getElementById('email').value;

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            return true;
        }
    </script>
</body>

</html>