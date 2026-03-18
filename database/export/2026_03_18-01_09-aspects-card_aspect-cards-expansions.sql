-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: localhost    Database: my_swudb
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `aspects`
--

DROP TABLE IF EXISTS `aspects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aspects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `colore` varchar(20) DEFAULT NULL,
  `slug` varchar(50) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aspects_nome_unique` (`nome`),
  UNIQUE KEY `aspects_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aspects`
--

LOCK TABLES `aspects` WRITE;
/*!40000 ALTER TABLE `aspects` DISABLE KEYS */;
INSERT INTO `aspects` VALUES (1,'Vigilanza','#4073d4','vigilanza',0,NULL,NULL),(2,'Autorità','#6faf2f','autorita',0,NULL,NULL),(3,'Aggressione','#d72323','aggressione',0,NULL,NULL),(4,'Astuzia','#f2e82b','astuzia',0,NULL,NULL),(5,'Eroismo','#ffffff','eroismo',0,NULL,NULL),(6,'Malvagità','#000000','malvagita',0,NULL,NULL);
/*!40000 ALTER TABLE `aspects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `card_aspect`
--

DROP TABLE IF EXISTS `card_aspect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `card_aspect` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `card_cid` varchar(15) NOT NULL,
  `aspect_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `card_aspect_card_cid_aspect_id_unique` (`card_cid`,`aspect_id`),
  KEY `card_aspect_aspect_id_foreign` (`aspect_id`),
  CONSTRAINT `card_aspect_aspect_id_foreign` FOREIGN KEY (`aspect_id`) REFERENCES `aspects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `card_aspect_card_cid_foreign` FOREIGN KEY (`card_cid`) REFERENCES `cards` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `card_aspect`
--

LOCK TABLES `card_aspect` WRITE;
/*!40000 ALTER TABLE `card_aspect` DISABLE KEYS */;
/*!40000 ALTER TABLE `card_aspect` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cards`
--

DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `cid` varchar(15) NOT NULL,
  `espansione` varchar(10) NOT NULL,
  `numero` decimal(3,0) NOT NULL,
  `unica` tinyint(1) NOT NULL DEFAULT 0,
  `nome` varchar(100) NOT NULL,
  `titolo` varchar(100) NOT NULL DEFAULT '',
  `tipo` varchar(20) NOT NULL,
  `rarita` varchar(100) NOT NULL,
  `costo` decimal(2,0) NOT NULL DEFAULT 0,
  `vita` decimal(2,0) DEFAULT NULL,
  `potenza` decimal(2,0) DEFAULT NULL,
  `descrizione` longtext NOT NULL,
  `tratti` varchar(100) NOT NULL,
  `arena` varchar(100) DEFAULT NULL,
  `artista` varchar(100) NOT NULL,
  `frontArt` varchar(200) DEFAULT NULL,
  `backArt` varchar(200) DEFAULT NULL,
  `maxCopie` int(11) NOT NULL DEFAULT 3,
  PRIMARY KEY (`espansione`,`numero`),
  UNIQUE KEY `cards_cid_unique` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cards`
--

LOCK TABLES `cards` WRITE;
/*!40000 ALTER TABLE `cards` DISABLE KEYS */;
/*!40000 ALTER TABLE `cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expansions`
--

DROP TABLE IF EXISTS `expansions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expansions` (
  `espansione` varchar(10) NOT NULL,
  `uscita` varchar(65) NOT NULL,
  `rotazione` varchar(1) NOT NULL DEFAULT '0',
  `confermato` tinyint(1) NOT NULL DEFAULT 0,
  `principale` varchar(10) NOT NULL DEFAULT '0' COMMENT 'ID espansione principale del gruppo, 0 se è principale, -1 se è standalone',
  PRIMARY KEY (`espansione`),
  KEY `expansions_principale_index` (`principale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expansions`
--

LOCK TABLES `expansions` WRITE;
/*!40000 ALTER TABLE `expansions` DISABLE KEYS */;
/*!40000 ALTER TABLE `expansions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-18  1:09:55
