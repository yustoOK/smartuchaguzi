<?php
require 'db.php';

// Fetch all elections
$stmt = $pdo->prepare("SELECT * FROM elections");
$stmt->execute();
$elections = $stmt->fetchAll();

// Default to the first election if none selected
$selected_election_id = $_GET['election_id'] ?? $elections[0]['id'] ?? null;

// Fetch results for the selected election
$results = [];
$total_votes = 0;
if ($selected_election_id) {
    $stmt = $pdo->prepare("SELECT c.id, c.full_name, c.party, COUNT(v.id) as vote_count 
                           FROM candidates c 
                           LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ? 
                           WHERE c.election_id = ?
                           GROUP BY c.id");
    $stmt->execute([$selected_election_id, $selected_election_id]);
    $results = $stmt->fetchAll();

    // Calculate total votes for percentage
    foreach ($results as $result) {
        $total_votes += $result['vote_count'];
    }
}

// Prepare data for the chart
$labels = [];
$vote_counts = [];
$colors = [];
$percentages = [];
foreach ($results as $result) {
    $labels[] = $result['full_name'] . ' (' . $result['party'] . ')';
    $vote_counts[] = $result['vote_count'];
    $colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    $percentage = $total_votes > 0 ? round(($result['vote_count'] / $total_votes) * 100, 2) : 0;
    $percentages[] = $percentage;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Statistics - SmartUchaguzi</title>
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

        body.dark-mode .download-btn {
            background-color: #2c7873;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            /* Adjusted shadow for dark mode */
        }

        body.dark-mode .download-btn:hover {
            background-color: #1f5a54;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
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

        .result-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }

        .result-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: calc(33.33% - 20px);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        body.dark-mode .result-item {
            background: #2a2a2a;
        }

        .result-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .result-item p {
            margin: 5px 0;
        }

        .percentage {
            font-weight: bold;
            color: #2c7873;
        }

        body.dark-mode .percentage {
            color: #66b0a8;
        }

        .chart-container {
            margin: 30px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .chart-container {
            background: #2a2a2a;
        }

        canvas {
            max-width: 100%;
        }

        .download-btn {
            display: inline-block;
            /* Changed from block to inline-block to fit content */
            margin: 15px auto;
            /* Increased margin for better spacing */
            padding: 12px 25px;
            /* Slightly increased padding */
            background-color: #2c7873;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            /* Slightly larger radius */
            text-align: center;
            font-weight: 500;
            /* Added font weight */
            transition: all 0.3s ease;
            /* Changed to 'all' for more properties */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            /* Added subtle shadow */
        }

        .download-btn:hover {
            background-color: #1f5a54;
            transform: translateY(-2px);
            /* Added slight lift effect */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            /* Enhanced shadow on hover */
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

            .result-item {
                width: calc(50% - 20px);
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

            .result-item {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="breadcrumb">
            <a href="index.html">Home</a>
            <span>/</span>
            <a href="#">Statistics</a>
        </div>
        <h1>Election Statistics</h1>
    </div>
    <div class="container">
        <div class="filter">
            <select onchange="location = this.value;">
                <option value="">Select Election</option>
                <?php foreach ($elections as $election): ?>
                    <option value="statistics.php?election_id=<?php echo $election['id']; ?>" <?php echo $selected_election_id == $election['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$selected_election_id || empty($elections)): ?>
        <p style="text-align: center; color: #666;">No elections available. Please select an election.</p>
    <?php else: ?>
        <div class="election">
            <!-- ... previous election content ... -->
            <?php if (!empty($results)): ?>
                <div class="chart-container">
                    <canvas id="voteChart"></canvas>
                    <a href="#" class="download-btn" onclick="downloadChart()">Download Graph</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Load dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }

        // Chart for vote counts
        const labels = <?php echo json_encode($labels); ?>;
        const voteCounts = <?php echo json_encode($vote_counts); ?>;
        const colors = <?php echo json_encode($colors); ?>;

        const ctx = document.getElementById('voteChart').getContext('2d');
        const voteChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Vote Count',
                    data: voteCounts,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Votes'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Candidates'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Election Results Histogram'
                    }
                }
            }
        });

        // Function to download the chart as an image
        function downloadChart() {
            const canvas = document.getElementByd('voteChart');
            const link = document.createElement('a');
            link.download = 'election-results.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
    </script>
</body>

</html>