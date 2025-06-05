<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

// CSRF Token Validation
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed.");
    header('Location: login.php?error=' . urlencode('Invalid CSRF token.'));
    exit;
}

require_once 'config.php';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}

// Session Validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    error_log("Session validation failed: user_id or role not set or invalid.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit;
}

if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: 2fa.php');
    exit;
}

if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    error_log("User agent mismatch; possible session hijacking attempt.");
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed.'));
    exit;
}

$user_id = $_SESSION['user_id'];
$user = [];
try {
    $stmt = $conn->prepare("SELECT fname, college_id, hostel_id, association, active FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user || $user['active'] == 0) {
        throw new Exception("No user found or user is blocked for user_id: " . $user_id);
    }
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('User not found, blocked, or server error. Please log in again.'));
    exit;
}

$profile_picture = 'uploads/passports/general.png';

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = generateCsrfToken();

$dashboard_file = 'login.php'; // Simplified default
if ($user['association'] === 'UDOSO') {
    if ($user['college_id'] == 1) $dashboard_file = 'cive-students.php';
    elseif ($user['college_id'] == 2) $dashboard_file = 'coed-students.php';
    elseif ($user['college_id'] == 3) $dashboard_file = 'cnms-students.php';
} elseif ($user['association'] === 'UDOMASA') {
    if ($user['college_id'] == 1) $dashboard_file = 'cive-teachers.php';
    elseif ($user['college_id'] == 2) $dashboard_file = 'coed-teachers.php';
    elseif ($user['college_id'] == 3) $dashboard_file = 'cnms-teachers.php';
}

