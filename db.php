<?php
$server = 'localhost';
$user = 'root';
$password = 'Leonida1972@@@@';
$database = 'smartuchaguzi_db';

$db = new mysqli($server, $user, $password, $database);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$db->set_charset("utf8mb4");
?>