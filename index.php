<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartUchaguzi | University of Dodoma Elections</title>
    <link rel="icon" href="./Uploads/Vote.jpeg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            background-color: #f5f5f5;
            color: #2d3748;
            line-height: 1.6;
            scroll-behavior: smooth;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(50, 90, 80, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: gradientShift 5s infinite alternate;
        }

        @keyframes gradientShift {
            0% { background: rgba(50, 90, 80, 0.9); }
            100% { background: rgba(70, 110, 100, 0.9); }
        }

        .header .logo {
            display: flex;
            align-items: center;
        }

        .header .logo img {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            border-radius: 50%;
            border: 2px solid #f4a261;
            transition: transform 0.3s ease;
        }

        .header .logo img:hover {
            transform: rotate(360deg);
        }

        .header .logo h1 {
            font-size: 26px;
            font-weight: 600;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .nav-menu {
            display: flex;
            gap: 30px;
        }

        .nav-menu a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            padding: 10px 20px;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #f4a261;
            transition: width 0.3s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .nav-menu a:hover {
            color: #f4a261;
        }

        .hamburger {
            display: none;
            font-size: 28px;
            background: none;
            border: none;
            color: #e6e6e6;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(26, 60, 52, 0.5), rgba(26, 60, 52, 0.5)), url('./Uploads/background.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #e6e6e6;
            padding: 0 20px;
            flex: 1 0 auto; /* Allows the hero to grow and push the footer down */
        }

        .hero-content {
            max-width: 900px;
            animation: fadeIn 1s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero h1 {
            font-size: 50px;
            font-weight: 700;
            margin-bottom: 25px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 35px;
            color: #d1d5db;
        }

        .cta-btn {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            color: #fff;
            padding: 14px 35px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
        }

        .cta-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(244, 162, 97, 0.5);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #2a5c54, #3d5768);
            color: #e6e6e6;
            padding: 20px;
            text-align: center;
            flex: 0 0 auto; /* Footer takes only the space it needs */
        }

        footer .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        footer p {
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
            }

            .nav-menu {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: rgba(50, 90, 80, 0.9);
                backdrop-filter: blur(10px);
                flex-direction: column;
                padding: 20px;
                text-align: center;
            }

            .nav-menu.active {
                display: flex;
            }

            .hamburger {
                display: block;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 18px;
            }

            footer .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .header .logo h1 {
                font-size: 20px;
            }

            .header .logo img {
                width: 40px;
                height: 40px;
            }

            .hero h1 {
                font-size: 28px;
            }

            .hero p {
                font-size: 16px;
            }

            .cta-btn {
                padding: 10px 25px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="logo">
            <img src="./images/System Logo.jpg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="nav-menu" id="nav-menu">
            <a href="register.php">Register</a>
            <a href="login.php">Login</a>
            <a href="about-us.html">About Us</a>
            <a href="contact-us.html">Contact Us</a>
        </div>
        <button class="hamburger" onclick="toggleMenu()">☰</button>
    </div>

    <div class="hero">
        <div class="hero-content">
            <h1>Empower Your Vote with SmartUchaguzi</h1>
            <p>A secure, transparent online voting platform for University of Dodoma elections, powered by blockchain
                and neural networks.</p>
            <a href="register.php" class="cta-btn">Join Now</a>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>© 2025 SmartUchaguzi - UDOM Elections | Powered by Blockchain & Neural Networks</p>
        </div>
    </footer>

    <script>
        function toggleMenu() {
            const navMenu = document.getElementById('nav-menu');
            navMenu.classList.toggle('active');
        }
    </script>
</body>

</html>