-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: smartuchaguzi_db
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auditlogs`
--

DROP TABLE IF EXISTS `auditlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditlogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditlogs_user_id` (`user_id`),
  CONSTRAINT `auditlogs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `auditlogs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditlogs`
--

LOCK TABLES `auditlogs` WRITE;
/*!40000 ALTER TABLE `auditlogs` DISABLE KEYS */;
INSERT INTO `auditlogs` VALUES (1,1,'User logged in: yustobitalio24@gmail.com',NULL,NULL,'2025-04-13 07:03:20'),(2,2,'User logged in: ashamkuto2@gmail.com',NULL,NULL,'2025-04-13 07:10:03'),(3,2,'User logged in: ashamkuto2@gmail.com',NULL,NULL,'2025-04-13 07:42:37'),(4,1,'User logged in: yustobitalio24@gmail.com',NULL,NULL,'2025-04-13 07:54:34'),(5,1,'User logged in: yustobitalio24@gmail.com',NULL,NULL,'2025-04-13 08:06:08'),(6,1,'User logged in: yustobitalio24@gmail.com',NULL,NULL,'2025-04-13 08:28:58'),(7,2,'User logged in: ashamkuto2@gmail.com',NULL,NULL,'2025-04-13 08:29:18');
/*!40000 ALTER TABLE `auditlogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blockchainrecords`
--

DROP TABLE IF EXISTS `blockchainrecords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blockchainrecords` (
  `block_id` int NOT NULL AUTO_INCREMENT,
  `vote_id` int DEFAULT NULL,
  `election_id` int DEFAULT NULL,
  `hash` varchar(255) NOT NULL,
  `previous_hash` varchar(255) DEFAULT NULL,
  `data` text NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`),
  KEY `fk_block_vote` (`vote_id`),
  KEY `fk_block_election` (`election_id`),
  CONSTRAINT `blockchainrecords_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  CONSTRAINT `fk_block_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  CONSTRAINT `fk_block_vote` FOREIGN KEY (`vote_id`) REFERENCES `votes` (`vote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blockchainrecords`
--

LOCK TABLES `blockchainrecords` WRITE;
/*!40000 ALTER TABLE `blockchainrecords` DISABLE KEYS */;
/*!40000 ALTER TABLE `blockchainrecords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidates` (
  `id` int NOT NULL,
  `official_id` varchar(30) DEFAULT NULL,
  `election_id` int DEFAULT NULL,
  `firstname` varchar(30) NOT NULL,
  `midname` varchar(30) DEFAULT NULL,
  `lastname` varchar(30) NOT NULL,
  `association` varchar(40) DEFAULT NULL,
  `college` varchar(50) DEFAULT NULL,
  `position_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `official_id` (`official_id`),
  KEY `election_id` (`election_id`),
  CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`official_id`) REFERENCES `users` (`official_id`),
  CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  CONSTRAINT `fk_elections` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users` FOREIGN KEY (`official_id`) REFERENCES `users` (`official_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colleges`
--

DROP TABLE IF EXISTS `colleges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `colleges` (
  `college_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`college_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colleges`
--

LOCK TABLES `colleges` WRITE;
/*!40000 ALTER TABLE `colleges` DISABLE KEYS */;
INSERT INTO `colleges` VALUES (1,'College of Informatics and Virtual Education'),(2,'College of Education'),(3,'College Mathematics and Natural Science');
/*!40000 ALTER TABLE `colleges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contactmessages`
--

DROP TABLE IF EXISTS `contactmessages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactmessages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `user_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','responded') DEFAULT 'unread',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contactmessages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contactmessages`
--

LOCK TABLES `contactmessages` WRITE;
/*!40000 ALTER TABLE `contactmessages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contactmessages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `electionpositions`
--

DROP TABLE IF EXISTS `electionpositions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `electionpositions` (
  `position_id` int NOT NULL AUTO_INCREMENT,
  `election_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `hostel_id` int DEFAULT NULL,
  PRIMARY KEY (`position_id`),
  KEY `fk_positions_hostel` (`hostel_id`),
  CONSTRAINT `fk_positions_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`hostel_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `electionpositions`
--

LOCK TABLES `electionpositions` WRITE;
/*!40000 ALTER TABLE `electionpositions` DISABLE KEYS */;
/*!40000 ALTER TABLE `electionpositions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `electionresults`
--

DROP TABLE IF EXISTS `electionresults`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `electionresults` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `election_id` int NOT NULL,
  `candidate_id` int NOT NULL,
  `position_id` int NOT NULL,
  `vote_count` int NOT NULL,
  `calculated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`result_id`),
  KEY `election_id` (`election_id`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `electionresults_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  CONSTRAINT `electionresults_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `electionpositions` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `electionresults`
--

LOCK TABLES `electionresults` WRITE;
/*!40000 ALTER TABLE `electionresults` DISABLE KEYS */;
/*!40000 ALTER TABLE `electionresults` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `elections` (
  `election_id` int NOT NULL AUTO_INCREMENT,
  `association` varchar(10) NOT NULL,
  `college` varchar(10) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('upcoming','ongoing','completed') DEFAULT 'upcoming',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `title` varchar(500) DEFAULT NULL,
  `description` text,
  `blockchain_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`election_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elections`
--

LOCK TABLES `elections` WRITE;
/*!40000 ALTER TABLE `elections` DISABLE KEYS */;
/*!40000 ALTER TABLE `elections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frauddetectionlogs`
--

DROP TABLE IF EXISTS `frauddetectionlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frauddetectionlogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `election_id` int DEFAULT NULL,
  `description` text NOT NULL,
  `action_taken` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `model_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `election_id` (`election_id`),
  KEY `idx_frauddetectionlogs_user_id` (`user_id`),
  KEY `model_id` (`model_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_4` FOREIGN KEY (`model_id`) REFERENCES `neuralnetworkmodels` (`model_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_5` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `frauddetectionlogs_ibfk_6` FOREIGN KEY (`model_id`) REFERENCES `neuralnetworkmodels` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frauddetectionlogs`
--

LOCK TABLES `frauddetectionlogs` WRITE;
/*!40000 ALTER TABLE `frauddetectionlogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `frauddetectionlogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hostels`
--

DROP TABLE IF EXISTS `hostels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hostels` (
  `hostel_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `college_id` int DEFAULT NULL,
  PRIMARY KEY (`hostel_id`),
  KEY `college_id` (`college_id`),
  CONSTRAINT `hostels_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostels`
--

LOCK TABLES `hostels` WRITE;
/*!40000 ALTER TABLE `hostels` DISABLE KEYS */;
INSERT INTO `hostels` VALUES (1,'Hostel 1',1),(2,'Hostel 2',1),(3,'Hostel 3',1),(4,'Hostel 4',1),(5,'Hostel 5',1),(6,'Hostel 6',1),(7,'Hostel 1',2),(8,'Hostel 2',2),(9,'Hostel 3',2),(10,'Hostel 4',2),(11,'Hostel 5',2),(12,'Hostel 6',2),(13,'Hostel 7',2),(14,'Hostel 8',2),(15,'Hostel 9',2),(16,'Hostel 10',2),(17,'Hostel 1',3),(18,'Hostel 2',3),(19,'Hostel 3',3),(20,'Hostel 4',3),(21,'Hostel 5',3),(22,'Hostel 6',3),(23,'Hostel 7',3),(24,'Hostel 8',3),(25,'Hostel 9',3),(26,'Hostel 10',3),(27,'Hostel 11',3),(28,'Hostel 12',3),(29,'Hostel 13',3),(30,'Hostel 14',3),(31,'Hostel 15',3),(32,'Hostel 16',3),(33,'Hostel 17',3),(34,'Hostel 18',3),(35,'Hostel 19',3),(36,'Hostel 20',3);
/*!40000 ALTER TABLE `hostels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `neuralnetworkmodels`
--

DROP TABLE IF EXISTS `neuralnetworkmodels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `neuralnetworkmodels` (
  `model_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `version` varchar(50) NOT NULL,
  `parameters` text,
  `accuracy` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`model_id`),
  UNIQUE KEY `name` (`name`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `neuralnetworkmodels`
--

LOCK TABLES `neuralnetworkmodels` WRITE;
/*!40000 ALTER TABLE `neuralnetworkmodels` DISABLE KEYS */;
/*!40000 ALTER TABLE `neuralnetworkmodels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `type` enum('email','in_app') NOT NULL,
  `status` enum('sent','pending','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`token`),
  KEY `email` (`email`),
  KEY `fk_passwordreset_user` (`user_id`),
  CONSTRAINT `fk_passwordreset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES ('yustobitalio24@gmail.com','541ecbaf9adcb1e890a08b539d996d1f','2025-04-13 12:31:02',0),('yustobitalio24@gmail.com','88e52898649c0a2162b53dae0d143739','2025-04-13 12:36:47',0),('yustobitalio24@gmail.com','b83877ce21f37132aaa21ada161e7b97','2025-04-13 12:36:15',0);
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `session_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `login_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`session_id`),
  KEY `idx_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userdatarequests`
--

DROP TABLE IF EXISTS `userdatarequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `userdatarequests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `request_type` enum('access','correction','deletion') NOT NULL,
  `status` enum('pending','processed','denied') DEFAULT 'pending',
  `details` text,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `fk_userdata_user` (`user_id`),
  CONSTRAINT `fk_userdata_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `userdatarequests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userdatarequests`
--

LOCK TABLES `userdatarequests` WRITE;
/*!40000 ALTER TABLE `userdatarequests` DISABLE KEYS */;
/*!40000 ALTER TABLE `userdatarequests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `official_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `college` varchar(10) DEFAULT NULL,
  `association` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('voter','observer','admin','none') DEFAULT 'voter',
  `fname` varchar(30) DEFAULT NULL,
  `mname` varchar(30) DEFAULT NULL,
  `lname` varchar(30) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `privacy_consent` tinyint(1) DEFAULT '0',
  `consent_timestamp` timestamp NULL DEFAULT NULL,
  `college_id` int DEFAULT NULL,
  `hostel_id` int DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `official_id` (`official_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_college` (`college_id`),
  KEY `fk_user_hostel` (`hostel_id`),
  CONSTRAINT `fk_user_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_user_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`hostel_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`official_id`) REFERENCES `original_db`.`all_users` (`official_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ADMIN001','yustobitalio24@gmail.com',NULL,'ADMIN','$2y$10$93rKrfmKSYHcLGAEUFVbz.qqgOGku2xWbr4Tb7zHVqwkwNPo4g.4q',1,NULL,'2025-04-10 11:47:55','admin','Admin',NULL,'Admin',NULL,0,NULL,NULL,NULL),(2,'T22-03-06448','ashamkuto2@gmail.com','CIVE','UDOSO','$2y$10$Bq3mxjgYrklAEtlRIS6ja.ONrl38Bhykw0M1efQR3VHZTp3ymco4K',1,NULL,'2025-04-13 07:04:41','voter','asha',NULL,'mkuto',NULL,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `votes` (
  `vote_id` int NOT NULL AUTO_INCREMENT,
  `election_id` int NOT NULL,
  `user_id` int NOT NULL,
  `candidate_id` int NOT NULL,
  `vote_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `blockchain_hash` varchar(255) NOT NULL,
  `is_anonymized` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`vote_id`),
  KEY `idx_votes_user_id` (`user_id`),
  KEY `idx_votes_election_id` (`election_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-16 15:57:46
