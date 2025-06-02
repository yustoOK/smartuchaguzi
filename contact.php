<?php

$message = '';
$status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($_POST['subject']), ENT_QUOTES, 'UTF-8');
    $user_message = htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8');

    // Validatation of input
    if (empty($name) || empty($email) || empty($subject) || empty($user_message)) {
        $message = 'All fields are required. Please fill out the form completely.';
        $status = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $status = 'error';
    } else {
        $to = 'smartuchaguzi255@gmail.com';
        $email_subject = "SmartUchaguzi Contact Form: $subject";
        $email_body = "You have received a new message from the SmartUchaguzi contact form.\n\n";
        $email_body .= "Name: $name\n";
        $email_body .= "Email: $email\n";
        $email_body .= "Subject: $subject\n";
        $email_body .= "Message:\n$user_message\n";

        $headers = "From: SmartUchaguzi <noreply@smartuchaguzi.com>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (mail($to, $email_subject, $email_body, $headers)) {
            $message = 'Thank you for your message! We will get back to you soon.';
            $status = 'success';
        } else {
            $message = 'Sorry, there was an error sending your message. Please try again later.';
            $status = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | SmartUchaguzi</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #2d3748;
            line-height: 1.6;
            min-height: 100vh;
            scroll-behavior: smooth;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(26, 60, 52, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: gradientShift 5s infinite alternate;
        }

        @keyframes gradientShift {
            0% { background: rgba(26, 60, 52, 0.9); }
            100% { background: rgba(44, 82, 76, 0.9); }
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
            border-radius: 50%;
            border: 2px solid #f4a261;
            transition: transform 0.3s ease;
        }

        .header .logo img:hover {
            transform: rotate(360deg);
        }

        .header .logo h1 {
            font-size: 20px;
            font-weight: 600;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .breadcrumb {
            font-size: 14px;
        }

        .breadcrumb a {
            color: #e6e6e6;
            text-decoration: none;
            padding: 0 5px;
            position: relative;
            transition: color 0.3s ease;
        }

        .breadcrumb a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: #f4a261;
            transition: width 0.3s ease;
        }

        .breadcrumb a:hover::after {
            width: 100%;
        }

        .breadcrumb a:hover {
            color: #f4a261;
        }

        .container {
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 20px;
        }

        .response-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in-out;
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .response-section h2 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .response-message {
            font-size: 18px;
            margin-bottom: 30px;
            padding: 15px;
            border-radius: 8px;
        }

        .response-message.success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }

        .response-message.error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .back-btn {
            display: inline-block;
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            padding: 12px 35px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .back-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .back-btn::before {
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

        .back-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        footer {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            padding: 40px 20px;
            text-align: center;
            margin-top: 40px;
        }

        footer .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        footer p {
            font-size: 14px;
        }

        footer .footer-links {
            display: flex;
            gap: 20px;
        }

        footer .footer-links a {
            color: #f4a261;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        footer .footer-links a:hover {
            color: #e76f51;
        }

        footer .social-icons {
            display: flex;
            gap: 15px;
        }

        footer .social-icons a {
            color: #e6e6e6;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        footer .social-icons a:hover {
            color: #f4a261;
            transform: scale(1.1);
        }

        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(244, 162, 97, 0.5);
        }

        @media (max-width: 768px) {
            .response-section {
                padding: 30px;
            }

            .response-section h2 {
                font-size: 30px;
            }

            .response-message {
                font-size: 16px;
            }

            footer .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 10px 20px;
            }

            .header .logo h1 {
                font-size: 18px;
            }

            .header .logo img {
                width: 35px;
                height: 35px;
            }

            .response-section {
                padding: 20px;
            }

            .response-section h2 {
                font-size: 24px;
            }

            .response-message {
                font-size: 14px;
                padding: 10px;
            }

            .back-btn {
                padding: 10px 25px;
                font-size: 14px;
            }

            footer .social-icons a {
                font-size: 14px;
            }

            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 20px;
                bottom: 20px;
                right: 20px;
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
                <a href="index.php">Home</a> / <a href="contact-us.html">Contact Us</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="response-section">
            <h2>Contact Us</h2>
            <div class="response-message <?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <a href="contact-us.html" class="back-btn">Back to Contact Form</a>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>© 2025 SmartUchaguzi | Group 4, University of Dodoma</p>
            <div class="footer-links">
                <a href="index.html">Home</a>
                <a href="about-us.html">About Us</a>
                <a href="privacy-policy.html">Privacy Policy</a>
            </div>
            <div class="social-icons">
                <a href="https://instagram.com/smartuchaguzi" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                <a href="https://facebook.com/smartuchaguzi" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/SmartUchaguzi" target="_blank" rel="noopener"><i class="fab fa-x-twitter"></i></a>
                <a href="https://wa.me/255719950708" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

    <a href="#" class="back-to-top" id="back-to-top"><i class="fas fa-chevron-up"></i></a>

    <script>
        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('back-to-top');
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
    </script>
</body>
</html>