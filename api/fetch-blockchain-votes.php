<?php
/*
header('Content-Type: application/json');

require_once '../vendor/autoload.php'; 
use Web3\Web3;
use Web3\Contract;

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if (!$election_id) {
    echo json_encode(['error' => 'Invalid election ID']);
    exit;
}

try {
    $web3 = new Web3('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
    
   $contractAbi = [
        [
            "inputs": [],
            "stateMutability": "nonpayable",
            "type": "constructor"
        ],
        [
            "anonymous": false,
            "inputs": [
                {"indexed": false, "internalType": "uint256", "name": "electionId", "type": "uint256"},
                {"indexed": true, "internalType": "address", "name": "voter", "type": "address"},
                {"indexed": false, "internalType": "uint256", "name": "positionId", "type": "uint256"},
                {"indexed": false, "internalType": "string", "name": "candidateId", "type": "string"},
                {"indexed": false, "internalType": "string", "name": "candidateName", "type": "string"},
                {"indexed": false, "internalType": "string", "name": "positionName", "type": "string"}
            ],
            "name": "VoteCast",
            "type": "event"
        ],
        [
            "inputs": [],
            "name": "admin",
            "outputs": [{"internalType": "address", "name": "", "type": "address"}],
            "stateMutability": "view",
            "type": "function"
        ],
        [
            "inputs": [
                {"internalType": "uint256", "name": "electionId", "type": "uint256"},
                {"internalType": "uint256", "name": "positionId", "type": "uint256"},
                {"internalType": "string", "name": "candidateId", "type": "string"},
                {"internalType": "string", "name": "candidateName", "type": "string"},
                {"internalType": "string", "name": "positionName", "type": "string"}
            ],
            "name": "castVote",
            "outputs": [],
            "stateMutability": "nonpayable",
            "type": "function"
        ],
        [
            "inputs": [
                {"internalType": "uint256", "name": "positionId", "type": "uint256"},
                {"internalType": "string", "name": "candidateId", "type": "string"}
            ],
            "name": "getVoteCount",
            "outputs": [{"internalType": "uint256", "name": "", "type": "uint256"}],
            "stateMutability": "view",
            "type": "function"
        ],
        [
            "inputs": [{"internalType": "uint256", "name": "electionId", "type": "uint256"}],
            "name": "getVotesByElection",
            "outputs": [
                {
                    "components": [
                        {"internalType": "uint256", "name": "electionId", "type": "uint256"},
                        {"internalType": "address", "name": "voter", "type": "address"},
                        {"internalType": "uint256", "name": "positionId", "type": "uint256"},
                        {"internalType": "string", "name": "candidateId", "type": "string"},
                        {"internalType": "uint256", "name": "timestamp", "type": "uint256"},
                        {"internalType": "string", "name": "candidateName", "type": "string"},
                        {"internalType": "string", "name": "positionName", "type": "string"}
                    ],
                    "internalType": "struct VoteContract.Vote[]",
                    "name": "",
                    "type": "tuple[]"
                }
            ],
            "stateMutability": "view",
            "type": "function"
        ],
        [
            "inputs": [
                {"internalType": "address", "name": "", "type": "address"},
                {"internalType": "uint256", "name": "", "type": "uint256"},
                {"internalType": "string", "name": "", "type": "string"}
            ],
            "name": "hasVoted",
            "outputs": [{"internalType": "bool", "name": "", "type": "bool"}],
            "stateMutability": "view",
            "type": "function"
        ],
        [
            "inputs": [
                {"internalType": "uint256", "name": "", "type": "uint256"},
                {"internalType": "string", "name": "", "type": "string"}
            ],
            "name": "voteCount",
            "outputs": [{"internalType": "uint256", "name": "", "type": "uint256"}],
            "stateMutability": "view",
            "type": "function"
        ],
        [
            "inputs": [{"internalType": "uint256", "name": "", "type": "uint256"}],
            "name": "votes",
            "outputs": [
                {"internalType": "uint256", "name": "electionId", "type": "uint256"},
                {"internalType": "address", "name": "voter", "type": "address"},
                {"internalType": "uint256", "name": "positionId", "type": "uint256"},
                {"internalType": "string", "name": "candidateId", "type": "string"},
                {"internalType": "uint256", "name": "timestamp", "type": "uint256"},
                {"internalType": "string", "name": "candidateName", "type": "string"},
                {"internalType": "string", "name": "positionName", "type": "string"}
            ],
            "stateMutability": "view",
            "type": "function"
        ]
    ];

    $contractAddress = '0xC046c854C85e56DB6AF41dF3934DD671831d9d09';
    $contract = new Contract($web3->provider, $contractAddress, $contractAbi);

    // Call the getVotesByElection function
    $votes = $contract->at($contractAddress)->call('getVotesByElection', [$election_id]);

    // Format the response
    $formattedVotes = [];
    foreach ($votes[0] as $vote) {
        $formattedVotes[] = [
            'electionId' => $vote['electionId']->toString(),
            'voter' => $vote['voter'],
            'positionId' => $vote['positionId']->toString(),
            'candidateId' => $vote['candidateId'],
            'timestamp' => $vote['timestamp']->toString(),
            'candidateName' => $vote['candidateName'],
            'positionName' => $vote['positionName']
        ];
    }

    echo json_encode(['votes' => $formattedVotes]);
} catch (Exception $e) {
    error_log("Error in fetch-blockchain-votes.php: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch votes: ' . $e->getMessage()]);
}
    */
?>