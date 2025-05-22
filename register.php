<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register to vote in the upcoming elections.">
    <meta name="keywords" content="register, vote, elections, official, ID, email, password">
    <meta name="author" content="We Vote">
    <title>SmartUchaguzi | Register</title>
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

        .register-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            text-align: center;
            margin-top: 20px;
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

        .register-title {
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

        .register-btn,
        .login-btn {
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

        .register-btn:hover,
        .login-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .register-btn::before,
        .login-btn::before {
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

        .register-btn:hover::before,
        .login-btn:hover::before {
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
            .register-container {
                width: 90%;
                padding: 20px;
            }

            .register-title {
                font-size: 24px;
                padding: 6px 12px;
            }
        }
    </style>
</head>

<body>
    <div class="register-container">
        <div class="register-title">SmartUchaguzi Register</div>
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
        <form class="register-form" action="register_process.php" method="POST" onsubmit="return validateForm()">
            <div class="input-group">
                <label for="official_id" aria-label="Official ID"><i class="fas fa-id-card"></i></label>
                <input type="text" id="official_id" name="official_id" placeholder="Official ID" required>
            </div>
            <div class="input-group">
                <label for="email" aria-label="Email Address"><i class="fas fa-envelope"></i></label>
                <input type="email" id="email" name="email" placeholder="Email ID" required>
            </div>
            <div class="input-group">
                <label for="password" aria-label="Password"><i class="fas fa-lock"></i></label>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password" aria-label="Confirm Password"><i class="fas fa-lock"></i></label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="register-btn">Register</button>
            <button type="button" class="login-btn" onclick="window.location.href='login.php'">Login</button>
        </form>
    </div>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }

            if (password.length < 8) {
                alert('Password must be at least 8 characters long.');
                return false;
            }

            return true;
        }
    </script>
</body>

</html>