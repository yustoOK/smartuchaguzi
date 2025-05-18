<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
    if (!$election_id) {
        echo json_encode(['error' => 'Invalid election ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT c.id, c.firstname, c.lastname, c.pair_id, ep.position_id, ep.name AS position_name 
                           FROM candidates c 
                           JOIN electionpositions ep ON c.position_id = ep.position_id 
                           WHERE c.election_id = ?");
    $stmt->execute([$election_id]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($candidates);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>