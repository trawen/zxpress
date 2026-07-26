-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: zxpress_db
-- ------------------------------------------------------
-- Server version	8.0.45

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
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_issue` int NOT NULL DEFAULT '0',
  `title` varchar(2048) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `temp` int NOT NULL DEFAULT '0',
  `date` int NOT NULL DEFAULT '0',
  `views` int NOT NULL DEFAULT '0',
  `number` int NOT NULL DEFAULT '1',
  `title_eng` varchar(1024) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `file` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `dt` int NOT NULL DEFAULT '0',
  `id_press` int NOT NULL DEFAULT '0',
  `text_type` int NOT NULL DEFAULT '0',
  `text_ru` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `text_en` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `temp` (`temp`),
  KEY `id_issue` (`id_issue`),
  KEY `id_press` (`id_press`)
) ENGINE=InnoDB AUTO_INCREMENT=18659 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artifacts`
--

DROP TABLE IF EXISTS `artifacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artifacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('letter','ad','other','') NOT NULL,
  `created` date NOT NULL,
  `caption_en` varchar(255) NOT NULL,
  `caption_ru` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artifacts_img`
--

DROP TABLE IF EXISTS `artifacts_img`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artifacts_img` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artifact_id` int NOT NULL,
  `caption_en` varchar(255) NOT NULL,
  `caption_ru` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `authors_articles`
--

DROP TABLE IF EXISTS `authors_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_author` int NOT NULL,
  `id_article` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog`
--

DROP TABLE IF EXISTS `blog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `books` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title1` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `title2` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `series` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `publisher` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `authors` varchar(512) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `annotation` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `isbn` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `pages` int NOT NULL,
  `city_id` int NOT NULL,
  `date` int NOT NULL,
  `circulation` int NOT NULL,
  `type` int NOT NULL DEFAULT '0',
  `language` int NOT NULL,
  `online` smallint NOT NULL,
  `image_id` int NOT NULL,
  `file_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `books_files`
--

