<?php
header('Content-Type: application/json');
include '../../db.php';

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

    // Fetch votes from blockchain using JavaScript (executed server-side via Node.js)
    $node_script = '
        const ethers = require("ethers");
        const provider = new ethers.providers.JsonRpcProvider("' . getenv('SEPOLIA_RPC_URL') . '");
        const contract = new ethers.Contract(
            "' . getenv('VOTE_CONTRACT_ADDRESS') . '",
            ' . json_encode(json_decode(file_get_contents('../../blockchain/artifacts/contracts/VoteContract.sol/VoteContract.json'))->abi) . ',
            provider
        );
        async function getVotes() {
            const votes = await contract.getVotesByElection(' . $election_id . ');
            console.log(JSON.stringify(votes.map(v => ({
                electionId: v.electionId.toString(),
                voter: v.voter,
                positionId: v.positionId.toString(),
                candidateId: v.candidateId.toString(),
                timestamp: v.timestamp.toString()
            }))));
        }
        getVotes();
    ';
    file_put_contents('temp.js', $node_script);
    $output = shell_exec('node temp.js 2>&1');
    unlink('temp.js');

    $blockchain_votes = json_decode($output, true);
    if (!$blockchain_votes) {
        throw new Exception('Failed to fetch blockchain votes');
    }

    // Verify votes
    $verified_votes = [];
    foreach ($db_votes as $db_vote) {
        foreach ($blockchain_votes as $bc_vote) {
            if ((string)$db_vote['vote_id'] === $bc_vote['vote_id'] && hash('sha256', json_encode($bc_vote)) === $db_vote['block_hash']) {
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