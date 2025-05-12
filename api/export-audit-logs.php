<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_logs.csv"');
// Database connection and query similar to audit logs section
$pdo = new PDO("mysql:host=localhost;dbname=smartuchaguzi_db", "root", "Leonida1972@@@@");
$stmt = $pdo->prepare("SELECT a.timestamp, a.action, a.details, a.ip_address, u.fname, u.mname, u.lname 
                       FROM auditlogs a JOIN users u ON a.user_id = u.user_id 
                       ORDER BY a.timestamp DESC");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output = fopen('php://output', 'w');
fputcsv($output, ['Timestamp', 'User', 'Action', 'Details', 'IP Address']);
foreach ($logs as $log) {
    $full_name = $log['fname'] . ' ' . ($log['mname'] ? $log['mname'] . ' ' : '') . $log['lname'];
    fputcsv($output, [
        $log['timestamp'],
        $full_name,
        $log['action'],
        $log['details'] ?? 'N/A',
        $log['ip_address']
    ]);
}
fclose($output);
?>