<?php
session_start();
require_once '../tcpdf/tcpdf.php';
require_once '../config.php';

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token validation failed');
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    if (!$election_id) {
        die('Invalid election ID');
    }

    $stmt = $conn->prepare("SELECT association, start_time FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$election) {
        die('Election not found');
    }

    // Fetch vote data with error handling
    $voteUrl = "http://localhost/smartuchaguzi/api/fetch-blockchain-votes.php?election_id=$election_id";
    $voteResponse = @file_get_contents($voteUrl); // Suppress warnings
    if ($voteResponse === false) {
        die('Failed to fetch vote data: URL not found or server error');
    }

    $voteData = json_decode($voteResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($voteData) || !isset($voteData['votes'])) {
        die('Error decoding vote data or invalid response format');
    }
    $votes = $voteData['votes'];

    $positionsMap = [];
    if (is_array($votes)) {
        foreach ($votes as $vote) {
            $positionId = $vote['positionId'] ?? '';
            $candidateId = $vote['candidateId'] ?? '';
            $candidateName = $vote['candidateName'] ?? 'Unknown';
            $positionName = $vote['positionName'] ?? 'Unknown';

            if (!isset($positionsMap[$positionId])) {
                $positionsMap[$positionId] = [
                    'name' => $positionName,
                    'candidates' => []
                ];
            }
            if (!isset($positionsMap[$positionId]['candidates'][$candidateId])) {
                $positionsMap[$positionId]['candidates'][$candidateId] = [
                    'name' => $candidateName,
                    'votes' => 0
                ];
            }
            $positionsMap[$positionId]['candidates'][$candidateId]['votes']++;
        }
    }

    // Create PDF
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SmartUchaguzi');
    $pdf->SetTitle('Election Analytics Report');
    $pdf->SetSubject('Vote Analytics');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Election Analytics Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Election: {$election['association']} - {$election['start_time']}", 0, 1, 'C');
    $pdf->Ln(10);

    foreach ($positionsMap as $positionId => $pos) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, $pos['name'], 0, 1);
        $pdf->SetFont('helvetica', '', 12);

        $totalVotes = array_sum(array_column($pos['candidates'], 'votes'));
        $winner = array_reduce($pos['candidates'], fn($a, $b) => $a['votes'] > $b['votes'] ? $a : $b, ['votes' => 0, 'name' => 'None']);

        $pdf->Cell(0, 10, "Total Votes: $totalVotes", 0, 1);
        $pdf->Cell(0, 10, "Winner: {$winner['name']} ({$winner['votes']} votes)", 0, 1);
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(100, 10, 'Candidate', 1, 0, 'C');
        $pdf->Cell(80, 10, 'Votes', 1, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);

        foreach ($pos['candidates'] as $candidate) {
            $pdf->Cell(100, 10, $candidate['name'], 1, 0);
            $pdf->Cell(80, 10, $candidate['votes'], 1, 1, 'C');
        }
        $pdf->Ln(10);
    }

    $pdf->Output('Analytics_Report.pdf', 'D');
} catch (Exception $e) {
    die('Error generating report: ' . $e->getMessage());
}
?>