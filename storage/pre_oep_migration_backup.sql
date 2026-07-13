-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: proctad_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `proctad_non_exam_personnel`
--

DROP TABLE IF EXISTS `proctad_non_exam_personnel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proctad_non_exam_personnel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nep_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `agency` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `personnel_type` varchar(30) NOT NULL,
  `field_office_id` bigint(20) unsigned DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proctad_non_exam_personnel_nep_id_unique` (`nep_id`),
  KEY `proctad_non_exam_personnel_field_office_id_foreign` (`field_office_id`),
  KEY `proctad_non_exam_personnel_created_by_foreign` (`created_by`),
  KEY `proctad_non_exam_personnel_personnel_type_index` (`personnel_type`),
  KEY `proctad_non_exam_personnel_is_active_index` (`is_active`),
  CONSTRAINT `proctad_non_exam_personnel_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proctad_non_exam_personnel_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proctad_non_exam_personnel`
--

LOCK TABLES `proctad_non_exam_personnel` WRITE;
/*!40000 ALTER TABLE `proctad_non_exam_personnel` DISABLE KEYS */;
INSERT INTO `proctad_non_exam_personnel` VALUES (1,'NEP-CSCRO8-NHXH4R','CHRISTOPHE',NULL,'GAYLORD',NULL,'male','09128538454',NULL,NULL,NULL,'inspector',2,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(2,'NEP-CSCRO8-AVYV2T','KYLIE',NULL,'PROSACCO',NULL,'female','09743601420',NULL,NULL,NULL,'paymaster',2,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(3,'NEP-CSCRO8-K5PVNK','ISMAEL',NULL,'BLOCK',NULL,'male','09331510522',NULL,NULL,NULL,'pnp_officer',3,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(4,'NEP-CSCRO8-2TQLWK','DAGMAR',NULL,'SCHMIDT',NULL,'female','09650495953',NULL,NULL,NULL,'security_officer',3,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(5,'NEP-CSCRO8-NFLJFG','LUCIO',NULL,'FRANECKI',NULL,'male','09634248167',NULL,NULL,NULL,'janitor',4,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(6,'NEP-CSCRO8-K8KD77','ANGIE',NULL,'FRAMI',NULL,'female','09252121351',NULL,NULL,NULL,'helper',4,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(7,'NEP-CSCRO8-ZL8CGY','ROSALYN',NULL,'REMPEL',NULL,'female','09420414659',NULL,NULL,NULL,'driver',5,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(8,'NEP-CSCRO8-2ZZTL4','HERTA',NULL,'STARK',NULL,'male','09034184459',NULL,NULL,NULL,'coordinator',5,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(9,'NEP-CSCRO8-E8U3DE','JASON',NULL,'HAMILL',NULL,'male','09516805590',NULL,NULL,NULL,'inspector',6,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(10,'NEP-CSCRO8-2Q4PYJ','ELISSA',NULL,'WEIMANN',NULL,'female','09900362689',NULL,NULL,NULL,'paymaster',6,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(11,'NEP-CSCRO8-XPSKJK','NAT',NULL,'VEUM',NULL,'male','09114793841',NULL,NULL,NULL,'pnp_officer',7,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02'),(12,'NEP-CSCRO8-RH265S','VIVA',NULL,'GAYLORD',NULL,'female','09162079977',NULL,NULL,NULL,'security_officer',7,NULL,1,NULL,NULL,'2026-07-10 08:47:08','2026-07-10 19:17:02');
/*!40000 ALTER TABLE `proctad_non_exam_personnel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proctad_nep_assignments`
--

DROP TABLE IF EXISTS `proctad_nep_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proctad_nep_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `non_exam_personnel_id` bigint(20) unsigned NOT NULL,
  `examination_school_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'confirmed',
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nep_assignments_nep_venue_unique` (`non_exam_personnel_id`,`examination_school_id`),
  KEY `proctad_nep_assignments_examination_school_id_foreign` (`examination_school_id`),
  KEY `proctad_nep_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `proctad_nep_assignments_status_index` (`status`),
  CONSTRAINT `proctad_nep_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proctad_nep_assignments_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proctad_nep_assignments_non_exam_personnel_id_foreign` FOREIGN KEY (`non_exam_personnel_id`) REFERENCES `proctad_non_exam_personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proctad_nep_assignments`
--

LOCK TABLES `proctad_nep_assignments` WRITE;
/*!40000 ALTER TABLE `proctad_nep_assignments` DISABLE KEYS */;
INSERT INTO `proctad_nep_assignments` VALUES (1,1,1,'confirmed',NULL,'2026-07-10 08:47:08','2026-07-10 08:47:08'),(2,2,2,'confirmed',NULL,'2026-07-10 08:47:08','2026-07-10 08:47:08'),(3,3,1,'confirmed',NULL,'2026-07-10 08:47:08','2026-07-10 08:47:08'),(4,4,2,'confirmed',NULL,'2026-07-10 08:47:08','2026-07-10 08:47:08');
/*!40000 ALTER TABLE `proctad_nep_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proctad_nep_attendances`
--

DROP TABLE IF EXISTS `proctad_nep_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proctad_nep_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `non_exam_personnel_id` bigint(20) unsigned NOT NULL,
  `examination_school_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `scan_method` varchar(10) NOT NULL DEFAULT 'qr',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanned_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nep_attendances_nep_venue_unique` (`non_exam_personnel_id`,`examination_school_id`),
  KEY `proctad_nep_attendances_examination_school_id_foreign` (`examination_school_id`),
  KEY `proctad_nep_attendances_scanned_by_foreign` (`scanned_by`),
  CONSTRAINT `proctad_nep_attendances_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proctad_nep_attendances_non_exam_personnel_id_foreign` FOREIGN KEY (`non_exam_personnel_id`) REFERENCES `proctad_non_exam_personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proctad_nep_attendances_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proctad_nep_attendances`
--

LOCK TABLES `proctad_nep_attendances` WRITE;
/*!40000 ALTER TABLE `proctad_nep_attendances` DISABLE KEYS */;
INSERT INTO `proctad_nep_attendances` VALUES (1,1,1,'present','manual','2026-03-14 22:15:00',NULL),(2,2,2,'present','manual','2026-03-14 22:15:00',NULL);
/*!40000 ALTER TABLE `proctad_nep_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `other_examination_personnel`
--

DROP TABLE IF EXISTS `other_examination_personnel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `other_examination_personnel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `oep_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `agency` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `personnel_type` varchar(30) NOT NULL,
  `field_office_id` bigint(20) unsigned DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `other_examination_personnel`
--

LOCK TABLES `other_examination_personnel` WRITE;
/*!40000 ALTER TABLE `other_examination_personnel` DISABLE KEYS */;
/*!40000 ALTER TABLE `other_examination_personnel` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-12 19:21:52
