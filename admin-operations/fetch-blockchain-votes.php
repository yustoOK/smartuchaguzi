<?php
header('Content-Type: application/json');

try {
    // Input validation
    $election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
    if (!$election_id) {
        echo json_encode(['error' => 'Invalid election ID']);
        exit;
    }
    ?>
    <!-- HTML and JavaScript to fetch votes from the blockchain -->
    <!DOCTYPE html>
    <html>
    <head>
        <script src="https://cdn.ethers.io/lib/ethers-5.7.2.umd.min.js"></script>
    </head>
    <body>
        <script>
            const provider = new ethers.providers.JsonRpcProvider('https://eth-sepolia.g.alchemy.com/v2/1isPc6ojuMcMbyoNNeQkLDGM76n8oT8B');
            const contractAddress = '0x7f37Ea78D22DA910e66F8FdC1640B75dc88fa44F';

            // Fetching ABI and initialize contract
            async function loadContract() {
                try {
                    const response = await fetch('../js/contract-abi.json');
                    if (!response.ok) {
                        throw new Error('Failed to load ABI');
                    }
                    return await response.json();
                } catch (error) {
                    console.error('Error loading ABI:', error);
                    throw error;
                }
            }

            async function fetchVotes() {
                try {
                    const contractABI = await loadContract();
                    const contract = new ethers.Contract(contractAddress, contractABI, provider);
                    const votes = await contract.getVotesByElection(<?php echo $election_id; ?>);

                    // Converting votes to a JSON-serializable format
                    const serializedVotes = votes.map(vote => ({
                        electionId: vote.electionId.toString(),
                        voter: vote.voter,
                        positionId: vote.positionId.toString(),
                        candidateId: vote.candidateId.toString(),
                        timestamp: vote.timestamp.toString(),
                        candidateName: vote.candidateName,
                        positionName: vote.positionName
                    }));

                    // Outputing the result as JSON
                    console.log(JSON.stringify({ votes: serializedVotes }));
                } catch (error) {
                    console.error('Error fetching votes:', error);
                    console.log(JSON.stringify({ error: error.message }));
                }
            }

            fetchVotes();
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>