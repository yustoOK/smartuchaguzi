<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("Session or CSRF validation failed: " . print_r($_SESSION, true) . ", GET: " . print_r($_GET, true));
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('Session validation failed.'));
    exit;
}

$role = $_GET['role'] ?? null;
$college_id = $_GET['college_id'] ?? null;
$association = $_GET['association'] ?? null;
$csrf_token = $_GET['csrf_token'] ?? $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Login Verification</title>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
</head>
<body>
    <h2>Please Connect Your MetaMask Wallet</h2>
    <p>Detecting active wallet address...</p>

    <script>
        async function getWalletAddress() {
            try {
                if (typeof window.ethereum !== 'undefined') {
                    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                    const walletAddress = accounts[0];

                    console.log('Detected Wallet Address:', walletAddress);
                    alert('Detected Wallet Address: ' + walletAddress);

                    await updateSessionWalletAddress(walletAddress);
                } else {
                    console.error('MetaMask is not installed!');
                    alert('Please install MetaMask to proceed.');
                    window.location.href = 'login.php?error=' + encodeURIComponent('MetaMask not detected.');
                }
            } catch (error) {
                console.error('Error getting wallet address:', error);
                alert('Error connecting to MetaMask: ' + error.message);
                window.location.href = 'login.php?error=' + encodeURIComponent('Failed to connect to MetaMask.');
            }
        }

        async function updateSessionWalletAddress(walletAddress) {
            try {
                const response = await fetch('update-wallet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'wallet_address=' + encodeURIComponent(walletAddress) + '&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>'
                });
                const result = await response.json();
                if (result.success) {
                    console.log('Session wallet address updated to:', walletAddress);
                    window.location.href = getDashboardUrl();
                } else {
                    console.error('Failed to update session wallet address:', result.error);
                    alert('Failed to update session: ' + result.error);
                    window.location.href = 'login.php?error=' + encodeURIComponent('Session update failed.');
                }
            } catch (error) {
                console.error('Error updating session wallet address:', error);
                alert('Error updating session: ' + error.message);
                window.location.href = 'login.php?error=' + encodeURIComponent('Session update error.');
            }
        }

        function getDashboardUrl() {
            const role = '<?php echo htmlspecialchars($role); ?>';
            const collegeId = '<?php echo htmlspecialchars($college_id); ?>';
            const association = '<?php echo htmlspecialchars($association); ?>';
            let url = 'login.php?error=' + encodeURIComponent('Invalid role or association');

            if (role === 'admin') {
                url = 'admin-dashboard.php';
            } else if (role === 'voter') {
                if (association === 'UDOSO') {
                    if (collegeId === '1') url = 'cive-students.php';
                    else if (collegeId === '3') url = 'cnms-students.php';
                    else if (collegeId === '2') url = 'coed-students.php';
                } else if (association === 'UDOMASA') {
                    if (collegeId === '1') url = 'cive-teachers.php';
                    else if (collegeId === '3') url = 'cnms-teachers.php';
                    else if (collegeId === '2') url = 'coed-teachers.php';
                }
            }
            return url + '?csrf_token=<?php echo htmlspecialchars($csrf_token); ?>';
        }
        
        window.addEventListener('load', getWalletAddress);
    </script>
</body>
</html>