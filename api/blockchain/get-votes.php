<?php
header('Content-Type: application/json');
include '../../db.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if (!$election_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Election ID is required']);
    exit;
}

try {
     $query = "SELECT v.vote_id, v.user_id, v.election_id, v.candidate_id, v.vote_timestamp, br.hash AS blockchain_hash 
              FROM votes v 
              JOIN blockchain_records br ON v.vote_id = br.vote_id 
              WHERE v.election_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $election_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $db_votes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

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

    if ($output === null) {
        throw new Exception('Node.js execution failed: No output received');
    }
    $blockchain_votes = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse blockchain votes: ' . json_last_error_msg());
    }
    if (!$blockchain_votes) {
        throw new Exception('Failed to fetch blockchain votes');
    }

    $verified_votes = [];
    foreach ($db_votes as $db_vote) {
        foreach ($blockchain_votes as $bc_vote) {
            $stmt = $db->prepare("SELECT c.position_id FROM candidates WHERE id = ?");
            $stmt->bind_param('i', $db_vote['candidate_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $position = $result->fetch_assoc();
            $stmt->close();

            $db_timestamp = strtotime($db_vote['vote_timestamp']);
            $bc_timestamp = (int)$bc_vote['timestamp'];
            $timestamp_diff = abs($db_timestamp - $bc_timestamp);

            if (
                (string)$db_vote['election_id'] === $bc_vote['electionId'] &&
                $bc_vote['voter'] === getenv('WALLET_ADDRESS') && // Single wallet address
                (string)$position['position_id'] === $bc_vote['positionId'] &&
                (string)$db_vote['candidate_id'] === $bc_vote['candidateId'] &&
                $timestamp_diff <= 60 // Allows 60-second difference
            ) {
                // Verify hash
                $vote_data = [
                    'electionId' => (int)$bc_vote['electionId'],
                    'voter' => $bc_vote['voter'],
                    'positionId' => (int)$bc_vote['positionId'],
                    'candidateId' => (int)$bc_vote['candidateId'],
                    'timestamp' => (int)$bc_vote['timestamp']
                ];
                if (hash('sha256', json_encode($vote_data)) === $db_vote['blockchain_hash']) {
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
    }

    echo json_encode(['votes' => $verified_votes]);
} catch (Exception $e) {
    error_log("Get votes failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve votes']);
}
?>