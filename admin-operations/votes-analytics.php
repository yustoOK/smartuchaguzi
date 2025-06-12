<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Unable to connect to the database");
}

$required_role = 'admin';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
    header('Location: ../login.php?error=' . urlencode('Please log in as an admin.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: ../2fa.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT fname, mname, lname, college_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_name = htmlspecialchars($user['fname'] . ' ' . ($user['mname'] ? $user['mname'] . ' ' : '') . $user['lname']);
} catch (Exception $e) {
    error_log("User query error: " . $e->getMessage());
    header('Location: ../login.php?error=' . urlencode('Error logging in'));
    exit;
}

$college_name = '';
if ($user['college_id']) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM colleges WHERE college_id = ?");
        $stmt->execute($user['college_id']);
        $college_name = $stmt->fetchColumn() ?: 'Unknown';
    } catch (PDOException $e) {
        error_log("College query failed: " . $e->getMessage());
        $college_name = 'Unknown';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Votes Analytics | SmartUchaguzi</title>
    <link rel="icon" href="../images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js" defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('../images/cive.jpeg');
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
        }
        .header {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .logo {
            display: flex;
            align-items: center;
        }
        .logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }
        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            color: #e6e6e6;
            cursor: pointer;
        }
        .nav a {
            color: #e6e6e6;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
            transition: color 0.3s ease;
        }
        .nav a:hover {
            color: #f4a261;
        }
        .user {
            display: flex;
            align-items: center;
        }
        .user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }
        .user a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
        }
        .dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .dropdown a,
        .dropdown span {
            display: block;
            padding: 10px 20px;
            color: #2d3748;
            text-decoration: none;
            font-size: 16px;
        }
        .dropdown a:hover {
            background: #f4a261;
            color: #fff;
        }
        .logout-link {
            display: none;
            color: #e6e6e6;
            text-decoration: none;
            font-size: 16px;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100%;
            background: #1a3c34;
            padding-top: 80px;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 900;
        }
        .sidebar .nav {
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }
        .sidebar .nav a {
            color: #e6e6e6;
            text-decoration: none;
            font-size: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .sidebar .nav a.active {
            background: #f4a261;
            color: #fff;
        }
        .sidebar .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            margin-left: 260px;
            padding: 80px 1rem 2rem;
            min-height: 100vh;
        }
        .dash-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 1200px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
        }
        h3 {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }
        .analytics-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .analytics-filter label {
            font-size: 1rem;
            color: #2d3748;
        }
        .analytics-filter select {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        .analytics-filter button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }
        .analytics-filter button:hover {
            background: #e76f51;
        }
        .analytics-filter button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .vote-analytics {
            margin-top: 20px;
        }
        .vote-analytics h4 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 15px;
            text-align: center;
        }
        .vote-analytics p {
            font-size: 14px;
            color: #2d3748;
            text-align: center;
        }
        .vote-analytics canvas {
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .error {
            color: #e76f51;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }
        footer {
            background: #1a3c34;
            color: #e6e6e6;
            padding: 15px;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .menu-toggle {
                display: block;
            }
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                padding: 10px 20px;
            }
            .logo h1 {
                font-size: 20px;
            }
            .nav {
                margin: 10px 0;
                text-align: center;
            }
            .nav a {
                margin: 0 10px;
                font-size: 14px;
            }
            .user img {
                display: none;
            }
            .dropdown {
                display: block;
                position: static;
                box-shadow: none;
                background: none;
                text-align: center;
            }
            .dropdown a,
            .dropdown span {
                color: #e6e6e6;
                padding: 5px 10px;
            }
            .dropdown a:hover {
                background: none;
                color: #f4a261;
            }
            .logout-link {
                display: block;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-bars menu-toggle"></i>
            <img src="../images/System Logo.jpg" alt="SmartUchaguzi Logo">
            <h1>SmartUchaguzi</h1>
        </div>
        <div class="user">
            <span><?php echo $admin_name . ($college_name ? ' (' . $college_name . ')' : ''); ?></span>
            <img src="../images/default.png" alt="Profile" onerror="this.src='../images/general.png';">
            <div class="dropdown" id="user-dropdown">
                <span><?php echo htmlspecialchars($admin_name); ?></span>
                <a href="../profile.php">My Profile</a>
                <a href="../logout.php">Logout</a>
            </div>
            <a href="../logout.php" class="logout-link">Logout</a>
        </div>
    </header>

    <aside class="sidebar">
        <div class="nav">
            <a href="../admin-dashboard.php"><i class="fas fa-home"></i> Overview</a>
            <a href="manage-elections.php"><i class="fas fa-cog"></i> Election Management</a>
            <a href="blockchain-verification.php"><i class="fas fa-chain"></i> Blockchain Verification</a>
            <a href="user-management.php"><i class="fas fa-users"></i> User Management</a>
            <a href="votes-analytics.php" class="active"><i class="fas fa-chart-bar"></i> Votes Analytics</a>
            <a href="fraud-incidents.php"><i class="fas fa-exclamation-triangle"></i> Fraud Incidents</a>
            <a href="security-settings.php"><i class="fas fa-shield-alt"></i> Security Settings</a>
            <a href="audit-logs.php"><i class="fas fa-file-alt"></i> Audit Logs</a>
        </div>
    </aside>

    <main class="main-content">
        <section class="dashboard">
            <div class="dash-content">
                <h3>Election Analytics</h3>
                <div class="analytics-filter">
                    <label for="election-select">Select Election:</label>
                    <select id="election-select">
                        <option value="">All Elections</option>
                        <?php
                        try {
                            $stmt = $pdo->prepare("SELECT election_id, CONCAT(association, ' - ', start_time) AS name FROM elections ORDER BY start_time DESC");
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$row['election_id']}'>" . htmlspecialchars($row['name']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            error_log("Election select query error: " . $e->getMessage());
                            echo "<option value=''>Error loading elections</option>";
                        }
                        ?>
                    </select>
                    <form method="POST" action="../api/generate-report.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="election_id" id="report-election-id">
                        <button type="submit" id="download-report" disabled>Download Report (PDF)</button>
                    </form>
                </div>
                <div id="vote-analytics" class="vote-analytics">
                    <p>Select an election to view analytics.</p>
                </div>
            </div>
        </section>
        <footer>
            <p>© 2025 SmartUchaguzi | University of Dodoma</p>
        </footer>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const profilePic = document.querySelector('.user img');
            const userDropdown = document.getElementById('user-dropdown');
            const electionSelect = document.getElementById('election-select');
            const voteAnalytics = document.getElementById('vote-analytics');
            const downloadButton = document.getElementById('download-report');
            const reportElectionId = document.getElementById('report-election-id');

            // Check if ethers is available
            if (typeof ethers === 'undefined') {
                voteAnalytics.innerHTML = '<p class="error">Failed to load ethers library. Please check your internet connection or script source.</p>';
                return;
            }

            const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
            const contractAddress = '0xC046c854C85e56DB6AF41dF3934DD671831d9d09';

            // Directly include the ABI
            const contractABI = [{
                "inputs": [],
                "stateMutability": "nonpayable",
                "type": "constructor"
            },
            {
                "anonymous": false,
                "inputs": [{
                        "indexed": false,
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "indexed": true,
                        "internalType": "address",
                        "name": "voter",
                        "type": "address"
                    },
                    {
                        "indexed": false,
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "indexed": false,
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "name": "VoteCast",
                "type": "event"
            },
            {
                "inputs": [],
                "name": "admin",
                "outputs": [{
                    "internalType": "address",
                    "name": "",
                    "type": "address"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "name": "castVote",
                "outputs": [],
                "stateMutability": "nonpayable",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    }
                ],
                "name": "getVoteCount",
                "outputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                    "internalType": "uint256",
                    "name": "electionId",
                    "type": "uint256"
                }],
                "name": "getVotesByElection",
                "outputs": [{
                    "components": [{
                            "internalType": "uint256",
                            "name": "electionId",
                            "type": "uint256"
                        },
                        {
                            "internalType": "address",
                            "name": "voter",
                            "type": "address"
                        },
                        {
                            "internalType": "uint256",
                            "name": "positionId",
                            "type": "uint256"
                        },
                        {
                            "internalType": "string",
                            "name": "candidateId",
                            "type": "string"
                        },
                        {
                            "internalType": "uint256",
                            "name": "timestamp",
                            "type": "uint256"
                        },
                        {
                            "internalType": "string",
                            "name": "candidateName",
                            "type": "string"
                        },
                        {
                            "internalType": "string",
                            "name": "positionName",
                            "type": "string"
                        }
                    ],
                    "internalType": "struct VoteContract.Vote[]",
                    "name": "",
                    "type": "tuple[]"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "address",
                        "name": "",
                        "type": "address"
                    },
                    {
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "",
                        "type": "string"
                    }
                ],
                "name": "hasVoted",
                "outputs": [{
                    "internalType": "bool",
                    "name": "",
                    "type": "bool"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "",
                        "type": "string"
                    }
                ],
                "name": "voteCount",
                "outputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "stateMutability": "view",
                "type": "function"
            },
            {
                "inputs": [{
                    "internalType": "uint256",
                    "name": "",
                    "type": "uint256"
                }],
                "name": "votes",
                "outputs": [{
                        "internalType": "uint256",
                        "name": "electionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "address",
                        "name": "voter",
                        "type": "address"
                    },
                    {
                        "internalType": "uint256",
                        "name": "positionId",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateId",
                        "type": "string"
                    },
                    {
                        "internalType": "uint256",
                        "name": "timestamp",
                        "type": "uint256"
                    },
                    {
                        "internalType": "string",
                        "name": "candidateName",
                        "type": "string"
                    },
                    {
                        "internalType": "string",
                        "name": "positionName",
                        "type": "string"
                    }
                ],
                "stateMutability": "view",
                "type": "function"
            }];

            const contract = new ethers.Contract(contractAddress, contractABI, provider);

            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            if (profilePic) {
                profilePic.addEventListener('click', () => {
                    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', (e) => {
                    if (!profilePic.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.style.display = 'none';
                    }
                });
            }

            electionSelect.addEventListener('change', async () => {
                const electionId = electionSelect.value;
                voteAnalytics.innerHTML = '<p>Loading analytics...</p>';
                downloadButton.disabled = !electionId;
                reportElectionId.value = electionId;

                if (!electionId) {
                    voteAnalytics.innerHTML = '<p>Select an election to view analytics.</p>';
                    return;
                }

                try {
                    const votes = await contract.getVotesByElection(electionId);
                    if (!votes || votes.length === 0) {
                        voteAnalytics.innerHTML = '<p class="error">No votes found for this election.</p>';
                        return;
                    }

                    const candidateResponse = await fetch(`../api/get-candidates.php?election_id=${electionId}`);
                    const candidatesData = await candidateResponse.json();
                    if (candidatesData.error) {
                        voteAnalytics.innerHTML = `<p class="error">${candidatesData.error}</p>`;
                        return;
                    }

                    const positionsMap = {};
                    votes.forEach(vote => {
                        const positionId = vote.positionId.toString();
                        const candidateId = vote.candidateId.toString();
                        if (!positionsMap[positionId]) {
                            positionsMap[positionId] = {
                                name: vote.positionName,
                                candidates: {}
                            };
                        }
                        if (!positionsMap[positionId].candidates[candidateId]) {
                            positionsMap[positionId].candidates[candidateId] = {
                                name: vote.candidateName,
                                votes: 0
                            };
                        }
                        positionsMap[positionId].candidates[candidateId].votes++;
                    });

                    let html = '<h4>Vote Analytics</h4>';
                    for (const [positionId, pos] of Object.entries(positionsMap)) {
                        const totalVotes = Object.values(pos.candidates).reduce((sum, c) => sum + c.votes, 0);
                        const winner = Object.values(pos.candidates).reduce((a, b) => a.votes > b.votes ? a : b, { votes: 0, name: 'None' });
                        html += `
                            <div>
                                <h4>${pos.name}</h4>
                                <canvas id="chart-${positionId}" style="max-width: 100%;"></canvas>
                                <p>Total Votes: ${totalVotes}</p>
                                <p>Winner: ${winner.name} (${winner.votes} votes)</p>
                            </div>
                        `;
                    }
                    voteAnalytics.innerHTML = html;

                    for (const [positionId, pos] of Object.entries(positionsMap)) {
                        const ctx = document.getElementById(`chart-${positionId}`).getContext('2d');
                        const candidates = Object.values(pos.candidates);
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: candidates.map(c => c.name),
                                datasets: [{
                                    label: 'Votes',
                                    data: candidates.map(c => c.votes),
                                    backgroundColor: '#f4a261',
                                    borderColor: '#e76f51',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: { beginAtZero: true }
                                },
                                plugins: {
                                    title: {
                                        display: true,
                                        text: `${pos.name} Vote Distribution`,
                                        color: '#2d3748',
                                        font: { size: 14 }
                                    },
                                    legend: {
                                        labels: { color: '#2d3748' }
                                    }
                                }
                            }
                        });
                    }
                } catch (error) {
                    voteAnalytics.innerHTML = `<p class="error">Failed to load analytics: ${error.message}</p>`;
                    console.error('Analytics error:', error);
                }
            });
        });
    </script>
</body>
</html>
<?php $pdo = null; ?>