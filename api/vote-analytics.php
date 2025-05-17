<?php
header('Content-Type: application/json');

// Ensure this script is called via AJAX from admin-dashboard.php
if (!isset($_GET['election_id']) || empty($_GET['election_id'])) {
    echo json_encode(['error' => 'Election ID is required']);
    exit;
}

$electionId = filter_var($_GET['election_id'], FILTER_VALIDATE_INT);

if ($electionId === false) {
    echo json_encode(['error' => 'Invalid election ID']);
    exit;
}

$contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';
$abi = '[
    {
      "inputs": [],
      "stateMutability": "nonpayable",
      "type": "constructor"
    },
    {
      "anonymous": false,
      "inputs": [
        {
          "indexed": false,
          "internalType": "uint256",
          "name": "electionId",
          "type": "uint256"
        },
        {
          "indexed": true,
          "internalType": "address",
          "name": "voter",
          "type": "address"
        },
        {
          "indexed": false,
          "internalType": "uint256",
          "name": "positionId",
          "type": "uint256"
        },
        {
          "indexed": false,
          "internalType": "uint256",
          "name": "candidateId",
          "type": "uint256"
        },
        {
          "indexed": false,
          "internalType": "string",
          "name": "candidateName",
          "type": "string"
        },
        {
          "indexed": false,
          "internalType": "string",
          "name": "positionName",
          "type": "string"
        }
      ],
      "name": "VoteCast",
      "type": "event"
    },
    {
      "inputs": [],
      "name": "admin",
      "outputs": [
        {
          "internalType": "address",
          "name": "",
          "type": "address"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "electionId",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "positionId",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "candidateId",
          "type": "uint256"
        },
        {
          "internalType": "string",
          "name": "candidateName",
          "type": "string"
        },
        {
          "internalType": "string",
          "name": "positionName",
          "type": "string"
        }
      ],
      "name": "castVote",
      "outputs": [],
      "stateMutability": "nonpayable",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "positionId",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "candidateId",
          "type": "uint256"
        }
      ],
      "name": "getVoteCount",
      "outputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "electionId",
          "type": "uint256"
        }
      ],
      "name": "getVotesByElection",
      "outputs": [
        {
          "components": [
            {
              "internalType": "uint256",
              "name": "electionId",
              "type": "uint256"
            },
            {
              "internalType": "address",
              "name": "voter",
              "type": "address"
            },
            {
              "internalType": "uint256",
              "name": "positionId",
              "type": "uint256"
            },
            {
              "internalType": "uint256",
              "name": "candidateId",
              "type": "uint256"
            },
            {
              "internalType": "uint256",
              "name": "timestamp",
              "type": "uint256"
            },
            {
              "internalType": "string",
              "name": "candidateName",
              "type": "string"
            },
            {
              "internalType": "string",
              "name": "positionName",
              "type": "string"
            }
          ],
          "internalType": "struct VoteContract.Vote[]",
          "name": "",
          "type": "tuple[]"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "address",
          "name": "",
          "type": "address"
        },
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "name": "hasVoted",
      "outputs": [
        {
          "internalType": "bool",
          "name": "",
          "type": "bool"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "name": "voteCount",
      "outputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "name": "votes",
      "outputs": [
        {
          "internalType": "uint256",
          "name": "electionId",
          "type": "uint256"
        },
        {
          "internalType": "address",
          "name": "voter",
          "type": "address"
        },
        {
          "internalType": "uint256",
          "name": "positionId",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "candidateId",
          "type": "uint256"
        },
        {
          "internalType": "uint256",
          "name": "timestamp",
          "type": "uint256"
        },
        {
          "internalType": "string",
          "name": "candidateName",
          "type": "string"
        },
        {
          "internalType": "string",
          "name": "positionName",
          "type": "string"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    }
  ]';

// Initialize Web3.js via CDN
echo '<script src="https://cdn.jsdelivr.net/npm/web3@1.8.0/dist/web3.min.js"></script>';
echo '<script>';
echo 'async function fetchVoteAnalytics() {';
echo '    try {';
echo '        if (typeof window.web3 === "undefined") {';
echo '            throw new Error("Web3 is not available. Ensure MetaMask or a Web3 provider is installed.");';
echo '        }';

echo '        const web3 = new Web3(window.web3.currentProvider);';
echo '        const contract = new web3.eth.Contract(' . json_encode($abi) . ', "' . $contractAddress . '");';

echo '        // Get votes for the selected election';
echo '        const votes = await contract.methods.getVotesByElection(' . $electionId . ').call();';
echo '        const positions = {};';
echo '        let totalVotes = 0;';

echo '        votes.forEach(vote => {';
echo '            const positionId = vote.positionId.toString();';
echo '            if (!positions[positionId]) {';
echo '                positions[positionId] = {';
echo '                    name: vote.positionName,';
echo '                    candidates: {},';
echo '                    totalVotes: 0';
echo '                };';
echo '            }';
echo '            const candidateId = vote.candidateId.toString();';
echo '            if (!positions[positionId].candidates[candidateId]) {';
echo '                positions[positionId].candidates[candidateId] = {';
echo '                    name: vote.candidateName,';
echo '                    votes: 0';
echo '                };';
echo '            }';
echo '            positions[positionId].candidates[candidateId].votes++;';
echo '            positions[positionId].totalVotes++;';
echo '            totalVotes++;';
echo '        });';

echo '        // Determine winners for each position';
echo '        for (let posId in positions) {';
echo '            let maxVotes = 0;';
echo '            let winner = null;';
echo '            for (let candId in positions[posId].candidates) {';
echo '                if (positions[posId].candidates[candId].votes > maxVotes) {';
echo '                    maxVotes = positions[posId].candidates[candId].votes;';
echo '                    winner = positions[posId].candidates[candId].name;';
echo '                }';
echo '            }';
echo '            positions[posId].winner = winner || "None";';
echo '        }';

echo '        // Prepare response';
echo '        const response = {';
echo '            positions: Object.values(positions),';
echo '            totalVotes: totalVotes';
echo '        };';
echo '        return response;';
echo '    } catch (error) {';
echo '        console.error("Error fetching vote analytics:", error);';
echo '        throw error;';
echo '    }';
echo '}';

echo 'fetchVoteAnalytics().then(data => {';
echo '    document.getElementById("vote-analytics").innerHTML = JSON.stringify(data);'; // For testing, replace with actual rendering logic
echo '}).catch(error => {';
echo '    document.getElementById("vote-analytics").innerHTML = "<p class=\'error\'>Error loading analytics: " + error.message + "</p>";';
echo '});';
echo '</script>';
