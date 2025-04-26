<?php
header('Content-Type: application/json');
include '../../db.php';
session_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['user_id']) || !isset($input['candidate_id'])) {
        throw new Exception('Invalid input data');
    }

    $user_id = (int)$input['user_id'];
    $candidate_id = (int)$input['candidate_id'];

    // Validate session
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== $user_id) {
        throw new Exception('Unauthorized user');
    }

    // Fetch candidate details
    $stmt = $db->prepare("SELECT election_id, position_id FROM candidates WHERE id = ?");
    $stmt->bind_param('i', $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $stmt->close();

    if (!$candidate) {
        throw new Exception('Candidate not found');
    }

    $election_id = $candidate['election_id'];
    $position_id = $candidate['position_id'];

    // Fetch user details for college_id and hostel_id
    $stmt = $db->prepare("SELECT college_id, hostel_id FROM userdetails WHERE user_id = ? AND processed_at IS NOT NULL");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new Exception('User details not found or not processed');
    }
    $college_id = $user['college_id'];
    $hostel_id = $user['hostel_id'] ?: 0;

    // Check for duplicate votes
    $stmt = $db->prepare(
        "SELECT id FROM votes 
         WHERE user_id = ? AND election_id = ? AND candidate_id IN (
             SELECT id FROM candidates WHERE position_id = ?
         )"
    );
    $stmt->bind_param('iii', $user_id, $election_id, $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_vote = $result->fetch_assoc();
    $stmt->close();

    if ($existing_vote) {
        throw new Exception('You have already voted for this position');
    }

    // Fraud detection (before blockchain and database operations)
    $vote_timestamp = date('Y-m-d H:i:s');
    $fraud_check_data = [
        'user_id' => $user_id,
        'voter_id' => (string)$user_id,
        'vote_timestamp' => $vote_timestamp,
        'time_diff' => 2.5,
        'vote_frequency' => 0.1,
        'vpn_usage' => false,
        'election_id' => $election_id 
    ];

    $ch = curl_init('http://localhost/smartuchaguzi/api/fraud-detection.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fraud_check_data));
    $fraud_response = curl_exec($ch);
    curl_close($ch);

    $fraud_result = json_decode($fraud_response, true);
    if (!$fraud_result || isset($fraud_result['error']) || $fraud_result['action'] != 'allow') {
        throw new Exception('Vote flagged as potential fraud');
    }

    // Generate vote hash
    $vote_data = [
        'electionId' => $election_id,
        'voter' => getenv('WALLET_ADDRESS'),
        'positionId' => $position_id,
        'candidateId' => $candidate_id,
        'timestamp' => strtotime($vote_timestamp)
    ];
    $vote_hash = hash('sha256', json_encode($vote_data));

    // Cast vote on the blockchain
    $node_script = '
        const ethers = require("ethers");
        const provider = new ethers.providers.JsonRpcProvider("' . getenv('SEPOLIA_RPC_URL') . '");
        const wallet = new ethers.Wallet("' . getenv('PRIVATE_KEY') . '", provider);
        const contract = new ethers.Contract(
            "' . getenv('VOTE_CONTRACT_ADDRESS') . '",
            ' . json_encode(json_decode(file_get_contents('../../blockchain/artifacts/contracts/VoteContract.sol/VoteContract.json'))->abi) . ',
            wallet
        );
        async function submitVote() {
            try {
                const tx = await contract.castVote(
                    ' . $election_id . ',
                    ' . $position_id . ',
                    ' . $candidate_id . ',
                    ' . $college_id . ',
                    ' . $hostel_id . '
                );
                const receipt = await tx.wait();
                console.log(JSON.stringify({ hash: receipt.transactionHash }));
            } catch (error) {
                console.log(JSON.stringify({ error: error.message }));
            }
        }
        submitVote();
    ';
    file_put_contents('temp.js', $node_script);
    $output = shell_exec('node temp.js 2>&1');
    unlink('temp.js');

    // Improved error handling for Node.js execution
    if ($output === null) {
        throw new Exception('Node.js execution failed: No output received');
    }
    $blockchain_result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse blockchain response: ' . json_last_error_msg());
    }
    if (!$blockchain_result || isset($blockchain_result['error'])) {
        throw new Exception('Failed to store vote on blockchain: ' . ($blockchain_result['error'] ?? 'Unknown error'));
    }

    // Store vote in the database
    $stmt = $db->prepare(
        "INSERT INTO votes (user_id, election_id, candidate_id, vote_timestamp, blockchain_hash, is_anonymized) 
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param('iiiss', $user_id, $election_id, $candidate_id, $vote_timestamp, $vote_hash);
    $stmt->execute();
    $vote_id = $db->insert_id;
    $stmt->close();

    // Store blockchain record
    $stmt = $db->prepare(
        "INSERT INTO blockchain_records (vote_id, election_id, hash, timestamp) 
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('iis', $vote_id, $election_id, $blockchain_result['hash']);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'vote_id' => $vote_id,
        'blockchain_hash' => $blockchain_result['hash']
    ]);
} catch (Exception $e) {
    error_log("Submit vote failed: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>