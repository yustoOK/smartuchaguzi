<?php

include 'db.php'; // Database connection
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.html');
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UDOMASA | Dashboard</title>
  <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
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
      color: #2d3748;
      line-height: 1.6;
      min-height: 100vh;
    }

    .header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background: linear-gradient(135deg, #1a3c34, #2d3748);
      padding: 15px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      z-index: 1000;
    }

    .header .logo {
      display: flex;
      align-items: center;
    }

    .header .logo img {
      width: 50px;
      height: 50px;
      margin-right: 15px;
    }

    .header .logo h1 {
      font-size: 24px;
      color: #e6e6e6;
      font-weight: 600;
    }

    .header .nav {
      display: flex;
      gap: 20px;
    }

    .header .nav a {
      color: #e6e6e6;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      padding: 10px 20px;
      border-radius: 30px;
      background: rgba(255, 255, 255, 0.1);
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
    }

    .header .nav a.active {
      background: #f4a261;
      color: #fff;
    }

    .header .nav a:hover {
      transform: scale(1.1);
      background: #f4a261;
      color: #fff;
      box-shadow: 0 0 15px rgba(244, 162, 97, 0.5);
    }

    .header .nav a::before {
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

    .header .nav a:hover::before {
      width: 200px;
      height: 200px;
    }

    .header .user {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .header .user span {
      font-size: 16px;
      color: #e6e6e6;
    }

    .header .user img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid #f4a261;
    }

    .header .user a {
      background: #f4a261;
      color: #fff;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .header .user a:hover {
      background: #e76f51;
      box-shadow: 0 0 10px rgba(231, 111, 81, 0.5);
    }

    .dashboard {
      padding: 100px 20px 20px;
    }

    .dash-content {
      max-width: 1200px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.9);
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .dash-content h2 {
      font-size: 28px;
      color: #1a3c34;
      margin-bottom: 30px;
      text-align: center;
      background: linear-gradient(to right, #1a3c34, #f4a261);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .overview {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }

    .overview .card {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .overview .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .overview .card i {
      font-size: 30px;
      color: #f4a261;
      margin-bottom: 10px;
    }

    .overview .card .text {
      font-size: 16px;
      color: #4a5568;
    }

    .overview .card .number {
      font-size: 24px;
      font-weight: 600;
      color: #1a3c34;
      margin-top: 5px;
    }

    .content-section {
      display: none;
    }

    .content-section.active {
      display: block;
    }

    .election-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .election-card {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .election-card:hover {
      transform: translateY(-5px);
    }

    .election-card h3 {
      font-size: 20px;
      color: #1a3c34;
      margin-bottom: 15px;
      background: linear-gradient(to right, #1a3c34, #2d3748);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .election-card .candidate {
      margin: 10px 0;
      padding: 10px;
      background: #f5f5f5;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background 0.3s ease;
    }

    .election-card .candidate:hover {
      background: #e8ecef;
    }

    .election-card .candidate span {
      font-size: 16px;
      color: #2d3748;
    }

    .election-card .candidate a {
      color: #f4a261;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s ease;
    }

    .election-card .candidate a:hover {
      color: #e76f51;
    }

    .vote-section table {
      width: 100%;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      border-collapse: collapse;
    }

    .vote-section th {
      background: #1a3c34;
      color: #e6e6e6;
      padding: 12px;
    }

    .vote-section td {
      padding: 12px;
      border-bottom: 1px solid #e8ecef;
    }

    .vote-section button {
      background: #f4a261;
      color: #fff;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .vote-section button:hover {
      background: #e76f51;
      transform: scale(1.05);
    }

    .verify-section {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .verify-section p {
      font-size: 16px;
      color: #4a5568;
      margin-bottom: 20px;
    }

    .verify-section .hash {
      font-family: monospace;
      background: #f5f5f5;
      padding: 10px;
      border-radius: 8px;
      word-break: break-all;
      color: #1a3c34;
    }

    .analytics-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .analytics-section .chart {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
      transition: transform 0.3s ease;
    }

    .analytics-section .chart:hover {
      transform: translateY(-5px);
    }

    .analytics-section .chart h4 {
      font-size: 18px;
      color: #1a3c34;
      margin-bottom: 10px;
    }

    .quick-links {
      margin-top: 40px;
      text-align: center;
    }

    .quick-links h3 {
      font-size: 22px;
      color: #1a3c34;
      margin-bottom: 15px;
    }

    .quick-links ul {
      list-style: none;
      display: flex;
      justify-content: center;
      gap: 20px;
      flex-wrap: wrap;
    }

    .quick-links ul li a {
      display: block;
      padding: 10px 20px;
      background: #fff;
      border-radius: 8px;
      color: #f4a261;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .quick-links ul li a:hover {
      background: #f4a261;
      color: #fff;
      transform: scale(1.05);
    }

    /* Responsiveness */
    @media (max-width: 768px) {
      .header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 10px;
      }

      .header .nav {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
      }

      .overview, .election-cards, .analytics-section {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .header .logo h1 {
        font-size: 20px;
      }

      .header .nav a {
        padding: 8px 14px;
        font-size: 14px;
      }

      .header .user img {
        width: 30px;
        height: 30px;
      }
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="logo">
      <img src="./uploads/Vote.jpeg" alt="SmartUchaguzi Logo">
      <h1>SmartUchaguzi</h1>
    </div>
    <div class="nav">
      <a href="#" data-section="election" class="active">Election</a>
      <a href="#" data-section="vote">Vote</a>
      <a href="#" data-section="verify">Verify Vote</a>
      <a href="#" data-section="analytics">Analytics</a>
    </div>
    <div class="user">
      <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
      <img src="./images/user-placeholder.jpg" alt="User">
      <a href="logout.php">Logout</a>
    </div>
  </header>

  <section class="dashboard">
    <div class="dash-content">
      <h2>UDOMASA Election Dashboard</h2>

      <div class="overview">
        <div class="card">
          <i class="uil uil-users-alt"></i>
          <span class="text">Election Candidates</span>
          <span class="number"><?php echo $db->query("SELECT COUNT(*) FROM candidates WHERE election_type='UDOMASA'")->fetch_row()[0]; ?></span>
        </div>
        <div class="card">
          <i class="uil uil-clock"></i>
          <span class="text">Remaining Time</span>
          <span class="number" id="timer">Loading...</span>
        </div>
        <div class="card">
          <i class="uil uil-check-circle"></i>
          <span class="text">Voting Status</span>
          <span class="number"><?php echo $db->query("SELECT COUNT(*) FROM votes WHERE user_id='{$_SESSION['user_id']}' AND election_type='UDOMASA'")->fetch_row()[0] > 0 ? 'Voted' : 'Not Voted'; ?></span>
        </div>
      </div>

      <div class="content-section active" id="election">
        <h3>Current Election</h3>
        <div class="election-cards">
          <?php
          $positions = $db->query("SELECT DISTINCT position FROM candidates WHERE election_type='UDOMASA'");
          while ($pos = $positions->fetch_assoc()) {
            echo "<div class='election-card'>";
            echo "<h3>" . htmlspecialchars($pos['position']) . "</h3>";
            $candidates = $db->query("SELECT id, name FROM candidates WHERE position='" . $pos['position'] . "' AND election_type='UDOMASA'");
            while ($cand = $candidates->fetch_assoc()) {
              echo "<div class='candidate'><span>" . htmlspecialchars($cand['name']) . "</span><a href='candidate-details.php?id=" . $cand['id'] . "'>Details</a></div>";
            }
            echo "</div>";
          }
          ?>
        </div>
      </div>

      <div class="content-section" id="vote">
        <h3>Cast Your Vote</h3>
        <div class="vote-section">
          <table>
            <tr>
              <th>Position</th>
              <th>Candidate</th>
              <th>Action</th>
            </tr>
            <?php
            $positions = $db->query("SELECT DISTINCT position FROM candidates WHERE election_type='UDOMASA'");
            while ($pos = $positions->fetch_assoc()) {
              echo "<tr>";
              echo "<td>" . htmlspecialchars($pos['position']) . "</td>";
              echo "<td><select name='candidate_" . $pos['position'] . "'>";
              $candidates = $db->query("SELECT id, name FROM candidates WHERE position='" . $pos['position'] . "' AND election_type='UDOMASA'");
              while ($cand = $candidates->fetch_assoc()) {
                echo "<option value='" . $cand['id'] . "'>" . htmlspecialchars($cand['name']) . "</option>";
              }
              echo "</select></td>";
              echo "<td><button onclick='submitVote(\"UDOMASA\", \"" . $pos['position'] . "\")'>Vote</button></td>";
              echo "</tr>";
            }
            ?>
          </table>
        </div>
      </div>

      <div class="content-section" id="verify">
        <h3>Verify Your Vote</h3>
        <div class="verify-section">
          <p>Check your vote's blockchain record to ensure its integrity.</p>
          <?php
          $vote = $db->query("SELECT blockchain_hash FROM votes WHERE user_id='{$_SESSION['user_id']}' AND election_type='UDOMASA' LIMIT 1")->fetch_assoc();
          if ($vote && $vote['blockchain_hash']) {
            echo "<div class='hash'>" . htmlspecialchars($vote['blockchain_hash']) . "</div>";
          } else {
            echo "<p>No vote recorded yet.</p>";
          }
          ?>
        </div>
      </div>

      <div class="content-section" id="analytics">
        <h3>Election Analytics</h3>
        <div class="analytics-section">
          <div class="chart">
            <h4>Voter Turnout</h4>
            <p><?php echo round(($db->query("SELECT COUNT(*) FROM votes WHERE election_type='UDOMASA'")->fetch_row()[0] / $db->query("SELECT COUNT(*) FROM students WHERE college='CNMS'")->fetch_row()[0]) * 100, 1); ?>%</p>
          </div>
          <div class="chart">
            <h4>Total Votes</h4>
            <p><?php echo $db->query("SELECT COUNT(*) FROM votes WHERE election_type='UDOMASA'")->fetch_row()[0]; ?></p>
          </div>
        </div>
      </div>

      <div class="quick-links">
        <h3>Quick Links</h3>
        <ul>
          <li><a href="profile.php">My Profile</a></li>
          <li><a href="election-rules.php">Election Rules</a></li>
          <li><a href="contact.php">Support</a></li>
        </ul>
      </div>
    </div>
  </section>

  <script>
    const links = document.querySelectorAll('.header .nav a');
    const sections = document.querySelectorAll('.content-section');
    links.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const sectionId = link.getAttribute('data-section');
        sections.forEach(section => section.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');
        links.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
      });
    });

    const electionEnd = new Date('2025-06-10T23:59:59').getTime(); 
    function updateTimer() {
      const now = new Date().getTime();
      const distance = electionEnd - now;
      if (distance < 0) {
        document.getElementById('timer').innerHTML = 'Election Ended';
        return;
      }
      const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((distance % (1000 * 60)) / 1000);
      document.getElementById('timer').innerHTML = `${hours}:${minutes}:${seconds}`;
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    async function submitVote(electionType, position) {
      const candidateId = document.querySelector(`select[name="candidate_${position}"]`).value;
      const response = await fetch('/api/submit-vote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ candidate_id: candidateId, election_type: electionType })
      });
      if (response.ok) {
        alert('Vote submitted successfully!');
        location.reload();
      } else {
        alert('Error submitting vote.');
      }
    }
  </script>
</body>
</html>