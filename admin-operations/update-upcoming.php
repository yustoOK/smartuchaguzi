<?php
header('Content-Type: application/json');
include 'db.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$title = $_POST['title'];
$date = $_POST['date'];
$description = $_POST['description'];

$db->query("INSERT INTO elections (title, start_date, description) VALUES ('$title', '$date', '$description')");
$db->query("INSERT INTO audit_log (user_id, action) VALUES ('{$_SESSION['user_id']}', 'Added upcoming election: $title')");

echo json_encode(['success' => true]);
?>