-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: localhost    Database: original_db
-- ------------------------------------------------------
-- Server version	8.0.41

--To recover (mysql -u your_username -p original_db < C:\path\to\backup\original_db_backup.sql)

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
  `college` varchar(10) DEFAULT NULL,
  `association` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NULL DEFAULT NULL,
  `role` varchar(30) DEFAULT 'voter',
  `is_active` tinyint(1) DEFAULT '1',
  `fname` varchar(30) DEFAULT NULL,
  `mname` varchar(30) DEFAULT NULL,
  `lname` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`official_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `all_users`
--

LOCK TABLES `all_users` WRITE;
/*!40000 ALTER TABLE `all_users` DISABLE KEYS */;
INSERT INTO `all_users` VALUES ('ADMIN001','yustobitalio24@gmail.com',NULL,'ADMIN','2025-04-06 05:31:53',NULL,'admin',1,'Admin',NULL,'admin'),('T22-03-00002','shadamu96@gmail.com','CIVE','UDOMASA','2025-04-06 05:31:53',NULL,'teacher-voter',1,'sheyla',NULL,'adamu'),('T22-03-06448','ashamkuto2@gmail.com','CIVE','UDOSO','2025-04-06 05:31:53',NULL,'voter',1,'asha',NULL,'mkuto'),('T22-03-06449','student2@udom.ac.tz','COED','UDOSO','2025-04-06 05:31:53',NULL,'voter',1,NULL,NULL,NULL),('T22-03-06450','student3@udom.ac.tz','CNMS','UDOSO','2025-04-06 05:31:53',NULL,'voter',1,NULL,NULL,NULL),('ZU-01','observer@udom.ac.tz',NULL,'none','2025-04-10 09:44:03',NULL,'observer',1,NULL,NULL,NULL);
/*!40000 ALTER TABLE `all_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-14 13:49:44
