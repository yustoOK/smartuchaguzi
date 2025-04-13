<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | SmartUchaguzi</title>
    <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #2d3748;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        .container {
            max-width: 1000px; 
            margin: 100px auto 40px;
            padding: 20px;
            flex: 1;
        }

        .contact-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 50px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-section h2 {
            font-size: 40px; 
            color: #1a3c34;
            text-align: center;
            margin-bottom: 40px; 
            background: linear-gradient(to right, #1a3c34, #f4a261);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }

        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 30px; /* Increased gap for better spacing */
            max-width: 800px; /* Ensure the form takes up more space */
            margin: 0 auto; /* Center the form within the section */
        }

        .contact-form label {
            font-weight: 500;
            color: #1a3c34;
            font-size: 18px; /* Larger label text */
        }

        .contact-form textarea {
            padding: 15px; 
            border: none;
            border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 16px;
            resize: vertical;
            min-height: 200px; 
            transition: box-shadow 0.3s ease;
        }

        .contact-form textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 162, 97, 0.3);
        }

        .contact-form button {
            background: linear-gradient(135deg, #f4a261, #e76f51);
            border: none;
            color: #fff;
            padding: 15px 40px; /* Larger button with more padding */
            border-radius: 8px; /* Slightly larger border-radius */
            font-size: 18px; /* Larger font size */
            cursor: pointer;
            align-self: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .contact-form button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
        }

        .contact-form button::before {
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

        .contact-form button:hover::before {
            width: 200px;
            height: 200px;
        }

        .success-message {
            text-align: center;
            color: #1a3c34;
            margin-top: 30px; 
            font-size: 18px; 
            background: rgba(255, 255, 255, 0.8);
            padding: 15px; /* More padding */
            border-radius: 8px;
        }

        footer {
            background: linear-gradient(135deg, #1a3c34, #2d3748);
            color: #e6e6e6;
            text-align: center;
            padding: 20px;
            width: 100%;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .container {
                max-width: 90%; 
            }

            .contact-section {
                padding: 40px;
            }

            .contact-section h2 {
                font-size: 34px;
            }

            .contact-form {
                gap: 25px;
            }

            .contact-form textarea {
                min-height: 180px;
            }

            .contact-form button {
                padding: 12px 30px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .container {
                max-width: 95%;
            }

            .contact-section {
                padding: 30px;
            }

            .contact-section h2 {
                font-size: 28px;
            }

            .contact-form {
                gap: 20px;
            }

            .contact-form label {
                font-size: 16px;
            }

            .contact-form textarea {
                min-height: 150px;
                font-size: 14px;
            }

            .contact-form button {
                padding: 10px 25px;
                font-size: 14px;
            }

            .success-message {
                font-size: 16px;
                padding: 10px;
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
                <a href="index.html">Home</a> / <a href="#">Contact</a>
            </div>
        </div>
    </div>

    <!--  
    <div class="container">
        <div class="contact-section">
            <h2>Get in Touch</h2>
            <form class="contact-form" action="submit_feedback.php" method="POST">
                <label for="message">Your Message</label>
                <textarea id="message" name="message" required></textarea>
                <button type="submit">Send Feedback</button>
            </form>
            <?php if (isset($_GET['success'])): ?>
                <p class="success-message">Feedback submitted successfully!</p>
            <?php endif; ?>
        </div>
    </div>
    -->
    <footer>
        <p>© 2025 SmartUchaguzi | Group 4, University of Dodoma</p>
    </footer>
</body>
</html>