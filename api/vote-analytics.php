<?php
header('Content-Type: application/json');
include '../db.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

try {
    // Fetch verified votes from blockchain
    $votes_response = file_get_contents("http://localhost/smartuchaguzi/api/blockchain/get-votes.php" . ($election_id ? "?election_id=$election_id" : ''));
    $votes_data = json_decode($votes_response, true);
    if (isset($votes_data['error'])) {
        throw new Exception($votes_data['error']);
    }
    $votes = $votes_data['votes'];

    // Fetch election positions
    $query = $election_id
        ? "SELECT ep.id, ep.name 
           FROM election_positions ep 
           JOIN elections e ON ep.election_id = e.id 
           WHERE e.id = ?"
        : "SELECT ep.id, ep.name 
           FROM election_positions ep 
           JOIN elections e ON ep.election_id = e.id";
    $stmt = $db->prepare($query);
    if ($election_id) {
        $stmt->bind_param('i', $election_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $positions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate analytics
    $total_votes = count($votes);
    $analytics = [];

    foreach ($positions as $position) {
        // Fetch candidates for this position
        $stmt = $db->prepare(
            "SELECT c.id, u.fname AS name 
             FROM candidates c 
             JOIN users u ON c.user_id = u.id 
             WHERE c.election_id = ? AND c.position_id = ?"
        );
        $stmt->bind_param('ii', $election_id, $position['id']);
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

        // Count votes per candidate
        $max_votes = 0;
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
                $position_data['winner'] = $candidate['name'];
            }
            $position_data['candidates'][] = $candidate;
        }

        $analytics[] = $position_data;
    }

    echo json_encode([
        'positions' => $analytics,
        'totalVotes' => $total_votes
    ]);
} catch (Exception $e) {
    error_log("Vote analytics failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate analytics']);
}
?>