DROP TABLE IF EXISTS `books_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `books_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL,
  `file_name` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `file_type` int NOT NULL,
  `author` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `downloads` int NOT NULL DEFAULT '0',
  `file_size` int NOT NULL,
  `comment` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `upload_date` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar`
--

DROP TABLE IF EXISTS `calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar` (
  `id_cal` int NOT NULL AUTO_INCREMENT,
  `monday_cal` varchar(4) CHARACTER SET cp1251 COLLATE cp1251_bin NOT NULL,
  `press_id_cal` int NOT NULL,
  `year_cal` varchar(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `title_cal` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `number_cal` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `date_cal` int NOT NULL,
  PRIMARY KEY (`id_cal`)
) ENGINE=InnoDB AUTO_INCREMENT=1975 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chapters` (
  `ch_id` int NOT NULL AUTO_INCREMENT,
  `ch_id_book` int NOT NULL,
  `ch_title` varchar(1024) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `ch_date` int NOT NULL,
  `ch_views` int NOT NULL DEFAULT '0',
  `ch_number` int NOT NULL,
  `ch_type` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2432 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `name_eng` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `country_id` int NOT NULL,
  `lat` float NOT NULL,
  `lng` float NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cities2`
--

DROP TABLE IF EXISTS `cities2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities2` (
  `name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_article` int NOT NULL,
  `date` int NOT NULL,
  `nickname` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `text` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=284 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `country_name_eng` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `short` varchar(2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `covers`
--

DROP TABLE IF EXISTS `covers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `covers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_issue` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos`
--

DROP TABLE IF EXISTS `echos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `echo_id` int NOT NULL,
  `subj_id` int NOT NULL,
  `date` int NOT NULL,
  `name_from` varchar(36) NOT NULL,
  `name_to` varchar(36) NOT NULL,
  `text` text NOT NULL,
  `nm` int NOT NULL,
  `tag` varchar(85) NOT NULL,
  `tear` varchar(85) NOT NULL,
  `origin` varchar(85) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `echo_id` (`echo_id`,`subj_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19503 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos2`
--

DROP TABLE IF EXISTS `echos2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `echo_id` int NOT NULL,
  `subj_id` int NOT NULL,
  `date` int NOT NULL,
  `name_from` varchar(36) NOT NULL,
  `name_to` varchar(36) NOT NULL,
  `text` text NOT NULL,
  `nm` int NOT NULL,
  `tag` varchar(85) NOT NULL,
  `tear` varchar(85) NOT NULL,
  `origin` varchar(85) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `echo_id` (`echo_id`,`subj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos_subjs`
--

DROP TABLE IF EXISTS `echos_subjs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos_subjs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `echo_id` int NOT NULL,
  `title` varchar(128) NOT NULL,
  `nm` int NOT NULL,
  `date_from` int NOT NULL,
  `date_to` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3136 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos_subjs2`
--

DROP TABLE IF EXISTS `echos_subjs2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos_subjs2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `echo_id` int NOT NULL,
  `title` varchar(128) NOT NULL,
  `nm` int NOT NULL,
  `date_from` int NOT NULL,
  `date_to` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7158 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos_titles`
--

DROP TABLE IF EXISTS `echos_titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos_titles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(16) NOT NULL,
  `nm` int NOT NULL,
  `date_from` int NOT NULL,
  `date_to` int NOT NULL,
  `url` varchar(16) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos_titles2`
--

DROP TABLE IF EXISTS `echos_titles2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos_titles2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(16) NOT NULL,
  `nm` int NOT NULL,
  `date_from` int NOT NULL,
  `date_to` int NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `echos_zxnet`
--

DROP TABLE IF EXISTS `echos_zxnet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `echos_zxnet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `echo_id` int NOT NULL,
  `subj_id` int NOT NULL,
  `date` int NOT NULL,
  `name_from` varchar(32) NOT NULL,
  `name_to` varchar(32) NOT NULL,
  `text` text NOT NULL,
  `nm` int NOT NULL,
  `tag` varchar(80) NOT NULL,
  `tear` varchar(80) NOT NULL,
  `origin` varchar(80) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32570 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_issue` int NOT NULL,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` int NOT NULL,
  `downloads` int NOT NULL DEFAULT '0',
  `size` int NOT NULL,
  `date` int NOT NULL,
  `delete` tinyint NOT NULL DEFAULT '0',
  `file_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `file_comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2523 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `files_to_news`
--

DROP TABLE IF EXISTS `files_to_news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files_to_news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_news` int NOT NULL,
  `id_news_files` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_news` (`id_news`,`id_news_files`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `illustrations`
--

DROP TABLE IF EXISTS `illustrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `illustrations` (
  `id_il` int NOT NULL AUTO_INCREMENT,
  `id_il_issue` int NOT NULL,
  `il_type` int NOT NULL DEFAULT '0',
  `il_date` int NOT NULL,
  PRIMARY KEY (`id_il`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `indexes`
--

DROP TABLE IF EXISTS `indexes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indexes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL,
  `id1` int NOT NULL,
  `id2` int NOT NULL,
  `name` varchar(64) NOT NULL,
  `date` int NOT NULL,
  `type` int NOT NULL,
  `file` varchar(64) NOT NULL,
  `id3` int NOT NULL,
  `img` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20362 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `issue`
--

DROP TABLE IF EXISTS `issue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `issue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_press` int NOT NULL,
  `title` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` int NOT NULL DEFAULT '0',
  `views` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2651 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `log`
--

DROP TABLE IF EXISTS `log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_issue` int NOT NULL DEFAULT '0',
  `id_article` int NOT NULL DEFAULT '0',
  `id_press` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '0',
  `id_user` int NOT NULL DEFAULT '0',
  `date` int NOT NULL DEFAULT '0',
  `id_screen` int NOT NULL DEFAULT '0',
  `id_cover` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20576 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu`
--

DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent` int NOT NULL,
  `name` varchar(64) NOT NULL,
  `articles` int NOT NULL,
  `childrens` int NOT NULL,
  `order` int NOT NULL DEFAULT '1',
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`)
) ENGINE=InnoDB AUTO_INCREMENT=13452 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_articles`
--

DROP TABLE IF EXISTS `menu_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_menu` int NOT NULL,
  `id_article` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_menu` (`id_menu`,`id_article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `views` int NOT NULL DEFAULT '0',
  `source` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news_files`
--

DROP TABLE IF EXISTS `news_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('image','youtube','sound','') NOT NULL DEFAULT '',
  `downloads` int NOT NULL DEFAULT '0',
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pictures`
--

DROP TABLE IF EXISTS `pictures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pictures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL,
  `type` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `press`
--

DROP TABLE IF EXISTS `press`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `press` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` int NOT NULL,
  `city` int NOT NULL,
  `years_from` int NOT NULL,
  `years_to` int NOT NULL,
  `numbers` int NOT NULL,
  `online_issues` int NOT NULL,
  `online_articles` int NOT NULL,
  `language` int NOT NULL,
  `ex` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=343 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `publishers`
--

DROP TABLE IF EXISTS `publishers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `publishers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `alias` varchar(64) NOT NULL,
  `city_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `city_id` (`city_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubrics`
--

DROP TABLE IF EXISTS `rubrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` int NOT NULL,
  `parent` int NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` int NOT NULL,
  `articles` int NOT NULL,
  `childrens` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`)
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rubrics_articles`
--

DROP TABLE IF EXISTS `rubrics_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubrics_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_rubrics` int NOT NULL,
  `id_article` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_rubrics` (`id_rubrics`,`id_article`)
) ENGINE=InnoDB AUTO_INCREMENT=2168 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `screens`
--

DROP TABLE IF EXISTS `screens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `screens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_issue` int NOT NULL,
  `id_press` int NOT NULL,
  `type` int NOT NULL DEFAULT '0',
  `date` int NOT NULL,
  `format` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_press` (`id_press`)
) ENGINE=InnoDB AUTO_INCREMENT=2469 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `search`
--

DROP TABLE IF EXISTS `search`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `text` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `date` int NOT NULL,
  `type` int NOT NULL,
  `article_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1100295 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tag`
--

DROP TABLE IF EXISTS `tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(16) NOT NULL,
  `temp` tinyint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(24) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `tag_alias` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `show` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags_articles`
--

DROP TABLE IF EXISTS `tags_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_tag` int NOT NULL,
  `id_article` int NOT NULL,
  `tag_size` int NOT NULL DEFAULT '0',
  `tag_type` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11394 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags_relations`
--

DROP TABLE IF EXISTS `tags_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags_relations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_tag_1` int NOT NULL,
  `id_tag_2` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `date` int NOT NULL,
  `level` int DEFAULT NULL,
  `ip` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `activation` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-20 12:27:24
