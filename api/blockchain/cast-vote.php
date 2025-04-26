<?php
header('Content-Type: application/json');
include '../../db.php';
session_start();

// Input validation
if (!isset($_SESSION['user_id']) || !isset($_POST['election_id']) || !isset($_POST['position_id']) || !isset($_POST['candidate_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing some required fields']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$election_id = (int)$_POST['election_id'];
$position_id = (int)$_POST['position_id'];
$candidate_id = (int)$_POST['candidate_id'];

try {
    // Fetch user details
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

    // Check for duplicate votes (since smart contract uses a single wallet address)
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
        http_response_code(403);
        echo json_encode(['error' => 'User has already voted for this position']);
        exit;
    }

    // Generate vote hash (adjusted to match blockchain data)
    $vote_data = [
        'electionId' => $election_id,
        'voter' => getenv('WALLET_ADDRESS'), // Assuming the wallet address is available as an env variable
        'positionId' => $position_id,
        'candidateId' => $candidate_id,
        'timestamp' => time() // Approximate timestamp; will adjust in get-votes.php
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

    // Improved error handling for Node.js execution
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

    // Store vote in the database
    $stmt = $db->prepare(
        "INSERT INTO votes (user_id, election_id, candidate_id, vote_timestamp, blockchain_hash, is_anonymous) 
         VALUES (?, ?, ?, NOW(), ?, 0)"
    );
    $stmt->bind_param('iiis', $user_id, $election_id, $candidate_id, $vote_hash);
    $stmt->execute();
    $vote_id = $db->insert_id; // Get the auto-incremented vote_id
    $stmt->close();

    // Store blockchain record
    $stmt = $db->prepare(
        "INSERT INTO blockchain_records (vote_id, election_id, hash, timestamp) 
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->bind_param('iis', $vote_id, $election_id, $result['txHash']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'txHash' => $result['txHash']]);
} catch (Exception $e) {
    error_log("Cast vote failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to cast vote']);
}
?>