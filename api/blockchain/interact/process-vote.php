<?php
header('Content-Type: application/json');
include '../../db.php';
session_start();

// Input validation
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

if (!isset($_SESSION['user_id']) || !isset($input['election_id']) || !isset($input['position_id']) || !isset($input['candidate_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$election_id = (int)$input['election_id'];
$position_id = (int)$input['position_id'];
$candidate_id = (int)$input['candidate_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];

// Validating session for JSON requests
if (isset($input['user_id']) && (int)$input['user_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized user']);
    exit;
}

try {
     $log_stmt = $db->prepare("INSERT INTO auditlogs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
    $action = "Vote attempt";
    $details = "User attempted to vote for candidate_id $candidate_id in election_id $election_id for position_id $position_id";
    $log_stmt->bind_param('isss', $user_id, $action, $ip_address, $details);
    $log_stmt->execute();
    $log_stmt->close();

    // Checking if the election is ongoing
    $stmt = $db->prepare("SELECT status FROM elections WHERE election_id = ?");
    $stmt->bind_param('i', $election_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $election = $result->fetch_assoc();
    $stmt->close();

    if (!$election || $election['status'] !== 'ongoing') {
        throw new Exception('Election is not ongoing');
    }

    // Fetching user details
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

    // Validating candidate
    $stmt = $db->prepare("SELECT election_id, position_id FROM candidates WHERE id = ?");
    $stmt->bind_param('i', $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $stmt->close();

    if (!$candidate || $candidate['election_id'] !== $election_id || $candidate['position_id'] !== $position_id) {
        throw new Exception('Invalid candidate for this position or election');
    }

    // Checking for duplicate votes
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM votes 
         WHERE user_id = ? AND election_id = ? AND candidate_id IN (
             SELECT id FROM candidates WHERE position_id = ?
         )"
    );
    $stmt->bind_param('iii', $user_id, $election_id, $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vote_count = $result->fetch_row()[0];
    $stmt->close();
    if ($vote_count > 0) {
        throw new Exception('You have already voted for this position');
    }

    // Fraud detection (Most Important)
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

    // Sending data to fraud detection API(the API is hosted locally)
    $ch = curl_init('http://localhost/smartuchaguzi/api/fraud-detection.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fraud_check_data));
    $fraud_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($fraud_response === false) {
        throw new Exception('Fraud detection failed: ' . $curl_error);
    }
    $fraud_result = json_decode($fraud_response, true);
    if (!$fraud_result || isset($fraud_result['error']) || $fraud_result['action'] !== 'allow') {
        // Log fraud detection failure
        $log_stmt = $db->prepare("INSERT INTO auditlogs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $action = "Fraud detected";
        $details = "Fraud detected: " . ($fraud_result['error'] ?? 'Vote flagged as potential fraud');
        $log_stmt->bind_param('isss', $user_id, $action, $ip_address, $details);
        $log_stmt->execute();
        $log_stmt->close();

        throw new Exception('Vote flagged as potential fraud');
    }

    // Generating vote hash
    $vote_data = [
        'electionId' => $election_id,
        'voter' => getenv('WALLET_ADDRESS'),
        'positionId' => $position_id,
        'candidateId' => $candidate_id,
        'timestamp' => strtotime($vote_timestamp)
    ];
    $vote_hash = hash('sha256', json_encode($vote_data));


    
    // Casting vote on the blockchain (Most Important)
    $node_script = '
        const ethers = require("ethers");
        const provider = new ethers.providers.JsonRpcProvider("' . getenv('SEPOLIA_RPC_URL') . '");
        const wallet = new ethers.Wallet("' . getenv('PRIVATE_KEY') . '", provider);
        const contract = new ethers.Contract(
            "' . getenv('VOTE_CONTRACT_ADDRESS') . '",
            ' . json_encode(json_decode(file_get_contents('../../blockchain/artifacts/api/blockchain/contracts/VoteContract.sol/VoteContract.json'))->abi) . ',
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

    if ($output === null) {
        throw new Exception('Node.js execution failed: No output received');
    }
    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse blockchain response: ' . json_last_error_msg());
    }
    if (!$result || !$result['success']) {
        throw new Exception('Blockchain vote failed: ' . ($result['error'] ?? 'Unknown error'));
    }



    
    // Storing vote in the database
    $stmt = $db->prepare(
        "INSERT INTO votes (user_id, election_id, candidate_id, vote_timestamp, blockchain_hash, is_anonymized) 
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param('iiiss', $user_id, $election_id, $candidate_id, $vote_timestamp, $vote_hash);
    $stmt->execute();
    $vote_id = $db->insert_id;
    $stmt->close();

    // Storing blockchain record
    $stmt = $db->prepare(
        "INSERT INTO blockchain_records (vote_id, election_id, hash, timestamp) 
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('iis', $vote_id, $election_id, $result['txHash']);
    $stmt->execute();
    $stmt->close();

    // Log success
    $log_stmt = $db->prepare("INSERT INTO auditlogs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
    $action = "Vote cast";
    $details = "Vote successfully cast for candidate_id $candidate_id in election_id $election_id";
    $log_stmt->bind_param('isss', $user_id, $action, $ip_address, $details);
    $log_stmt->execute();
    $log_stmt->close();

    echo json_encode([
        'success' => true,
        'vote_id' => $vote_id,
        'txHash' => $result['txHash']
    ]);
} catch (Exception $e) {
     $log_stmt = $db->prepare("INSERT INTO auditlogs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
    $action = "Vote failed";
    $details = "Failed to cast vote: " . $e->getMessage();
    $log_stmt->bind_param('isss', $user_id, $action, $ip_address, $details);
    $log_stmt->execute();
    $log_stmt->close();

    error_log("Process vote failed: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>