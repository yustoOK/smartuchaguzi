<?php
require 'db.php';

// Fetch all elections
$stmt = $pdo->prepare("SELECT * FROM elections");
$stmt->execute();
$elections = $stmt->fetchAll();

// Default to the first election if none selected
$selected_election_id = $_GET['election_id'] ?? $elections[0]['id'] ?? null;

// Fetch candidates for the selected election
$candidates = [];
if ($selected_election_id) {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ?");
    $stmt->execute([$selected_election_id]);
    $candidates = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Profiles - SmartUchaguzi</title>
    <link rel="icon" href="./uploads/Vote.jpeg" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            transition: background-color 0.3s ease;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #2c7873;
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            text-align: center;
        }

        .breadcrumb {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
        }

        .breadcrumb a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .breadcrumb a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .breadcrumb span {
            color: #ddd;
            margin: 0 5px;
        }

        .filter {
            margin: 20px 0;
            text-align: center;
        }

        .filter select {
            padding: 12px 20px;
            border-radius: 6px;
            border: none;
            font-size: 16px;
            width: 100%;
            max-width: 350px;
            background-color: #fff;
            color: #333;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23333" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter select:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .filter select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(44, 120, 115, 0.3);
        }

        body.dark-mode .filter select {
            background-color: #2a2a2a;
            color: #e0e0e0;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23e0e0e0" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
        }

        .election {
            margin-bottom: 40px;
        }

        .election h2 {
            color: #1f5a54;
            margin-bottom: 20px;
            text-align: center;
        }

        body.dark-mode .election h2 {
            color: #66b0a8;
        }

        .candidate-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }

        .candidate {
            background: white;
            padding: 20px;
            /* Increased padding */
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: calc(33.33% - 20px);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        body.dark-mode .candidate {
            background: #2a2a2a;
        }

        .candidate:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .candidate img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            /* Increased margin */
            border: 2px solid #2c7873;
            /* Added border */
        }

        .candidate p {
            margin: 8px 0;
            /* Increased spacing */
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 10px 20px;
            }

            .header h1 {
                font-size: 20px;
            }

            .breadcrumb a {
                font-size: 14px;
            }

            .filter select {
                max-width: 300px;
            }

            .election h2 {
                font-size: 20px;
            }

            .candidate {
                width: calc(50% - 20px);
            }

            .candidate img {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }

            .header {
                padding: 8px 15px;
            }

            .header h1 {
                font-size: 18px;
            }

            .breadcrumb a {
                font-size: 12px;
                padding: 3px 6px;
            }

            .filter select {
                max-width: 250px;
            }

            .election h2 {
                font-size: 18px;
            }

            .candidate {
                width: 100%;
            }

            .candidate img {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="breadcrumb">
            <a href="index.html">Home</a>
            <span>/</span>
            <a href="#">Candidates</a>
        </div>
        <h1>Candidate Profiles</h1>
    </div>
    <div class="container">
        <div class="filter">
            <select onchange="location = this.value;">
                <option value="">Select Election</option>
                <?php foreach ($elections as $election): ?>
                    <option value="candidates.php?election_id=<?php echo $election['id']; ?>" <?php echo $selected_election_id == $election['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$selected_election_id || empty($elections)): ?>
            <p style="text-align: center; color: #666;">No elections available. Please select an election.</p>
        <?php else: ?>
            <div class="election">
                <h2><?php echo htmlspecialchars($elections[array_search($selected_election_id, array_column($elections, 'id'))]['name']); ?></h2>
                <?php if (empty($candidates)): ?>
                    <p style="text-align: center; color: #666;">No candidates available for this election.</p>
                <?php else: ?>
                    <div class="candidate-list">
                        <?php foreach ($candidates as $candidate): ?>
                            <div class="candidate">
                                <img src="<?php echo htmlspecialchars($candidate['profile_photo']); ?>" alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($candidate['full_name']); ?></p>
                                <p><strong>Party:</strong> <?php echo htmlspecialchars($candidate['party']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Load dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>

</html>