-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: original_db
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
-- Table structure for table `all_users`
--

DROP TABLE IF EXISTS `all_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `all_users` (
  `official_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `association` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `role` enum('voter','admin','none') DEFAULT 'voter',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `fname` varchar(30) DEFAULT NULL,
  `mname` varchar(30) DEFAULT NULL,
  `lname` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `wallet_address` varchar(50) NOT NULL DEFAULT '',
  `hostel_id` int DEFAULT NULL,
  `college_id` int DEFAULT NULL,
  PRIMARY KEY (`official_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_colleges` (`college_id`),
  KEY `fk_hostels` (`hostel_id`),
  CONSTRAINT `fk_colleges` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hostels` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `all_users`
--

LOCK TABLES `all_users` WRITE;
/*!40000 ALTER TABLE `all_users` DISABLE KEYS */;
INSERT INTO `all_users` VALUES ('T22-03-098765','yustobitalio27@gmail.com','UDOSO','2025-06-17 10:34:41',NULL,'voter',1,'Yusto','Bitalio','Obonyo','',0,'0x1234567890abcdef1234567890abcdef12345678',1,1);
/*!40000 ALTER TABLE `all_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colleges`
--

DROP TABLE IF EXISTS `colleges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `colleges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colleges`
--

LOCK TABLES `colleges` WRITE;
/*!40000 ALTER TABLE `colleges` DISABLE KEYS */;
INSERT INTO `colleges` VALUES (1,'CIVE'),(2,'CoED'),(3,'CNMS');
/*!40000 ALTER TABLE `colleges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hostels`
--

DROP TABLE IF EXISTS `hostels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hostels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `college_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `college_id` (`college_id`),
  CONSTRAINT `hostels_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hostels`
--

LOCK TABLES `hostels` WRITE;
/*!40000 ALTER TABLE `hostels` DISABLE KEYS */;
INSERT INTO `hostels` VALUES (1,'Block 1',1),(2,'Block 2',1),(3,'Block 3',1),(4,'Block 4',1),(5,'Block 5',1),(6,'Block 6',1),(7,'Block 1',2),(8,'Block 2',2),(9,'Block 3',2),(10,'Block 4',2),(11,'Block 5',2),(12,'Block 6',2),(13,'Block 7',2),(14,'Block 8',2),(15,'Block 9',2),(16,'Block 10',2),(17,'Block 11',2),(18,'Block 12',2),(19,'Block 13',2),(20,'Block 14',2),(21,'Block 15',2),(22,'Block 16',2),(23,'Block 17',2),(24,'Block 18',2),(25,'Block 19',2),(26,'Block 20',2),(27,'Block 1',3),(28,'Block 2',3),(29,'Block 3',3),(30,'Block 4',3),(31,'Block 5',3),(32,'Block 6',3),(33,'Block 7',3),(34,'Block 8',3),(35,'Block 9',3),(36,'Block 10',3),(37,'Block 11',3),(38,'Block 12',3),(39,'Block 13',3),(40,'Block 14',3);
/*!40000 ALTER TABLE `hostels` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-27  8:42:08
