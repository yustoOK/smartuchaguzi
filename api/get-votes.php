<?php
header('Content-Type: application/json');
include '../db.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

try {
    // Fetch vote hashes from database
    $query = $election_id
        ? "SELECT vote_id, user_id, election_id, candidate_id, vote_timestamp, block_hash FROM votes WHERE election_id = ?"
        : "SELECT vote_id, user_id, election_id, candidate_id, vote_timestamp, block_hash FROM votes";
    $stmt = $db->prepare($query);
    if ($election_id) {
        $stmt->bind_param('i', $election_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $db_votes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Placeholder: Fetch votes from blockchain (replace with your contract's API)
    $blockchain_votes = [];
    $blockchain_api = 'https://your-blockchain-api/votes'; // Replace with your endpoint
    $ch = curl_init($blockchain_api . ($election_id ? "?election_id=$election_id" : ''));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $blockchain_votes = json_decode($response, true);
        if (!$blockchain_votes) {
            throw new Exception('Failed to parse blockchain response');
        }
    } else {
        throw new Exception('Failed to connect to blockchain');
    }

    // Verify votes
    $verified_votes = [];
    foreach ($db_votes as $db_vote) {
        foreach ($blockchain_votes as $bc_vote) {
            if ($db_vote['vote_id'] == $bc_vote['vote_id'] && hash('sha256', json_encode($bc_vote)) === $db_vote['block_hash']) {
                $verified_votes[] = [
                    'vote_id' => $db_vote['vote_id'],
                    'user_id' => $db_vote['user_id'],
                    'election_id' => $db_vote['election_id'],
                    'candidate_id' => $db_vote['candidate_id'],
                    'vote_timestamp' => $db_vote['vote_timestamp']
                ];
                break;
            }
        }
    }

    echo json_encode(['votes' => $verified_votes]);
} catch (Exception $e) {
    error_log("Get votes failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve votes']);
}
?>