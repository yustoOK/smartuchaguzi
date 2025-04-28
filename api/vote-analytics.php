<?php
header('Content-Type: application/json');
include '../db.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

// Validating election_id
if (!$election_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Election ID is required']);
    exit;
}

try {
    // Fetching verified votes from blockchain
    $votes_response = file_get_contents("http://localhost/smartuchaguzi/api/blockchain/get-votes.php?election_id=$election_id");
    $votes_data = json_decode($votes_response, true);
    if (isset($votes_data['error'])) {
        throw new Exception($votes_data['error']);
    }
    $votes = $votes_data['votes'];

    // Fetching election positions
    $query = "SELECT ep.id, ep.name 
              FROM election_positions ep 
              JOIN elections e ON ep.election_id = e.id 
              WHERE e.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $election_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $positions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculating analytics
    $total_votes = count($votes);
    $analytics = [];

    foreach ($positions as $position) {
        // Fetching candidates for this position
        $stmt = $db->prepare(
            "SELECT c.id, u.fname AS name 
             FROM candidates c 
             JOIN users u ON c.user_id = u.user_id 
             WHERE c.position_id = ?"
        );
        $stmt->bind_param('i', $position['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $candidates = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $position_data = [
            'id' => $position['id'],
            'name' => $position['name'],
            'candidates' => [],
            'totalVotes' => 0,
            'winner' => null
        ];

        // Counting votes per candidate and determining a winner
        $max_votes = 0;
        $winners = [];
        foreach ($candidates as &$candidate) {
            $candidate['votes'] = 0;
            foreach ($votes as $vote) {
                if ($vote['candidate_id'] == $candidate['id']) {
                    $candidate['votes']++;
                    $position_data['totalVotes']++;
                }
            }
            if ($candidate['votes'] > $max_votes) {
                $max_votes = $candidate['votes'];
                $winners = [$candidate['name']];
            } elseif ($candidate['votes'] == $max_votes && $max_votes > 0) {
                $winners[] = $candidate['name'];
            }
            $position_data['candidates'][] = $candidate;
        }
        $position_data['winner'] = $max_votes > 0 ? (count($winners) > 1 ? 'Tie: ' . implode(', ', $winners) : $winners[0]) : null;

        $analytics[] = $position_data;
    }

    echo json_encode([
        'election_id' => $election_id,
        'positions' => $analytics,
        'totalVotes' => $total_votes
    ]);
} catch (Exception $e) {
    error_log("Vote analytics failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate analytics']);
}
?>