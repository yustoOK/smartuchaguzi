<?php
   header('Content-Type: application/json');

   $host = 'localhost';
   $dbname = 'smartuchaguzi_db';
   $username = 'root';
   $password = 'Leonida1972@@@@';

   try {
       $conn = new mysqli($host, $username, $password, $dbname);
       if ($conn->connect_error) {
           throw new Exception("Database connection failed: " . $conn->connect_error);
       }

       $data = json_decode(file_get_contents('php://input'), true);
       $election_id = $data['election_id'];
       $hash = $data['hash'];
       $data_str = $data['data'];
       $voter = $data['voter'];
       $position_id = $data['position_id'];
       $candidate_id = $data['candidate_id'];
       $timestamp = date('Y-m-d H:i:s', $data['timestamp']);

       $stmt = $conn->prepare(
           "INSERT INTO blockchainrecords (election_id, hash, data, voter, position_id, candidate_id, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?)"
       );
       $stmt->bind_param("issssii", $election_id, $hash, $data_str, $voter, $position_id, $candidate_id, $timestamp);
       $stmt->execute();
       $stmt->close();

       echo json_encode(['success' => true]);
   } catch (Exception $e) {
       echo json_encode(['success' => false, 'message' => $e->getMessage()]);
   }

   $conn->close();
   ?>