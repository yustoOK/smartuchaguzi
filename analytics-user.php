<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Analytics | SmartUchaguzi</title>
    <link rel="icon" href="./Uploads/Vote.jpeg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg'); background-size: cover; color: #2d3748; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { background: rgba(255, 255, 255, 0.95); padding: 30px; border-radius: 12px; width: 90%; max-width: 1200px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15); }
        h2 { font-size: 28px; color: #1a3c34; margin-bottom: 20px; text-align: center; }
        .section { margin-bottom: 30px; }
        .section h3 { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; }
        th { background: #1a3c34; color: #fff; }
        td { background: #fff; }
        .winner { color: #2a9d8f; font-weight: 600; }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            h2 { font-size: 24px; }
            .section h3 { font-size: 18px; }
            th, td { font-size: 14px; padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vote Analytics</h2>
        <div id="analytics-content"></div>

        <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>
        <script>
            async function loadAnalytics() {
                const provider = new ethers.providers.Web3Provider(new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B'));
                // Use signer if write operations are needed; for read-only, provider is sufficient
                const signer = provider.getSigner();
                const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F'; // Replace with deployed address
                const contractABI = [
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
  ];
                const contract = new ethers.Contract(contractAddress, contractABI, provider); // Using provider for read-only

                const response = await fetch('get_elections.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: 'ongoing' })
                });
                const elections = await response.json();

                let content = '';
                for (const election of elections) {
                    content += `<div class='section'><h3>Election: ${election.title}</h3>`;

                    const votes = await contract.getVotesByElection(election.election_id);
                    const voteCounts = {};
                    const candidates = {};

                    for (const vote of votes) {
                        const positionId = vote.positionId.toString();
                        const candidateId = vote.candidateId.toString();
                        const candidateName = vote.candidateName;

                        if (!voteCounts[positionId]) voteCounts[positionId] = {};
                        voteCounts[positionId][candidateId] = (voteCounts[positionId][candidateId] || 0) + 1;
                        candidates[candidateId] = candidateName;
                    }

                    const positionsResponse = await fetch('get_positions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ election_id: election.election_id })
                    });
                    const positions = await positionsResponse.json();

                    for (const position of positions) {
                        content += `<h4>Position: ${position.name}</h4><table><tr><th>Candidate Name</th><th>Vote Count</th></tr>`;
                        let maxVotes = 0;
                        let winnerId = null;

                        if (voteCounts[position.position_id]) {
                            for (const [candidateId, count] of Object.entries(voteCounts[position.position_id])) {
                                content += `<tr><td>${candidates[candidateId]}</td><td>${count}</td></tr>`;
                                if (count > maxVotes) {
                                    maxVotes = count;
                                    winnerId = candidateId;
                                }
                            }
                        } else {
                            content += `<tr><td colspan='2'>No votes recorded yet.</td></tr>`;
                        }
                        content += `</table>`;

                        if (winnerId) {
                            content += `<p class='winner'>Winner: ${candidates[winnerId]} with ${maxVotes} votes</p>`;
                        } else {
                            content += `<p>No winner determined yet.</p>`;
                        }
                    }
                    content += `</div>`;
                }

                document.getElementById('analytics-content').innerHTML = content;
            }

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
                $conn = new mysqli('localhost', 'root', 'Leonida1972@@@@', 'smartuchaguzi_db');
                $stmt = $conn->prepare("SELECT election_id, title FROM elections WHERE status = ? AND end_time > NOW()");
                $stmt->bind_param('s', $_POST['status']);
                $stmt->execute();
                $result = $stmt->get_result();
                $elections = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $conn->close();
                echo "if (false) console.log(); else { const elections = " . json_encode($elections) . "; loadAnalytics(); }";
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['election_id'])) {
                $conn = new mysqli('localhost', 'root', 'Leonida1972@@@@', 'smartuchaguzi_db');
                $stmt = $conn->prepare("SELECT position_id, name FROM electionpositions WHERE election_id = ?");
                $stmt->bind_param('i', $_POST['election_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $positions = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $conn->close();
                echo "if (false) console.log(); else { const positions = " . json_encode($positions) . "; }";
            }
            ?>

            if (window.ethereum) {
                loadAnalytics().catch(console.error);
            } else {
                document.getElementById('analytics-content').innerHTML = '<p>Please install MetaMask to view analytics.</p>';
            }
        </script>
    </div>
</body>
</html>