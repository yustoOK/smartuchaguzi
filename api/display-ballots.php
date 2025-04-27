<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?error=' . urlencode('Please log in to vote.'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$elections = [];
$user_details = [];

// Fetch user details
try {
    $stmt = $db->prepare(
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
    }
} catch (Exception $e) {
    error_log("Fetch user details failed: " . $e->getMessage());
    $errors[] = "Failed to load user details due to a server error.";
}

if (empty($errors)) {
    $association = $user_details['association'];
    $college_id = $user_details['college_id'];
    $hostel_id = $user_details['hostel_id'] ?: 0;

    // Fetch active elections
    try {
        $stmt = $db->query(
            "SELECT election_id, title
             FROM elections
             WHERE status = 'ongoing'
             ORDER BY start_time ASC"
        );
        $elections = $stmt->fetch_all(MYSQLI_ASSOC);

        // For each election, fetch eligible positions and candidates
        foreach ($elections as &$election) {
            $election_id = $election['election_id'];
            $positions = [];

            // Fetch positions the user is eligible to vote for using scope
            $query = "
                SELECT ep.position_id, ep.name AS position_name, ep.scope, ep.college_id AS position_college_id, ep.hostel_id
                FROM electionpositions ep
                WHERE ep.election_id = ?
                AND ep.position_id IN (
                    SELECT c.position_id
                    FROM candidates c
                    WHERE c.election_id = ? AND c.association = ?
                )
                AND (
                    ep.scope = 'university'
                    OR (ep.scope = 'college' AND ep.college_id = ?)
                ";
            if ($association === 'UDOSO' && $hostel_id) {
                $query .= " OR (ep.scope = 'hostel' AND ep.hostel_id = ?)";
            }
            $query .= ") ORDER BY ep.position_id";

            $stmt = $db->prepare($query);
            if ($association === 'UDOSO' && $hostel_id) {
                $stmt->bind_param('iisii', $election_id, $election_id, $association, $college_id, $hostel_id);
            } else {
                $stmt->bind_param('iisi', $election_id, $election_id, $association, $college_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($position = $result->fetch_assoc()) {
                $position_id = $position['position_id'];

                // Check if the user has already voted for this position
                $vote_stmt = $db->prepare(
                    "SELECT 1 FROM votes 
                     WHERE user_id = ? AND election_id = ? AND candidate_id IN (
                         SELECT id FROM candidates WHERE position_id = ?
                     )"
                );
                $vote_stmt->bind_param('iii', $user_id, $election_id, $position_id);
                $vote_stmt->execute();
                $vote_result = $vote_stmt->get_result();
                if ($vote_result->num_rows > 0) {
                    $position['already_voted'] = true;
                } else {
                    $position['already_voted'] = false;
                }
                $vote_stmt->close();

                // Fetch candidates for this position, including official_id and association
                $cand_stmt = $db->prepare(
                    "SELECT id, official_id, firstname, middlename, lastname, association
                     FROM candidates
                     WHERE election_id = ? AND position_id = ? AND association = ?"
                );
                $cand_stmt->bind_param('iis', $election_id, $position_id, $association);
                $cand_stmt->execute();
                $cand_result = $cand_stmt->get_result();
                $candidates = $cand_result->fetch_all(MYSQLI_ASSOC);
                $cand_stmt->close();

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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>smartuchaguzi | Cast your Vote</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('../images/cive.jpeg');
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 80px auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #1a3c34;
            margin-bottom: 20px;
        }
        h3 {
            color: #1a3c34;
            margin-top: 20px;
        }
        .election-section {
            margin-bottom: 30px;
        }
        .position-section {
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .candidate-table th, .candidate-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .candidate-table th {
            background: #e0e0e0;
            color: #1a3c34;
        }
        .candidate-table tr:hover {
            background: #f1f1f1;
        }
        button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background: #e76f51;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error {
            color: #e76f51;
            margin-bottom: 15px;
        }
        .success {
            color: #2a9d8f;
            margin-bottom: 15px;
        }
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #f4a261;
            text-decoration: none;
            margin-right: 5px;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #2d3748;
            margin-right: 5px;
        }
        .breadcrumb i {
            margin: 0 5px;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="../index.php">Home</a> <i class="fas fa-chevron-right"></i>
            <span>Cast your vote</span>
        </div>
        <h2>Vote for Candidates</h2>

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
                        <p>No positions available for you to vote in this election.</p>
                    <?php else: ?>
                        <?php foreach ($election['positions'] as $position): ?>
                            <div class="position-section">
                                <h4>Position: <?php echo htmlspecialchars($position['position_name']); ?></h4>
                                <?php if ($position['already_voted']): ?>
                                    <p>You have already voted for this position.</p>
                                <?php elseif (empty($position['candidates'])): ?>
                                    <p>No candidates available for this position.</p>
                                <?php else: ?>
                                    <form class="vote-form" data-election-id="<?php echo $election['election_id']; ?>" data-position-id="<?php echo $position['position_id']; ?>">
                                        <table class="candidate-table">
                                            <thead>
                                                <tr>
                                                    <th>Official ID</th>
                                                    <th>Full Name</th>
                                                    <th>Association</th>
                                                    <th>Position</th>
                                                    <th>Vote</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($position['candidates'] as $candidate): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($candidate['official_id'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($candidate['firstname'] . ' ' . ($candidate['middlename'] ? $candidate['middlename'] . ' ' : '') . $candidate['lastname']); ?></td>
                                                        <td><?php echo htmlspecialchars($candidate['association'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($position['position_name']); ?></td>
                                                        <td>
                                                            <input type="radio" name="candidate_id" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>" required>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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

    <script>
        document.querySelectorAll('.vote-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const electionId = form.getAttribute('data-election-id');
                const positionId = form.getAttribute('data-position-id');
                const candidateId = form.querySelector('input[name="candidate_id"]:checked')?.value;

                if (!candidateId) {
                    showError('Please select a candidate to vote for.');
                    return;
                }

                const submitButton = form.querySelector('button');
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';

                try {
                    const response = await fetch('../../api/process-vote.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            election_id: electionId,
                            position_id: positionId,
                            candidate_id: candidateId
                        })
                    });

                    const result = await response.json();
                    if (response.ok && result.success) {
                        showSuccess('Vote cast successfully! Transaction Hash: ' + result.txHash);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        throw new Error(result.error || 'Failed to cast vote.');
                    }
                } catch (error) {
                    showError(error.message);
                } finally {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Cast Vote';
                }
            });
        });

        function showSuccess(message) {
            const successDiv = document.getElementById('success-message');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            document.getElementById('error-message').style.display = 'none';
        }

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            document.getElementById('success-message').style.display = 'none';
        }
    </script>
</body>
</html>