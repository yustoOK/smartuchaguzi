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

        body {
            background-color: #f5f5f5;
            color: #2d3748;
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(26, 60, 52, 0.9);
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
            0% { background: rgba(26, 60, 52, 0.9); }
            100% { background: rgba(44, 82, 76, 0.9); }
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
            background: linear-gradient(rgba(26, 60, 52, 0.85), rgba(26, 60, 52, 0.85)), url('./uploads/Vote.jpeg');
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

        /* Elections Section */
        .elections {
            max-width: 1200px;
            margin: 100px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
        }

        .election-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .election-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }

        .election-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 2px solid #f4a261;
        }

        .election-card img:not([src]),
        .election-card img[src=""] {
            background: #e6e6e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #718096;
            font-size: 16px;
            text-align: center;
        }

        .election-card .content {
            padding: 25px;
        }

        .election-card h3 {
            color: #1a3c34;
            font-size: 24px;
            margin-bottom: 15px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .election-card p {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 20px;
        }

        .election-card a {
            color: #f4a261;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .election-card a:hover {
            color: #e76f51;
            text-decoration: underline;
        }

        /* How It Works Section */
        .how-it-works {
            background: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.95));
            padding: 80px 20px;
            text-align: center;
        }

        .how-it-works h2 {
            font-size: 36px;
            color: #1a3c34;
            margin-bottom: 40px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .steps {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .step {
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .step:hover {
            transform: translateY(-5px);
        }

        .step h3 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .step p {
            font-size: 16px;
            color: #718096;
        }

        /* Security Features Section */
        .security-features {
            max-width: 1200px;
            margin: 80px auto;
            padding: 0 20px;
            text-align: center;
        }

        .security-features h2 {
            font-size: 36px;
            color: #1a3c34;
            margin-bottom: 40px;
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-5px);
        }

        .feature h3 {
            font-size: 22px;
            color: #1a3c34;
            margin-bottom: 15px;
        }

        .feature p {
            font-size: 16px;
            color: #4a5568;
        }

        /* Upcoming Elections Section */
        .upcoming-elections {
            background: linear-gradient(rgba(26, 60, 52, 0.95), rgba(26, 60, 52, 0.95));
            color: #e6e6e6;
            padding: 80px 20px;
            text-align: center;
        }

        .upcoming-elections h2 {
            font-size: 36px;
            margin-bottom: 40px;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .election-list {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .election-item {
            padding: 20px;
            border: 1px solid #f4a261;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .election-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
        }

        .election-item h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .election-item p {
            font-size: 16px;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            padding: 40px 20px;
            text-align: center;
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
            transition: color 0.3s ease;
        }

        footer .social-icons a:hover {
            color: #f4a261;
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
                background: rgba(26, 60, 52, 0.9);
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

            .elections,
            .steps,
            .features-grid,
            .election-list {
                grid-template-columns: 1fr;
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

            .how-it-works h2,
            .security-features h2,
            .upcoming-elections h2 {
                font-size: 28px;
            }
        }
        

        body {
            background-color: #f5f5f5;
            color: #2d3748;
            line-height: 1.6;
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
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: gradientShift 5s infinite alternate;
        }

        @keyframes gradientShift {
            0% {
                background: rgba(26, 60, 52, 0.9);
            }

            100% {
                background: rgba(44, 82, 76, 0.9);
            }
        }

        /* [Rest of CSS unchanged] */
        .upcoming-elections {
            background: linear-gradient(rgba(26, 60, 52, 0.95), rgba(26, 60, 52, 0.95));
            color: #e6e6e6;
            padding: 80px 20px;
            text-align: center;
        }

        .upcoming-elections h2 {
            font-size: 36px;
            margin-bottom: 40px;
            background: linear-gradient(to right, #f4a261, #e76f51);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .election-list {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .election-item {
            padding: 20px;
            border: 1px solid #f4a261;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .election-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(244, 162, 97, 0.3);
        }

        .election-item h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .election-item p {
            font-size: 16px;
        }

        /* [Rest of CSS unchanged] */
    </style>
</head>

<body>
<div class="header">
        <div class="logo">
            <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
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

    <div class="elections">
        <div class="election-card">
            <img src="images/udoso.jpeg" alt="UDOSO Elections" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='Image Not Available';">
            <div class="content">
                <h3>UDOSO Elections</h3>
                <p>Elect student leaders such as President, Vice President, and Secretary General for the University of
                    Dodoma Students' Organization.</p>
                <a href="candidates.php?type=udoso">View Candidates</a>
            </div>
        </div>
        <div class="election-card">
            <img src="images/teachers.jpeg" alt="UDOMASA Elections" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='Image Not Available';">
            <div class="content">
                <h3>UDOMASA Elections</h3>
                <p>Vote for Chairperson, Treasurer, and other key roles in the University of Dodoma Academic Staff
                    Association.</p>
                <a href="candidates.php?type=udomasa">View Candidates</a>
            </div>
        </div>
    </div>

    <div class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <h3>1. Register</h3>
                <p>Create an account with your university credentials to join the SmartUchaguzi platform.</p>
            </div>
            <div class="step">
                <h3>2. Vote Securely</h3>
                <p>Cast your vote using our blockchain-secured system, ensuring tamper-proof results.</p>
            </div>
            <div class="step">
                <h3>3. Track Results</h3>
                <p>Monitor election outcomes in real-time, verified by neural network analysis, with efficient graphs and an amazing canvas.</p>
            </div>
        </div>
    </div>

    <div class="security-features">
        <h2>Advanced Security Features</h2>
        <div class="features-grid">
            <div class="feature">
                <h3>Blockchain Technology</h3>
                <p>Every vote is encrypted and stored on a decentralized ledger, ensuring transparency and immutability.</p>
            </div>
            <div class="feature">
                <h3>Neural Network Verification</h3>
                <p>Our AI-driven system detects anomalies and verifies voter authenticity in real-time.</p>
            </div>
            <div class="feature">
                <h3>End-to-End Encryption</h3>
                <p>Your data is protected with military-grade encryption from registration to vote submission.</p>
            </div>
        </div>
    </div>    
<div class="upcoming-elections">
        <h2>Upcoming Elections</h2>
        <?php include 'fetch-upcoming.php'; ?>
    </div>
    <footer>
        <div class="footer-content">
            <p>© Copyright 2025 SmartUchaguzi - University of Dodoma Elections | Powered by Blockchain & Neural Networks</p>
            <div class="footer-links">
                <a href="about-us.html">About Us</a>
                <a href="contact-us.html">Contact Us</a>
                <a href="privacy-policy.html">Privacy Policy</a>
            </div>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
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