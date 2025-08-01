<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Unable to connect to database. Please try again later.");
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    error_log("Initial session validation failed: user_id or role not set. Session: " . print_r($_SESSION, true));
    header('Location: login.php?error=' . urlencode('Session validation failed. Please log in again.'));
    exit;
}

if ($_SESSION['role'] !== 'voter') {
    error_log("Role mismatch: expected voter, got " . ($_SESSION['role'] ?? 'unset'));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: 2fa.php');
    exit;
}

if (!isset($_SESSION['wallet_address']) || empty($_SESSION['wallet_address']) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $_SESSION['wallet_address'])) {
    error_log("Invalid or unset wallet address in session for user_id: " . ($_SESSION['user_id'] ?? 'unset'));
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
    header('Location: post-login.php?role=' . urlencode($_SESSION['role']) . '&college_id=' . urlencode($_SESSION['college_id'] ?? '') . '&association=' . urlencode($_SESSION['association'] ?? '') . '&csrf_token=' . urlencode($_SESSION['csrf_token']));
    exit;
}

try {
    $stmt = $conn->prepare("SELECT wallet_address FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $db_user = $result->fetch_assoc();
    $stmt->close();

    if (!$db_user || strtolower($db_user['wallet_address']) !== strtolower($_SESSION['wallet_address'])) {
        error_log("Wallet address mismatch for user_id: " . ($_SESSION['user_id'] ?? 'unset') .
            ". Session wallet: " . ($_SESSION['wallet_address'] ?? 'unset') .
            ", DB wallet: " . ($db_user['wallet_address'] ?? 'unset'));
        session_unset();
        session_destroy();
        header('Location: login.php?error=' . urlencode('Wallet address mismatch. Please log in again.'));
        exit;
    }
} catch (Exception $e) {
    error_log("Wallet validation error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Error validating wallet address. Please log in again.'));
    exit;
}

error_log("Session after validation: user_id=" . ($_SESSION['user_id'] ?? 'unset') .
    ", role=" . ($_SESSION['role'] ?? 'unset') .
    ", college_id=" . ($_SESSION['college_id'] ?? 'unset') .
    ", association=" . ($_SESSION['association'] ?? 'unset'));

if (isset($_SESSION['college_id']) && $_SESSION['college_id'] != 1) {
    error_log("College ID mismatch: expected 1, got " . $_SESSION['college_id']);
    header('Location: login.php?error=' . urlencode('Invalid college for this dashboard.'));
    exit;
}

if (isset($_SESSION['association']) && $_SESSION['association'] !== 'UDOSO') {
    error_log("Association mismatch: expected UDOSO, got " . $_SESSION['association']);
    header('Location: login.php?error=' . urlencode('Invalid association for this dashboard.'));
    exit;
}

if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch detected: Session UA: " . $_SESSION['user_agent'] . ", Current UA: " . $_SERVER['HTTP_USER_AGENT']);
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    error_log("User agent updated in session.");
}

$inactivity_timeout = 30 * 60; // 30 minutes
$max_session_duration = 1 * 60 * 60; // 1 hour
$warning_time = 28;

if (!isset($_SESSION['start_time'])) {
    $_SESSION['start_time'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

$time_elapsed = time() - $_SESSION['start_time'];
if ($time_elapsed >= $max_session_duration) {
    error_log("Session expired due to maximum duration: $time_elapsed seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired. Please log in again.'));
    exit;
}

$inactive_time = time() - $_SESSION['last_activity'];
if ($inactive_time >= $inactivity_timeout) {
    error_log("Session expired due to inactivity: $inactive_time seconds elapsed.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session expired due to inactivity. Please log in again.'));
    exit;
}

$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];
$user = [];
try {
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No user found for user_id: " . $user_id);
    }
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('User not found or server error. Please log in again.'));
    exit;
}

function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCsrfToken();

$profile_picture = 'Uploads/passports/general.png';
$errors = [];
$elections = [];

try {
    $stmt = $conn->prepare(
        "SELECT u.association, u.college_id, u.hostel_id, c.name AS college_name
         FROM users u
         LEFT JOIN colleges c ON u.college_id = c.college_id
         WHERE u.user_id = ? AND u.active = 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_details = $result->fetch_assoc();
    $stmt->close();

    if (!$user_details) {
        $errors[] = "User not found or not active.";
    } else {
        $association = $user_details['association'];
        $college_id = $user_details['college_id'];
        $hostel_id = $user_details['hostel_id'] ?: 0;

        $stmt = $conn->prepare(
            "SELECT election_id, title
             FROM elections
             WHERE status = ? AND end_time > NOW() AND association = ?
             ORDER BY start_time ASC"
        );
        $status = 'ongoing';
        $stmt->bind_param('ss', $status, $association);
        $stmt->execute();
        $result = $stmt->get_result();
        $elections = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($elections as &$election) {
            $election_id = $election['election_id'];
            $positions = [];

            $query = "
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id, ep.hostel_id, ep.is_vice
                FROM electionpositions ep
                WHERE ep.election_id = ?
                AND (
                    ep.scope = 'university'
                    OR (ep.scope = 'college' AND ep.college_id = ?)
                ";
            if ($association === 'UDOSO' && $hostel_id) {
                $query .= " OR (ep.scope = 'hostel' AND ep.hostel_id = ?)";
            }
            $query .= ") ORDER BY ep.position_id";

            $stmt = $conn->prepare($query);
            if ($association === 'UDOSO' && $hostel_id) {
                $stmt->bind_param('iii', $election_id, $college_id, $hostel_id);
            } else {
                $stmt->bind_param('ii', $election_id, $college_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($position = $result->fetch_assoc()) {
                $position_id = $position['position_id'];
                $scope = $position['scope'];
                $is_vice = $position['is_vice'];

                $candidates = [];

                if ($scope === 'hostel') {
                    $cand_stmt = $conn->prepare(
                        "SELECT c.id, c.official_id, c.firstname, c.lastname, c.passport, c.pair_id, c.position_id, ep.is_vice
                         FROM candidates c
                         JOIN electionpositions ep ON c.position_id = ep.position_id
                         WHERE c.election_id = ? AND c.position_id = ? AND c.pair_id IS NULL"
                    );
                    $cand_stmt->bind_param('ii', $election_id, $position_id);
                    $cand_stmt->execute();
                    $cand_result = $cand_stmt->get_result();
                    while ($row = $cand_result->fetch_assoc()) {
                        $candidates[$row['id']] = [$row];
                    }
                    $cand_stmt->close();
                } else {
                    if ($is_vice == 0) {
                        $vice_position_id = null;
                        $vice_position_name = '';
                        $vice_stmt = $conn->prepare(
                            "SELECT position_id, name
                             FROM electionpositions
                             WHERE election_id = ? AND is_vice = 1
                             AND (
                                 (scope = 'university' AND scope = ?)
                                 OR (scope = 'college' AND scope = ? AND college_id = ?)
                             )"
                        );
                        $vice_stmt->bind_param('issi', $election_id, $scope, $scope, $position['position_college_id']);
                        $vice_stmt->execute();
                        $vice_result = $vice_stmt->get_result();
                        if ($vice_row = $vice_result->fetch_assoc()) {
                            $vice_position_id = $vice_row['position_id'];
                            $vice_position_name = $vice_row['name'];
                        }
                        $vice_stmt->close();

                        if ($vice_position_id) {
                            $cand_stmt = $conn->prepare(
                                "SELECT c.id, c.official_id, c.firstname, c.lastname, c.passport, c.pair_id, c.position_id, ep.is_vice
                                 FROM candidates c
                                 JOIN electionpositions ep ON c.position_id = ep.position_id
                                 WHERE c.election_id = ? AND c.position_id IN (?, ?)
                                 AND c.pair_id IS NOT NULL
                                 ORDER BY c.pair_id, ep.is_vice ASC"
                            );
                            $cand_stmt->bind_param('iii', $election_id, $position_id, $vice_position_id);
                            $cand_stmt->execute();
                            $cand_result = $cand_stmt->get_result();
                            while ($row = $cand_result->fetch_assoc()) {
                                $pair_id = $row['pair_id'];
                                if (!isset($candidates[$pair_id])) {
                                    $candidates[$pair_id] = [];
                                }
                                $candidates[$pair_id][] = $row;
                            }
                            $cand_stmt->close();
                        }

                        $position['vice_position_name'] = $vice_position_name;
                    } else {
                        continue;
                    }
                }

                $position['candidates'] = $candidates;
                $positions[] = $position;
            }
            $stmt->close();

            $election['positions'] = $positions;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections due to a server error.";
}

function getUserInitials($name) {
    $parts = explode(" ", trim($name));
    $initials = "";
    foreach ($parts as $part) {
        if (trim($part) !== "") {
            $initials .= strtoupper(substr(trim($part), 0, 1));
        }
    }
    return $initials ?: "U";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIVE Students Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
</head>
<body>
    <header>
        <div class="logo">CIVE Students</div>
        <nav class="nav">
            <a href="process-vote.php" id="cast-vote-link">Cast Vote</a>
            <a href="#" id="verify-vote-link">Verify Vote</a>
            <a href="#" id="results-link">Results</a>
            <a href="#" id="analytics-link">Analytics</a>
        </nav>
        <div class="user-profile" id="profile-pic">
            <?php echo htmlspecialchars(getUserInitials($_SESSION['name'] ?? 'Unknown User')); ?>
            <div class="user-dropdown" id="user-dropdown">
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </header>
    <div class="dash-content">
        <section id="my-votes">
            <h3>The Votes Summary</h3>
            <p>Loading...</p>
        </section>
        <section id="analytics" style="display: none;">
            <h3>Analytics</h3>
            <div id="results-table"></div>
            <div id="deep-analytics"></div>
        </section>
    </div>

    <div id="timeout-modal" class="modal">
        <div class="modal-content">
            <p id="timeout-message">Your session will expire soon due to inactivity.</p>
            <button id="extend-session">Extend Session</button>
        </div>
    </div>

    <div id="verify-modal" class="modal">
        <div class="modal-content">
            <h3>Verify Your Vote</h3>
            <input type="number" id="verify-election-id" placeholder="Election ID">
            <input type="number" id="verify-position-id" placeholder="Position ID">
            <input type="text" id="verify-candidate-id" placeholder="Candidate ID">
            <button onclick="verifyVote()">Verify</button>
            <button onclick="closeVerifyModal()">Close</button>
            <div id="verify-result"></div>
        </div>
    </div>

    <div id="results-modal" class="modal">
        <div class="modal-content">
            <div id="results-content"></div>
        </div>
    </div>

    <script>
        const inactivityTimeout = <?php echo $inactivity_timeout; ?>;
        const warningTime = <?php echo $warning_time; ?>;
        const isDevMode = <?php echo json_encode(getenv('DEV_MODE') === 'true' || isset($_GET['dev_mode'])); ?>;
        let inactivityTimer;
        let warningTimer;
        const timeoutModal = document.getElementById('timeout-modal');
        const timeoutMessage = document.getElementById('timeout-message');
        const extendSessionButton = document.getElementById('extend-session');
        const verifyVoteLink = document.getElementById('verify-vote-link');
        const verifyModal = document.getElementById('verify-modal');
        const myVotesSection = document.getElementById('my-votes');
        const analyticsSection = document.getElementById('analytics');
        const resultsTable = document.getElementById('results-table');
        const deepAnalyticsSection = document.getElementById('deep-analytics');
        const castVoteLink = document.getElementById('cast-vote-link');
        const resultsLink = document.getElementById('results-link');
        const resultsModal = document.getElementById('results-modal');
        const resultsContent = document.getElementById('results-content');

        const contractAddress = '0x9875E209Eaa7c66B6117272cd87869c709Cd2A4c';
        const abi = [{
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
                        "internalType": "uint256",
                        "name": "",
                        "type": "uint256"
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
            }
        ];
        const alchemyApiKey = 'q_DqVYxr5iR_uqer0W3xZ';
        let provider = new Web3.providers.WebsocketProvider(`wss://eth-sepolia.g.alchemy.com/v2/${alchemyApiKey}`);
        let web3 = new Web3(provider);
        let contract = new web3.eth.Contract(abi, contractAddress);

        async function getAndValidateWalletAddress() {
            try {
                if (typeof window.ethereum === 'undefined') {
                    console.error('MetaMask is not installed.');
                    alert('Please install MetaMask to use this voting platform.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask not detected.');
                    return null;
                }
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (accounts.length === 0) {
                    console.error('No MetaMask accounts available.');
                    alert('Please connect an account in MetaMask.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('No MetaMask account connected.');
                    return null;
                }
                const currentAddress = accounts[0];
                const sessionAddress = '<?php echo htmlspecialchars($_SESSION['wallet_address'] ?? '0x0'); ?>';
                console.log('Current MetaMask Wallet Address:', currentAddress);
                console.log('Session Wallet Address:', sessionAddress);
                if (isDevMode) {
                    console.warn('Development Mode: Skipping wallet address validation.');
                    return currentAddress;
                }
                if (currentAddress.toLowerCase() !== sessionAddress.toLowerCase()) {
                    await updateWalletAddress(currentAddress);
                    location.reload();
                }
                return currentAddress;
            } catch (error) {
                console.error('Error accessing MetaMask wallet:', error.code, error.message);
                alert('Failed to connect to MetaMask: ' + error.message);
                window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask connection failed.');
                return null;
            }
        }

        if (window.ethereum) {
            window.ethereum.on('accountsChanged', async (accounts) => {
                if (accounts.length === 0) {
                    console.error('MetaMask disconnected.');
                    alert('MetaMask has been disconnected. Please reconnect to continue.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask disconnected.');
                    return;
                }
                const newAddress = accounts[0];
                console.log('MetaMask account changed to:', newAddress);
                if (isDevMode) {
                    console.warn('Development Mode: Auto-updating session wallet address.');
                    await updateWalletAddress(newAddress);
                    return;
                }
                const sessionAddress = '<?php echo htmlspecialchars($_SESSION['wallet_address'] ?? '0x0'); ?>';
                if (newAddress.toLowerCase() !== sessionAddress.toLowerCase()) {
                    const confirmUpdate = confirm(`Your MetaMask account has changed to ${newAddress}. Would you like to update your session to use this account? Selecting "Cancel" will log you out.`);
                    if (confirmUpdate) {
                        await updateWalletAddress(newAddress);
                    } else {
                        window.location.href = 'login.php?error=' + encodeURIComponent('Wallet account changed. Please log in again.');
                    }
                }
            });
            window.ethereum.on('disconnect', () => {
                console.error('MetaMask provider disconnected.');
                alert('MetaMask has been disconnected. Please reconnect to continue.');
                window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask disconnected.');
            });
        }

        async function updateWalletAddress(newAddress) {
            try {
                const response = await fetch('update-wallet.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `wallet_address=${encodeURIComponent(newAddress)}&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>`
                });
                const result = await response.json();
                if (result.success) {
                    console.log('Session wallet address updated to:', newAddress);
                    alert('Wallet address updated successfully. The page will now reload.');
                    location.reload();
                } else {
                    console.error('Failed to update wallet address:', result.error);
                    alert('Failed to update wallet address: ' + result.error);
                    window.location.href = 'login.php?error=' + encodeURIComponent('Wallet update failed.');
                }
            } catch (error) {
                console.error('Error updating wallet address:', error.code, error.message);
                alert('Error updating wallet address: ' + error.message);
                window.location.href = 'login.php?error=' + encodeURIComponent('Wallet update error.');
            }
        }

        async function loadMyVotes() {
            const voterAddress = await getAndValidateWalletAddress();
            if (!voterAddress) {
                myVotesSection.innerHTML = '<p class="error">Unable to load votes: Wallet validation failed.</p>';
                return;
            }
            try {
                const association = '<?php echo htmlspecialchars($_SESSION['association'] ?? ''); ?>';
                let electionId = 1;
                if (association === 'UDOMASA') electionId = 2;
                const allVotes = await contract.methods.getVotesByElection(electionId).call();
                console.log('Votes returned:', allVotes);
                let myVotesHtml = '';
                let hasVotes = false;
                for (let vote of allVotes) {
                    if (web3.utils.toChecksumAddress(vote.voter) === web3.utils.toChecksumAddress(voterAddress)) {
                        myVotesHtml += `<div class="vote-item">
                            <p>Election ID: ${vote.electionId}</p>
                            <p>Position: ${vote.positionName}</p>
                            <p>Candidate: ${vote.candidateName}</p>
                            <p>Time: ${new Date(vote.timestamp * 1000).toLocaleString()}</p>
                        </div>`;
                        hasVotes = true;
                    }
                }
                if (!hasVotes) {
                    myVotesHtml = `<p>No votes cast by you.</p>`;
                }
                myVotesSection.innerHTML = '<h3>The Votes Summary</h3>' + myVotesHtml;
            } catch (error) {
                console.error('Error loading votes:', error.code, error.message);
                myVotesSection.innerHTML = '<p class="error">Error loading votes: ' + (error.message || 'Unknown error') + '. Please try again later.</p>';
            }
        }

        async function loadAnalytics() {
            const voterAddress = await getAndValidateWalletAddress();
            if (!voterAddress) {
                analyticsSection.innerHTML = '<p class="error">Unable to load analytics: Wallet validation failed.</p>';
                return;
            }
            try {
                const association = '<?php echo htmlspecialchars($_SESSION['association'] ?? ''); ?>';
                let electionId = 1;
                if (association === 'UDOMASA') electionId = 2;
                const allVotes = await contract.methods.getVotesByElection(electionId).call();
                if (!allVotes || allVotes.length === 0) {
                    analyticsSection.innerHTML = '<p class="error">No votes found for this election.</p>';
                    return;
                }

                // Results Table
                const voteCounts = {};
                allVotes.forEach(vote => {
                    const candidateId = vote.candidateId;
                    voteCounts[candidateId] = voteCounts[candidateId] || {
                        count: 0,
                        name: vote.candidateName,
                        position: vote.positionName
                    };
                    voteCounts[candidateId].count += 1;
                });
                let tableHtml = `
                    <h4>Vote Results</h4>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Candidate ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Votes</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                for (let candidateId in voteCounts) {
                    tableHtml += `
                        <tr>
                            <td>${candidateId}</td>
                            <td>${voteCounts[candidateId].name}</td>
                            <td>${voteCounts[candidateId].position}</td>
                            <td>${voteCounts[candidateId].count}</td>
                        </tr>
                    `;
                }
                tableHtml += '</tbody></table>';
                resultsTable.innerHTML = tableHtml;

                // Deep Analytics (Heatmap and Scatter)
                const voteHeatmap = {};
                allVotes.forEach(vote => {
                    const hour = new Date(vote.timestamp * 1000).getHours();
                    const positionId = vote.positionId.toString();
                    if (!voteHeatmap[positionId]) voteHeatmap[positionId] = {};
                    if (!voteHeatmap[positionId][hour]) voteHeatmap[positionId][hour] = 0;
                    voteHeatmap[positionId][hour]++;
                });
                const geoData = allVotes.map(vote => ({
                    lat: Math.random() * (6.8 - 6.6) + 6.6,
                    lng: Math.random() * (39.3 - 39.0) + 39.0,
                    votes: 1
                }));
                let deepHtml = '';
                for (const [positionId, hours] of Object.entries(voteHeatmap)) {
                    deepHtml += `
                        <div class="analytics-chart">
                            <h4>Position ID: ${positionId}</h4>
                            <canvas id="heatmap-${positionId}" style="max-width: 600px; height: 300px;"></canvas>
                            <canvas id="scatter-${positionId}" style="max-width: 600px; height: 300px;"></canvas>
                        </div>
                    `;
                }
                deepAnalyticsSection.innerHTML = deepHtml;

                for (const [positionId, hours] of Object.entries(voteHeatmap)) {
                    const heatmapCtx = document.getElementById(`heatmap-${positionId}`).getContext('2d');
                    new Chart(heatmapCtx, {
                        type: 'scatter',
                        data: { datasets: [{ label: 'Voting Activity Heatmap', data: Object.keys(hours).map(hour => ({ x: parseInt(hour), y: hours[hour] })), backgroundColor: 'rgba(244, 162, 97, 0.6)', pointRadius: 10, pointHoverRadius: 15 }] },
                        options: { scales: { x: { title: { display: true, text: 'Hour of Day' }, min: 0, max: 23 }, y: { title: { display: true, text: 'Number of Votes' }, beginAtZero: true } }, plugins: { title: { display: true, text: `Voting Heatmap for Position ${positionId}`, color: '#2d3748', font: { size: 14 } } } }
                    });
                    const scatterCtx = document.getElementById(`scatter-${positionId}`).getContext('2d');
                    new Chart(scatterCtx, {
                        type: 'scatter',
                        data: { datasets: [{ label: 'Geospatial Vote Distribution', data: geoData, backgroundColor: 'rgba(46, 157, 143, 0.6)', pointRadius: 5 }] },
                        options: { scales: { x: { title: { display: true, text: 'Longitude' }, min: 39.0, max: 39.3 }, y: { title: { display: true, text: 'Latitude' }, min: 6.6, max: 6.8 } }, plugins: { title: { display: true, text: `Geospatial Distribution for Position ${positionId}`, color: '#2d3748', font: { size: 14 } } } }
                    });
                }
            } catch (error) {
                console.error('Error loading analytics:', error.code, error.message);
                analyticsSection.innerHTML = '<p class="error">Error loading analytics: ' + (error.message || 'Unknown error') + '</p>';
            }
        }

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(warningTimer);
            timeoutModal.style.display = 'none';
            warningTimer = setTimeout(() => {
                timeoutMessage.textContent = 'You will be logged out in 1 minute due to inactivity.';
                timeoutModal.style.display = 'flex';
            }, (inactivityTimeout - warningTime) * 1000);
            inactivityTimer = setTimeout(() => {
                window.location.href = 'login.php?error=' + encodeURIComponent('Session expired due to inactivity.');
            }, inactivityTimeout * 1000);
        }

        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        document.addEventListener('click', resetInactivityTimer);
        document.addEventListener('scroll', resetInactivityTimer);
        extendSessionButton.addEventListener('click', resetInactivityTimer);

        window.addEventListener('load', async () => {
            await loadMyVotes();
            resetInactivityTimer();
        });

        verifyVoteLink.addEventListener('click', (e) => { e.preventDefault(); verifyModal.style.display = 'flex'; });
        resultsLink.addEventListener('click', (e) => {
            e.preventDefault();
            resultsModal.style.display = 'flex';
            resultsContent.innerHTML = `
                <h3>Election Results</h3>
                <div style="margin-bottom: 20px;">
                    <input type="number" id="election-id-input" placeholder="Search Election ID...">
                    <button id="fetch-results-btn">Fetch Results</button>
                </div>
                <div id="results-display"></div>
            `;
            document.getElementById('fetch-results-btn').addEventListener('click', displayResults);
        });
        castVoteLink.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'process-vote.php'; });
        document.getElementById('analytics-link').addEventListener('click', (e) => {
            e.preventDefault();
            analyticsSection.style.display = analyticsSection.style.display === 'block' ? 'none' : 'block';
            if (analyticsSection.style.display === 'block') {
                loadAnalytics();
            }
        });

        async function verifyVote() {
            const electionId = document.getElementById('verify-election-id').value;
            const positionId = document.getElementById('verify-position-id').value;
            const candidateId = document.getElementById('verify-candidate-id').value;
            const resultDiv = document.getElementById('verify-result');
            if (!electionId || !positionId || !candidateId) {
                resultDiv.innerHTML = '<p class="error">Please fill in all fields.</p>';
                return;
            }
            try {
                const voterAddress = await getAndValidateWalletAddress();
                if (!voterAddress) {
                    resultDiv.innerHTML = '<p class="error">Wallet validation failed.</p>';
                    return;
                }
                const allVotes = await contract.methods.getVotesByElection(electionId).call();
                let voteFound = false;
                for (let vote of allVotes) {
                    if (web3.utils.toChecksumAddress(vote.voter) === web3.utils.toChecksumAddress(voterAddress) && vote.positionId === positionId && vote.candidateId === candidateId) {
                        resultDiv.innerHTML = `<p class="success">Vote verified! You voted for Candidate ID ${candidateId} for ${vote.positionName} in Election ID ${electionId} at ${new Date(vote.timestamp * 1000).toLocaleString()}</p>`;
                        voteFound = true;
                        break;
                    }
                }
                if (!voteFound) {
                    resultDiv.innerHTML = '<p class="error">No matching vote found for the provided details.</p>';
                }
            } catch (error) {
                console.error('Error verifying vote:', error.code, error.message);
                resultDiv.innerHTML = '<p class="error">Error verifying vote: ' + (error.message || 'Unknown error') + '</p>';
            }
        }

        function closeVerifyModal() {
            verifyModal.style.display = 'none';
            document.getElementById('verify-election-id').value = '';
            document.getElementById('verify-position-id').value = '';
            document.getElementById('verify-candidate-id').value = '';
            document.getElementById('verify-result').innerHTML = '';
        }

        async function displayResults() {
            const electionIdInput = document.getElementById('election-id-input');
            const electionId = electionIdInput.value.trim();
            const resultsDisplay = document.getElementById('results-display');
            if (!electionId) {
                resultsDisplay.innerHTML = '<p class="error">Please enter a valid Election ID.</p>';
                return;
            }
            try {
                const voterAddress = await getAndValidateWalletAddress();
                if (!voterAddress) {
                    resultsDisplay.innerHTML = '<p class="error">Unable to fetch results: Wallet validation failed.</p>';
                    return;
                }
                const allVotes = await contract.methods.getVotesByElection(electionId).call({ from: voterAddress });
                let resultsHtml = `
                    <h4>Results for Election ID: ${electionId}</h4>
                    <div class="candidate-grid">
                        <div class="candidate-card">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #1a3c34; color: #fff;">
                                        <th style="padding: 10px; text-align: left;">Candidate ID</th>
                                        <th style="padding: 10px; text-align: left;">Name</th>
                                        <th style="padding: 10px; text-align: left;">Position</th>
                                        <th style="padding: 10px; text-align: left;">Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                const voteCounts = {};
                allVotes.forEach(vote => {
                    const candidateId = vote.candidateId;
                    voteCounts[candidateId] = voteCounts[candidateId] || { count: 0, name: vote.candidateName, position: vote.positionName };
                    voteCounts[candidateId].count += 1;
                });
                for (let candidateId in voteCounts) {
                    resultsHtml += `
                        <tr style="border-bottom: 1px solid #e0e0e0;">
                            <td style="padding: 10px;">${candidateId}</td>
                            <td style="padding: 10px;">${voteCounts[candidateId].name}</td>
                            <td style="padding: 10px;">${voteCounts[candidateId].position}</td>
                            <td style="padding: 10px;">${voteCounts[candidateId].count} vote(s)</td>
                        </tr>
                    `;
                }
                if (Object.keys(voteCounts).length === 0) {
                    resultsHtml += `
                        <tr>
                            <td colspan="4" style="padding: 10px; text-align: center; color: #e76f51;">No votes recorded yet.</td>
                        </tr>
                    `;
                }
                resultsHtml += `
                                </tbody>
                            </table>
                            <button onclick="closeResultsModal()" style="margin-top: 20px; padding: 10px 20px; background: #e76f51; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background 0.3s;">Close</button>
                        </div>
                    </div>
                `;
                resultsDisplay.innerHTML = resultsHtml;
            } catch (error) {
                console.error('Error fetching results:', error.code, error.message);
                resultsDisplay.innerHTML = '<p class="error">Error fetching results: ' + (error.message || 'Unknown error') + '</p>';
            }
        }

        function closeResultsModal() {
            resultsModal.style.display = 'none';
        }

        const profilePic = document.getElementById('profile-pic');
        const userDropdown = document.getElementById('user-dropdown');
        profilePic.addEventListener('click', (e) => { e.preventDefault(); userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block'; });
        document.addEventListener('click', (event) => {
            if (!profilePic.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>