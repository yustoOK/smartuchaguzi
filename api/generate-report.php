<?php
require_once '../tcpdf/tcpdf.php';
include '../db.php';

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

if (!$election_id) {
    die('Please select an election.');
}

try {
    // Fetch election details
    $stmt = $db->prepare("SELECT association, start_time, end_time FROM elections WHERE id = ?");
    $stmt->bind_param('i', $election_id);
    $stmt->execute();
    $stmt->bind_result($association, $start_time, $end_time);
    $stmt->fetch();
    $election = [
        'association' => $association,
        'start_time' => $start_time,
        'end_time' => $end_time
    ];
    $stmt->close();

    if (!$election) {
        die('Election not found.');
    }

    // Fetch analytics
    $analytics_response = file_get_contents("http://localhost/smartuchaguzi/api/vote-analytics.php?election_id=$election_id");
    $analytics_data = json_decode($analytics_response, true);
    if (isset($analytics_data['error'])) {
        die($analytics_data['error']);
    }
    $positions = $analytics_data['positions'];
    $total_votes = $analytics_data['totalVotes'];

    // Initialize TCPDF
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SmartUchaguzi');
    $pdf->SetTitle('Election Analytics Report');
    $pdf->SetSubject('Election Results');
    $pdf->SetKeywords('Election, Analytics, Votes');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    // Header
    $pdf->SetFillColor(244, 162, 97);
    $pdf->Cell(0, 10, 'Election Analytics Report', 0, 1, 'C', 1);
    $pdf->Ln(5);
    $pdf->Cell(0, 8, "Election: {$election['association']}", 0, 1);
    $pdf->Cell(0, 8, "Period: {$election['start_time']} to {$election['end_time']}", 0, 1);
    $pdf->Cell(0, 8, "Total Votes: $total_votes", 0, 1);
    $pdf->Ln(10);

    // Analytics
    foreach ($positions as $position) {
        $pdf->SetFillColor(42, 157, 143);
        $pdf->Cell(0, 8, $position['name'], 0, 1, 'L', 1);
        $pdf->Cell(0, 8, "Total Votes: {$position['totalVotes']}", 0, 1);
        $pdf->Cell(0, 8, "Winner: " . ($position['winner'] ?: 'None'), 0, 1);
        $pdf->Ln(5);

        $pdf->Cell(80, 8, 'Candidate', 1, 0, 'C');
        $pdf->Cell(40, 8, 'Votes', 1, 0, 'C');
        $pdf->Cell(40, 8, 'Percentage', 1, 1, 'C');

        foreach ($position['candidates'] as $candidate) {
            $percentage = $position['totalVotes'] ? ($candidate['votes'] / $position['totalVotes'] * 100) : 0;
            $pdf->Cell(80, 8, $candidate['name'], 1);
            $pdf->Cell(40, 8, $candidate['votes'], 1, 0, 'C');
            $pdf->Cell(40, 8, number_format($percentage, 2) . '%', 1, 1, 'C');
        }
        $pdf->Ln(10);
    }

    // Output PDF
    $pdf->Output("election_report_{$election_id}.pdf", 'D');
} catch (Exception $e) {
    error_log("Report generation failed: " . $e->getMessage());
    die('Failed to generate report.');
}
?>