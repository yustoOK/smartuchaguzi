<?php
header('Content-Type: application/json');
include '../../db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['election_id']) || !isset($_POST['position_id']) || !isset($_POST['candidate_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$election_id = (int)$_POST['election_id'];
$position_id = (int)$_POST['position_id'];
$candidate_id = (int)$_POST['candidate_id'];

try {
    $stmt = $db->prepare("SELECT college_id, hostel_id FROM userdetails WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new Exception('User details not found');
    }
    $college_id = $user['college_id'];
    $hostel_id = $user['hostel_id'] ?: 0;

    $vote_data = [
        'electionId' => $election_id,
        'positionId' => $position_id,
        'candidateId' => $candidate_id,
        'collegeId' => $college_id,
        'hostelId' => $hostel_id
    ];
    $vote_hash = hash('sha256', json_encode($vote_data));

    $node_script = '
        const ethers = require("ethers");
        const provider = new ethers.providers.JsonRpcProvider("' . getenv('SEPOLIA_RPC_URL') . '");
        const wallet = new ethers.Wallet("' . getenv('PRIVATE_KEY') . '", provider);
        const contract = new ethers.Contract(
            "' . getenv('VOTE_CONTRACT_ADDRESS') . '",
            ' . json_encode(json_decode(file_get_contents('../../blockchain/artifacts/contracts/VoteContract.sol/VoteContract.json'))->abi) . ',
            wallet
        );
        async function castVote() {
            try {
                const tx = await contract.castVote(
                    ' . $election_id . ',
                    ' . $position_id . ',
                    ' . $candidate_id . ',
                    ' . $college_id . ',
                    ' . $hostel_id . '
                );
                const receipt = await tx.wait();
                console.log(JSON.stringify({ success: true, txHash: receipt.transactionHash }));
            } catch (error) {
                console.log(JSON.stringify({ success: false, error: error.message }));
            }
        }
        castVote();
    ';
    file_put_contents('temp.js', $node_script);
    $output = shell_exec('node temp.js 2>&1');
    unlink('temp.js');

    $result = json_decode($output, true);
    if (!$result || !$result['success']) {
        throw new Exception('Blockchain vote failed: ' . ($result['error'] ?? 'Unknown error'));
    }

    $stmt = $db->prepare(
        "INSERT INTO votes (vote_id, user_id, election_id, candidate_id, vote_timestamp, block_hash) 
         VALUES (?, ?, ?, ?, NOW(), ?)"
    );
    $vote_id = uniqid();
    $stmt->bind_param('siiis', $vote_id, $user_id, $election_id, $candidate_id, $vote_hash);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'txHash' => $result['txHash']]);
} catch (Exception $e) {
    error_log("Cast vote failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to cast vote']);
}
?>