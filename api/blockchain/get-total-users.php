<?php
/*
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$college = $_GET['college'] ?? null;
$association = $_GET['association'] ?? null;

if (!$college || !$association) {
    echo json_encode(['error' => 'Missing college or association']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE college = ? AND association = ?");
$stmt->execute([$college, $association]);
$total_users = $stmt->fetchColumn();

echo json_encode(['total_users' => $total_users]);
*/
?>