$errors = [];
$elections = [];
try {
    $stmt = $conn->prepare(
        "SELECT election_id, title
         FROM elections
         WHERE status = ? AND end_time > NOW() AND association = ?
         ORDER BY start_time ASC"
    );
    $status = 'ongoing';
    $stmt->bind_param('ss', $status, $user['association']);
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
        if ($user['association'] === 'UDOSO' && $user['hostel_id']) {
            $query .= " OR (ep.scope = 'hostel' AND ep.hostel_id = ?)";
        }
        $query .= ") ORDER BY ep.position_id";

        $stmt = $conn->prepare($query);
        if ($user['association'] === 'UDOSO' && $user['hostel_id']) {
            $stmt->bind_param('iii', $election_id, $user['college_id'], $user['hostel_id']);
        } else {
            $stmt->bind_param('ii', $election_id, $user['college_id']);
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
} catch (Exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections due to a server error.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Vote | SmartUchaguzi</title>
    <link rel="icon" href="./images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Keep the existing CSS styles as they are for UI consistency] */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg'); background-size: cover; color: #2d3748; min-height: 100vh; }
        .dropdown { display: none; position: absolute; top: 60px; right: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .dropdown a, .dropdown span { display: block; padding: 10px 20px; color: #2d3748; text-decoration: none; font-size: 16px; }
        .dropdown a:hover { background: #007bff; color: #fff; }
        .dashboard { margin-top: 80px; padding: 30px; display: flex; justify-content: center; }
        .dash-content { background: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 12px; width: 100%; max-width: 1200px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15); }
        .dash-content h2 { font-size: 28px; color: #1a3c34; margin-bottom: 20px; text-align: center; }
        .election-section { margin-bottom: 30px; }
        .election-section h3 { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .position-section { margin-bottom: 20px; }
        .position-section h4 { font-size: 18px; color: #2d3748; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .candidate-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 25px; }
        .candidate-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 25px; display: flex; align-items: center; justify-content: space-between; transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; overflow: hidden; }
        .candidate-card:hover { transform: translateY(-5px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); }
        .candidate-card.selected { border: 2px solid #007bff; background: #e6f0fa; }
        .candidate-img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid #007bff; margin-right: 20px; transition: border-color 0.3s ease; }
        .candidate-card:hover .candidate-img { border-color: #0056b3; }
        .candidate-details { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .candidate-details h5 { font-size: 18px; font-weight: 600; color: #2d3748; margin: 0; }
        .candidate-details p { font-size: 14px; color: #666; margin: 0; }
        .vote-label { display: flex; align-items: center; cursor: pointer; }
        .vote-checkbox { width: 24px; height: 24px; margin-right: 12px; accent-color: #007bff; }
        .vote-form button { background: #007bff; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; transition: background 0.3s ease; margin-top: 20px; display: block; margin-left: auto; margin-right: auto; }
        .vote-form button:hover { background: #0056b3; }
        .vote-form button:disabled { background: #cccccc; cursor: not-allowed; }
        .error, .success { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 16px; }
        .error { background: #ffe6e6; color: #e76f51; border: 1px solid #e76f51; }
        .success { background: #e6fff5; color: #2a9d8f; border: 1px solid #2a9d8f; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1001; justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 20px; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; }
        .modal-content p { font-size: 16px; color: #2d3748; margin-bottom: 20px; }
        .modal-content button { background: #007bff; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 16px; margin: 0 10px; }
        .modal-content button:hover { background: #0056b3; }
        @media (max-width: 768px) { .dropdown { display: block; position: static; box-shadow: none; background: none; text-align: center; } .dropdown a, .dropdown span { color: #e6e6e6; padding: 5px 10px; } .dropdown a:hover { background: none; color: #007bff; } .dash-content { padding: 20px; } .dash-content h2 { font-size: 24px; } .election-section h3 { font-size: 18px; } .position-section h4 { font-size: 16px; } .candidate-grid { grid-template-columns: 1fr; } .candidate-card { flex-direction: column; align-items: flex-start; padding: 15px; } .candidate-img { width: 80px; height: 80px; margin-bottom: 10px; margin-right: 0; } .candidate-details h5 { font-size: 14px; } .candidate-details p { font-size: 12px; } .vote-form button { font-size: 14px; padding: 10px 20px; } }
        @media (min-width: 600px) { .candidate-card { flex-direction: row; align-items: center; } .candidate-img { margin-bottom: 0; } }
        .back-arrow { display: inline-block; width: 60px; height: 60px; background: #1a3c34; border-radius: 50%; text-align: center; line-height: 60px; color: #fff; text-decoration: none; font-size: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease; }
        .back-arrow:hover { transform: scale(1.1); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3); background: #007bff; }
        #profile-pic { cursor: pointer; transition: transform 0.3s ease; }
        #profile-pic:hover { transform: scale(1.05); }
    </style>
</head>

<body>
    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <a href="<?php echo htmlspecialchars($dashboard_file); ?>" class="back-arrow">←</a>
    </div>
    <div style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Profile Picture" id="profile-pic" style="width: 40px; height: 40px; border-radius: 50%; cursor: pointer;">
        <div class="dropdown" id="user-dropdown">
            <span style="color: #2d3748; padding: 10px 20px;"><?php echo htmlspecialchars($user['fname'] ?? 'User'); ?></span>
            <a href="#">My Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <section class="dashboard">
        <div class="dash-content">
            <h2>Cast Your Vote</h2>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($elections)): ?>
                <div class="error">
                    <p>No ongoing elections available at this time.</p>
                </div>
            <?php else: ?>
                <div id="success-message" class="success" style="display: none;"></div>
                <div id="error-message" class="error" style="display: none;"></div>
                <?php foreach ($elections as $election): ?>
                    <div class="election-section">
                        <h3>Election: <?php echo htmlspecialchars($election['title']); ?></h3>
                        <?php if (empty($election['positions'])): ?>
                            <div class="error">
                                <p>No positions available for you to vote in this election.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($election['positions'] as $position): ?>
                                <div class="position-section">
                                    <h4>Position: <?php echo htmlspecialchars($position['position_name']); ?></h4>
                                    <?php if (empty($position['candidates'])): ?>
                                        <div class="error">
                                            <p>No candidates available for this position.</p>
                                        </div>
                                    <?php else: ?>
                                        <form class="vote-form" data-election-id="<?php echo $election['election_id']; ?>" data-position-id="<?php echo $position['position_id']; ?>" aria-label="Vote for <?php echo htmlspecialchars($position['position_name']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <div class="candidate-grid">
                                                <?php
                                                foreach ($position['candidates'] as $key => $candidateGroup) {
                                                    if ($position['scope'] !== 'hostel' && count($candidateGroup) == 2) {
                                                        $mainCandidate = $candidateGroup[0]['is_vice'] == 0 ? $candidateGroup[0] : $candidateGroup[1];
                                                        $viceCandidate = $candidateGroup[0]['is_vice'] == 1 ? $candidateGroup[0] : $candidateGroup[1];
                                                        $pair_id = $mainCandidate['pair_id'];
                                                ?>
                                                        <div class="candidate-card" data-candidate-id="<?php echo $mainCandidate['id']; ?>">
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($mainCandidate['passport'] ?: 'images/general.png'); ?>" alt="Candidate <?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname']); ?></h5>
                                                                    <p>Reg No: <?php echo htmlspecialchars($mainCandidate['official_id']); ?></p>
                                                                    <p>Programme: <?php echo htmlspecialchars($user['association']); ?></p>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($viceCandidate['passport'] ?: 'images/general.png'); ?>" alt="Running Mate <?php echo htmlspecialchars($viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?></h5>
                                                                    <p>Reg No: <?php echo htmlspecialchars($viceCandidate['official_id']); ?></p>
                                                                    <p>Post: <?php echo htmlspecialchars($position['vice_position_name']); ?></p>
                                                                </div>
                                                            </div>
                                                            <label class="vote-label">
                                                                <input type="radio" name="candidate_id" value="<?php echo $pair_id; ?>" id="candidate_<?php echo $mainCandidate['id']; ?>" required aria-label="Vote for <?php echo htmlspecialchars($mainCandidate['firstname'] . ' ' . $mainCandidate['lastname'] . ' and ' . $viceCandidate['firstname'] . ' ' . $viceCandidate['lastname']); ?>">
                                                                <span class="vote-checkmark"></span>
                                                            </label>
                                                        </div>
                                                    <?php
                                                    } else {
                                                        $candidate = $candidateGroup[0];
                                                        $candidate_id = $candidate['id'];
                                                    ?>
                                                        <div class="candidate-card" data-candidate-id="<?php echo $candidate_id; ?>">
                                                            <div style="display: flex; align-items: center;">
                                                                <img src="<?php echo htmlspecialchars($candidate['passport'] ?: 'images/general.png'); ?>" alt="Candidate <?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?>" class="candidate-img">
                                                                <div class="candidate-details">
                                                                    <h5><?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?></h5>
                                                                    <p>Reg No: <?php echo htmlspecialchars($candidate['official_id']); ?></p>
                                                                    <p>Programme: <?php echo htmlspecialchars($user['association']); ?></p>
                                                                </div>
                                                            </div>
                                                            <label class="vote-label">
                                                                <input type="radio" name="candidate_id" value="<?php echo $candidate_id; ?>" id="candidate_<?php echo $candidate_id; ?>" required aria-label="Vote for <?php echo htmlspecialchars($candidate['firstname'] . ' ' . $candidate['lastname']); ?>">
                                                                <span class="vote-checkmark"></span>
                                                            </label>
                                                        </div>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <button type="submit">Cast Vote</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="modal" id="timeout-modal">
        <div class="modal-content">
            <p id="timeout-message">You will be logged out in 1 minute due to inactivity.</p>
            <button id="extend-session">OK</button>
        </div>
    </div>

    <div class="modal" id="confirm-vote-modal">
        <div class="modal-content">
            <p id="confirm-vote-message">Are you sure you want to vote for <span id="confirm-candidate-name"></span> for <span id="confirm-position-name"></span>?</p>
            <button id="confirm-vote">Confirm Vote</button>
            <button id="cancel-vote">Cancel</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>
    <script>
        // Check if MetaMask is available
        if (window.ethereum) {
            console.log('MetaMask detected');
        } else {
            console.error('MetaMask not detected. Please install and enable the MetaMask extension.');
            showError('MetaMask not detected. Please install and enable the extension.');
            throw new Error('MetaMask required');
        }

        const provider = new ethers.providers.Web3Provider(window.ethereum);
        const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';

        // Fetch the ABI from a separate file
        let contract;
        fetch('./js/contract-abi.json')
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch ABI');
                return response.json();
            })
            .then(abi => {
                contract = new ethers.Contract(contractAddress, abi, provider);
                console.log('Contract initialized');
            })
            .catch(error => {
                console.error('Error loading ABI:', error);
                showError('Unable to load voting contract. Please contact support.');
            });

        function showSuccess(message) {
            const successMessage = document.getElementById('success-message');
            successMessage.textContent = message;
            successMessage.style.display = 'block';
            setTimeout(() => successMessage.style.display = 'none', 5000);
        }

        function showError(message) {
            const errorMessage = document.getElementById('error-message');
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            setTimeout(() => errorMessage.style.display = 'none', 5000);
        }

        async function retryOperation(operation, maxAttempts = 3, delay = 1000, timeout = 5000) {
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), timeout);
                try {
                    return await operation();
                } catch (error) {
                    if (attempt === maxAttempts) {
                        throw error;
                    }
                    await new Promise(resolve => setTimeout(resolve, delay));
                } finally {
                    clearTimeout(timeoutId);
                }
            }
        }

        document.querySelectorAll('.vote-form').forEach(form => {
            const confirmModal = document.getElementById('confirm-vote-modal');
            const confirmMessage = document.getElementById('confirm-vote-message');
            const confirmCandidateName = document.getElementById('confirm-candidate-name');
            const confirmPositionName = document.getElementById('confirm-position-name');
            const confirmButton = document.getElementById('confirm-vote');
            const cancelButton = document.getElementById('cancel-vote');
            let formData = null;

            form.querySelectorAll('input[name="candidate_id"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    form.querySelectorAll('.candidate-card').forEach(card => card.classList.remove('selected'));
                    const card = radio.closest('.candidate-card');
                    if (card) card.classList.add('selected');
                });
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!contract) {
                    showError('Contract not initialized. Please refresh the page.');
                    return;
                }

                const electionId = form.getAttribute('data-election-id');
                const positionId = form.getAttribute('data-position-id');
                const candidateId = form.querySelector('input[name="candidate_id"]:checked')?.value;
                const candidateName = form.querySelector('input[name="candidate_id"]:checked')?.closest('.candidate-card')?.querySelector('.candidate-details h5').textContent;
                const positionName = form.querySelector('h4').textContent.replace('Position: ', '');

                if (!candidateId) {
                    showError('Please select a candidate to vote for.');
                    return;
                }

                confirmCandidateName.textContent = candidateName;
                confirmPositionName.textContent = positionName;
                confirmModal.style.display = 'flex';
                formData = { electionId, positionId, candidateId, candidateName, positionName };

                confirmButton.onclick = async () => {
                    confirmModal.style.display = 'none';
                    const submitButton = form.querySelector('button');
                    submitButton.disabled = true;
                    submitButton.textContent = 'Submitting Vote...';

                    try {
                        let signer;
                        try {
                            await provider.send('eth_requestAccounts', []);
                            const chainId = await provider.send('eth_chainId', []);
                            if (chainId !== '0x11155111') {
                                await provider.send('wallet_switchEthereumChain', [{ chainId: '0x11155111' }]);
                            }
                            signer = provider.getSigner();
                            const address = await signer.getAddress();
                            if (!address) throw new Error('Failed to get signer address.');
                        } catch (error) {
                            throw new Error('MetaMask connection error: ' + error.message);
                        }

                        const contractWithSigner = contract.connect(signer);
                        let tx;
                        try {
                            const gasEstimate = await contractWithSigner.estimateGas.castVote(
                                formData.electionId, formData.positionId, formData.candidateId,
                                formData.candidateName, formData.positionName
                            );
                            tx = await retryOperation(() => contractWithSigner.castVote(
                                formData.electionId, formData.positionId, formData.candidateId,
                                formData.candidateName, formData.positionName,
                                { gasLimit: gasEstimate.mul(2) }
                            ), 3, 1000, 5000);
                        } catch (error) {
                            throw new Error('Blockchain transaction failed: ' + error.message);
                        }
                        const receipt = await tx.wait();

                        showSuccess('Vote cast successfully! Transaction Hash: ' + receipt.transactionHash);
                        form.querySelectorAll('input[name="candidate_id"]').forEach(radio => radio.disabled = true);
                        submitButton.disabled = true;
                        submitButton.textContent = 'Vote Cast';
                    } catch (error) {
                        console.error('Vote submission error:', error);
                        if (error.code === 'NETWORK_ERROR') {
                            showError('Network error. Please check your connection.');
                        } else if (error.code === 'INSUFFICIENT_FUNDS') {
                            showError('Insufficient funds for gas fees.');
                        } else if (error.message.includes('user rejected')) {
                            showError('Transaction rejected in MetaMask.');
                        } else {
                            showError('Error: ' + error.message);
                        }
                        submitButton.disabled = false;
                        submitButton.textContent = 'Cast Vote';
                    }
                };

                cancelButton.onclick = () => {
                    confirmModal.style.display = 'none';
                    form.querySelectorAll('input[name="candidate_id"]').forEach(radio => radio.checked = false);
                    form.querySelectorAll('.candidate-card').forEach(card => card.classList.remove('selected'));
                };
            });

            const inactivityTimeout = 3 * 60 * 60;
            const warningTime = 60;
            let inactivityTimer;
            let warningTimer;
            const timeoutModal = document.getElementById('timeout-modal');
            const timeoutMessage = document.getElementById('timeout-message');
            const extendSessionButton = document.getElementById('extend-session');

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
            resetInactivityTimer();

            const profilePic = document.getElementById('profile-pic');
            const userDropdown = document.getElementById('user-dropdown');
            profilePic.addEventListener('click', () => {
                userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
            });
            document.addEventListener('click', (e) => {
                if (!profilePic.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>