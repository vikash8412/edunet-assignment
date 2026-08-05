/*M!999999\- enable the sandbox mode */ 

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
DROP TABLE IF EXISTS `ai_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_generations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `form_id` bigint(20) unsigned DEFAULT NULL,
  `mode` varchar(10) NOT NULL,
  `prompt` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'queued',
  `model` varchar(60) DEFAULT NULL,
  `prompt_tokens` int(10) unsigned DEFAULT NULL,
  `completion_tokens` int(10) unsigned DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `error` text DEFAULT NULL,
  `result_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_schema`)),
  `warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`warnings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_generations_form_id_foreign` (`form_id`),
  KEY `ai_generations_user_id_status_id_index` (`user_id`,`status`,`id`),
  CONSTRAINT `ai_generations_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_generations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_generations` WRITE;
/*!40000 ALTER TABLE `ai_generations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_generations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `form_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `session_hash` varchar(64) NOT NULL,
  `type` varchar(10) NOT NULL,
  `step` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `form_events_form_id_type_created_at_index` (`form_id`,`type`,`created_at`),
  KEY `form_events_form_id_session_hash_type_index` (`form_id`,`session_hash`,`type`),
  CONSTRAINT `form_events_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `form_events` WRITE;
/*!40000 ALTER TABLE `form_events` DISABLE KEYS */;
INSERT INTO `form_events` VALUES
(1,1,'8efd6168a055d1e45863c237eb3feab2be94f10b7ccdc5bf8bb54305c8b19f7f','view',NULL,'2026-07-31 02:39:19'),
(2,1,'8efd6168a055d1e45863c237eb3feab2be94f10b7ccdc5bf8bb54305c8b19f7f','start',NULL,'2026-07-31 02:39:19'),
(3,1,'8efd6168a055d1e45863c237eb3feab2be94f10b7ccdc5bf8bb54305c8b19f7f','submit',NULL,'2026-07-31 02:39:19'),
(4,1,'0eb026731d9ea3f870511f8c18daeb814eaa2c9e276082b204f2a962212fb5bd','view',NULL,'2026-08-01 02:39:19'),
(5,1,'0eb026731d9ea3f870511f8c18daeb814eaa2c9e276082b204f2a962212fb5bd','start',NULL,'2026-08-01 02:39:19'),
(6,1,'0eb026731d9ea3f870511f8c18daeb814eaa2c9e276082b204f2a962212fb5bd','submit',NULL,'2026-08-01 02:39:19'),
(7,1,'532b20b0105c9883348558ed2711c7d23ea6b4ee718364a87e16fad4b3c3a029','view',NULL,'2026-08-02 02:39:19'),
(8,1,'532b20b0105c9883348558ed2711c7d23ea6b4ee718364a87e16fad4b3c3a029','start',NULL,'2026-08-02 02:39:19'),
(9,1,'532b20b0105c9883348558ed2711c7d23ea6b4ee718364a87e16fad4b3c3a029','submit',NULL,'2026-08-02 02:39:19'),
(10,1,'06a8db106a32a00f305948a18f7c301fe27f780eb07fa61b5e664d51c1011718','view',NULL,'2026-08-03 02:39:19'),
(11,1,'06a8db106a32a00f305948a18f7c301fe27f780eb07fa61b5e664d51c1011718','start',NULL,'2026-08-03 02:39:19'),
(12,1,'06a8db106a32a00f305948a18f7c301fe27f780eb07fa61b5e664d51c1011718','submit',NULL,'2026-08-03 02:39:19'),
(13,1,'20e98ed255fc5b1683eecfde5e917384d22f99efe335ce172d2032394cfc35ba','view',NULL,'2026-08-04 02:39:19'),
(14,1,'20e98ed255fc5b1683eecfde5e917384d22f99efe335ce172d2032394cfc35ba','start',NULL,'2026-08-04 02:39:19'),
(15,1,'20e98ed255fc5b1683eecfde5e917384d22f99efe335ce172d2032394cfc35ba','submit',NULL,'2026-08-04 02:39:19'),
(16,1,'229b304dd7b58b1ef8f7254104ec75a4284799a76ad86d5cd889624cc7a3ea9c','view',NULL,'2026-08-05 02:39:19'),
(17,1,'4a6e27af43622d7ea460cd72b3830ea8bdc49177edabd9f31bac06b205c627ee','view',NULL,'2026-08-04 02:39:19'),
(18,1,'cdfe64e651545236ab84623dee3f5f49467ec413eeb22d615f0a4ad0080464c3','view',NULL,'2026-08-03 02:39:19'),
(19,1,'189dc893d1c1fdf905ba3c23193ab507141498033bfbfa7b78f783ed39b3f897','view',NULL,'2026-08-02 02:39:19'),
(20,1,'9854c3747a0224c5cf54e8357c3cce62eec9e390ebcef758735f239538ed5964','view',NULL,'2026-08-01 02:39:19'),
(21,1,'f8286db0ec97a960b6c1cb0558d705ed25604b90aa155f241209b3672d84ec90','view',NULL,'2026-08-05 02:39:19'),
(22,1,'f8286db0ec97a960b6c1cb0558d705ed25604b90aa155f241209b3672d84ec90','start',NULL,'2026-08-05 02:39:19'),
(23,1,'3fd007f901c41a1fd20637e287bcb6bcdb31e9ac331e4ab75c3cc10547a3bdb6','view',NULL,'2026-08-04 02:39:19'),
(24,1,'3fd007f901c41a1fd20637e287bcb6bcdb31e9ac331e4ab75c3cc10547a3bdb6','start',NULL,'2026-08-04 02:39:19'),
(25,1,'59bf708f0c1648277cbb6f2dca0755d2d4a84afa491302cb729f603f51f4e78b','view',NULL,'2026-08-03 02:39:19'),
(26,1,'59bf708f0c1648277cbb6f2dca0755d2d4a84afa491302cb729f603f51f4e78b','start',NULL,'2026-08-03 02:39:19'),
(27,1,'8913fb91c796e3f56172ecb73fee2fcdf5a13f27f2c89ce1d83083169e3afa0c','view',NULL,'2026-08-02 02:39:19'),
(28,1,'8913fb91c796e3f56172ecb73fee2fcdf5a13f27f2c89ce1d83083169e3afa0c','start',NULL,'2026-08-02 02:39:19');
/*!40000 ALTER TABLE `form_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `form_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schema`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'builder',
  `label` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_versions_form_id_version_unique` (`form_id`,`version`),
  KEY `form_versions_created_by_foreign` (`created_by`),
  CONSTRAINT `form_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `form_versions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `form_versions` WRITE;
/*!40000 ALTER TABLE `form_versions` DISABLE KEYS */;
INSERT INTO `form_versions` VALUES
(1,1,1,'{\"title\":\"Customer Feedback Survey\",\"description\":\"Tell us how we did \\u2014 it takes less than two minutes.\",\"settings\":{\"multi_step\":false,\"success_message\":\"Thanks for the feedback!\",\"submit_label\":\"Send feedback\",\"max_per_day\":null},\"sections\":[{\"id\":\"sec_ORmV5Rf9\",\"title\":\"Your experience\",\"description\":null,\"fields\":[{\"id\":\"fld_C1JGHiln\",\"type\":\"text\",\"key\":\"full_name\",\"label\":\"Full name\",\"placeholder\":\"Jane Doe\",\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"minLength\":2,\"maxLength\":100},\"conditions\":null},{\"id\":\"fld_8BLApO6E\",\"type\":\"email\",\"key\":\"email\",\"label\":\"Email address\",\"placeholder\":\"jane@example.com\",\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_m25e3DET\",\"type\":\"rating\",\"key\":\"overall_rating\",\"label\":\"Overall rating\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"max\":5},\"conditions\":null},{\"id\":\"fld_ynykeIGJ\",\"type\":\"radio\",\"key\":\"recommend\",\"label\":\"Would you recommend us?\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"Yes\",\"value\":\"yes\"},{\"label\":\"No\",\"value\":\"no\"}],\"validation\":null,\"conditions\":null},{\"id\":\"fld_h04tU5ul\",\"type\":\"textarea\",\"key\":\"improvement\",\"label\":\"What should we improve?\",\"placeholder\":\"Be brutally honest\\u2026\",\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":null,\"conditions\":{\"logic\":\"all\",\"rules\":[{\"field\":\"recommend\",\"operator\":\"equals\",\"value\":\"no\"}]}}]}]}',1,'builder',NULL,'2026-08-05 02:39:19'),
(2,2,1,'{\"title\":\"Internship Application\",\"description\":\"Summer 2026 engineering internship \\u2014 applications close soon.\",\"settings\":{\"multi_step\":true,\"success_message\":\"Application received. We reply within a week.\",\"submit_label\":\"Submit application\",\"max_per_day\":100},\"sections\":[{\"id\":\"sec_5Ba5sBtq\",\"title\":\"Personal information\",\"description\":null,\"fields\":[{\"id\":\"fld_wQsjTW0v\",\"type\":\"text\",\"key\":\"full_name\",\"label\":\"Full name\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_OxBIcyrp\",\"type\":\"email\",\"key\":\"email\",\"label\":\"Email address\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_Wla6AqZy\",\"type\":\"phone\",\"key\":\"phone\",\"label\":\"Phone number\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_QhscPpWM\",\"type\":\"date\",\"key\":\"available_from\",\"label\":\"Available from\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"minDate\":\"2026-05-01\"},\"conditions\":null}]},{\"id\":\"sec_8mXqtkJs\",\"title\":\"Education & skills\",\"description\":null,\"fields\":[{\"id\":\"fld_ycREpkR3\",\"type\":\"dropdown\",\"key\":\"degree\",\"label\":\"Current degree\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"Bachelor\",\"value\":\"bachelor\"},{\"label\":\"Master\",\"value\":\"master\"},{\"label\":\"Other\",\"value\":\"other\"}],\"validation\":null,\"conditions\":null},{\"id\":\"fld_w9VYDphH\",\"type\":\"text\",\"key\":\"degree_other\",\"label\":\"Tell us about your degree\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":null,\"conditions\":{\"logic\":\"all\",\"rules\":[{\"field\":\"degree\",\"operator\":\"equals\",\"value\":\"other\"}]}},{\"id\":\"fld_PANetfsp\",\"type\":\"checkbox\",\"key\":\"skills\",\"label\":\"Skills\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"PHP \\/ Laravel\",\"value\":\"php\"},{\"label\":\"JavaScript \\/ React\",\"value\":\"js\"},{\"label\":\"Python\",\"value\":\"python\"},{\"label\":\"SQL\",\"value\":\"sql\"}],\"validation\":{\"max\":4},\"conditions\":null},{\"id\":\"fld_ubuTfPAx\",\"type\":\"number\",\"key\":\"experience_years\",\"label\":\"Years of experience\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"min\":0,\"max\":30,\"integer\":true},\"conditions\":null}]},{\"id\":\"sec_co6D2Uat\",\"title\":\"Documents\",\"description\":\"PDF only, max 2 MB.\",\"fields\":[{\"id\":\"fld_svLaiTD5\",\"type\":\"file\",\"key\":\"resume\",\"label\":\"Resume \\/ CV\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"mimes\":[\"pdf\"],\"maxSizeKb\":2048},\"conditions\":null},{\"id\":\"fld_L9wE2BRk\",\"type\":\"text\",\"key\":\"portfolio\",\"label\":\"Portfolio URL\",\"placeholder\":\"https:\\/\\/\\u2026\",\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"url\":true},\"conditions\":null}]}]}',1,'builder',NULL,'2026-08-05 02:39:19');
/*!40000 ALTER TABLE `form_versions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `public_id` char(26) NOT NULL,
  `title` varchar(200) NOT NULL,
  `schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schema`)),
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `current_version` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forms_public_id_unique` (`public_id`),
  KEY `forms_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `forms_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `forms` WRITE;
/*!40000 ALTER TABLE `forms` DISABLE KEYS */;
INSERT INTO `forms` VALUES
(1,1,'01kz8fghbjbes9bhb5w8qemj1q','Customer Feedback Survey','{\"title\":\"Customer Feedback Survey\",\"description\":\"Tell us how we did \\u2014 it takes less than two minutes.\",\"settings\":{\"multi_step\":false,\"success_message\":\"Thanks for the feedback!\",\"submit_label\":\"Send feedback\",\"max_per_day\":null},\"sections\":[{\"id\":\"sec_ORmV5Rf9\",\"title\":\"Your experience\",\"description\":null,\"fields\":[{\"id\":\"fld_C1JGHiln\",\"type\":\"text\",\"key\":\"full_name\",\"label\":\"Full name\",\"placeholder\":\"Jane Doe\",\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"minLength\":2,\"maxLength\":100},\"conditions\":null},{\"id\":\"fld_8BLApO6E\",\"type\":\"email\",\"key\":\"email\",\"label\":\"Email address\",\"placeholder\":\"jane@example.com\",\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_m25e3DET\",\"type\":\"rating\",\"key\":\"overall_rating\",\"label\":\"Overall rating\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"max\":5},\"conditions\":null},{\"id\":\"fld_ynykeIGJ\",\"type\":\"radio\",\"key\":\"recommend\",\"label\":\"Would you recommend us?\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"Yes\",\"value\":\"yes\"},{\"label\":\"No\",\"value\":\"no\"}],\"validation\":null,\"conditions\":null},{\"id\":\"fld_h04tU5ul\",\"type\":\"textarea\",\"key\":\"improvement\",\"label\":\"What should we improve?\",\"placeholder\":\"Be brutally honest\\u2026\",\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":null,\"conditions\":{\"logic\":\"all\",\"rules\":[{\"field\":\"recommend\",\"operator\":\"equals\",\"value\":\"no\"}]}}]}]}','published',1,'2026-08-05 02:39:19','2026-08-05 02:39:19',NULL),
(2,1,'01kz8fghbyngambmm8zq2bysaw','Internship Application','{\"title\":\"Internship Application\",\"description\":\"Summer 2026 engineering internship \\u2014 applications close soon.\",\"settings\":{\"multi_step\":true,\"success_message\":\"Application received. We reply within a week.\",\"submit_label\":\"Submit application\",\"max_per_day\":100},\"sections\":[{\"id\":\"sec_5Ba5sBtq\",\"title\":\"Personal information\",\"description\":null,\"fields\":[{\"id\":\"fld_wQsjTW0v\",\"type\":\"text\",\"key\":\"full_name\",\"label\":\"Full name\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_OxBIcyrp\",\"type\":\"email\",\"key\":\"email\",\"label\":\"Email address\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_Wla6AqZy\",\"type\":\"phone\",\"key\":\"phone\",\"label\":\"Phone number\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":null,\"conditions\":null},{\"id\":\"fld_QhscPpWM\",\"type\":\"date\",\"key\":\"available_from\",\"label\":\"Available from\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"minDate\":\"2026-05-01\"},\"conditions\":null}]},{\"id\":\"sec_8mXqtkJs\",\"title\":\"Education & skills\",\"description\":null,\"fields\":[{\"id\":\"fld_ycREpkR3\",\"type\":\"dropdown\",\"key\":\"degree\",\"label\":\"Current degree\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"Bachelor\",\"value\":\"bachelor\"},{\"label\":\"Master\",\"value\":\"master\"},{\"label\":\"Other\",\"value\":\"other\"}],\"validation\":null,\"conditions\":null},{\"id\":\"fld_w9VYDphH\",\"type\":\"text\",\"key\":\"degree_other\",\"label\":\"Tell us about your degree\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":null,\"conditions\":{\"logic\":\"all\",\"rules\":[{\"field\":\"degree\",\"operator\":\"equals\",\"value\":\"other\"}]}},{\"id\":\"fld_PANetfsp\",\"type\":\"checkbox\",\"key\":\"skills\",\"label\":\"Skills\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[{\"label\":\"PHP \\/ Laravel\",\"value\":\"php\"},{\"label\":\"JavaScript \\/ React\",\"value\":\"js\"},{\"label\":\"Python\",\"value\":\"python\"},{\"label\":\"SQL\",\"value\":\"sql\"}],\"validation\":{\"max\":4},\"conditions\":null},{\"id\":\"fld_ubuTfPAx\",\"type\":\"number\",\"key\":\"experience_years\",\"label\":\"Years of experience\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"min\":0,\"max\":30,\"integer\":true},\"conditions\":null}]},{\"id\":\"sec_co6D2Uat\",\"title\":\"Documents\",\"description\":\"PDF only, max 2 MB.\",\"fields\":[{\"id\":\"fld_svLaiTD5\",\"type\":\"file\",\"key\":\"resume\",\"label\":\"Resume \\/ CV\",\"placeholder\":null,\"help\":null,\"default\":null,\"required\":true,\"options\":[],\"validation\":{\"mimes\":[\"pdf\"],\"maxSizeKb\":2048},\"conditions\":null},{\"id\":\"fld_L9wE2BRk\",\"type\":\"text\",\"key\":\"portfolio\",\"label\":\"Portfolio URL\",\"placeholder\":\"https:\\/\\/\\u2026\",\"help\":null,\"default\":null,\"required\":false,\"options\":[],\"validation\":{\"url\":true},\"conditions\":null}]}]}','published',1,'2026-08-05 02:39:19','2026-08-05 02:39:19',NULL);
/*!40000 ALTER TABLE `forms` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `form_id` bigint(20) unsigned DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `kind` varchar(10) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'queued',
  `parsed_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parsed_schema`)),
  `warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`warnings`)),
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_form_id_foreign` (`form_id`),
  KEY `imports_user_id_status_id_index` (`user_id`,`status`,`id`),
  CONSTRAINT `imports_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `imports` WRITE;
/*!40000 ALTER TABLE `imports` DISABLE KEYS */;
/*!40000 ALTER TABLE `imports` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_08_05_000001_create_forms_tables',1),
(5,'2026_08_05_000002_create_submissions_tables',1),
(6,'2026_08_05_000003_create_form_events_table',1),
(7,'2026_08_05_000004_create_ai_generations_table',1),
(8,'2026_08_05_000005_create_imports_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `submission_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submission_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` bigint(20) unsigned NOT NULL,
  `field_key` varchar(64) NOT NULL,
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(127) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `submission_files_submission_id_field_key_index` (`submission_id`,`field_key`),
  CONSTRAINT `submission_files_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `submission_files` WRITE;
/*!40000 ALTER TABLE `submission_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_files` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `form_version_id` bigint(20) unsigned DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `search_text` text DEFAULT NULL,
  `ip_hash` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `submissions_form_version_id_foreign` (`form_version_id`),
  KEY `submissions_form_id_id_index` (`form_id`,`id`),
  KEY `submissions_form_id_created_at_index` (`form_id`,`created_at`),
  KEY `submissions_form_id_ip_hash_created_at_index` (`form_id`,`ip_hash`,`created_at`),
  FULLTEXT KEY `submissions_search_text_fulltext` (`search_text`),
  CONSTRAINT `submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_form_version_id_foreign` FOREIGN KEY (`form_version_id`) REFERENCES `form_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
INSERT INTO `submissions` VALUES
(1,1,1,'{\"full_name\":\"Aarav Sharma\",\"email\":\"aarav@example.com\",\"overall_rating\":5,\"recommend\":\"yes\"}','Aarav Sharma | aarav@example.com | 5 | Yes','8efd6168a055d1e45863c237eb3feab2be94f10b7ccdc5bf8bb54305c8b19f7f','Seeder','2026-07-31 02:36:19','2026-07-31 02:39:19'),
(2,1,1,'{\"full_name\":\"Priya Patel\",\"email\":\"priya@example.com\",\"overall_rating\":4,\"recommend\":\"yes\"}','Priya Patel | priya@example.com | 4 | Yes','0eb026731d9ea3f870511f8c18daeb814eaa2c9e276082b204f2a962212fb5bd','Seeder','2026-08-01 02:36:19','2026-08-01 02:39:19'),
(3,1,1,'{\"full_name\":\"Rohan Gupta\",\"email\":\"rohan@example.com\",\"overall_rating\":2,\"recommend\":\"no\",\"improvement\":\"Faster support responses, please.\"}','Rohan Gupta | rohan@example.com | 2 | No | Faster support responses, please.','532b20b0105c9883348558ed2711c7d23ea6b4ee718364a87e16fad4b3c3a029','Seeder','2026-08-02 02:36:19','2026-08-02 02:39:19'),
(4,1,1,'{\"full_name\":\"Sneha Iyer\",\"email\":\"sneha@example.com\",\"overall_rating\":5,\"recommend\":\"yes\"}','Sneha Iyer | sneha@example.com | 5 | Yes','06a8db106a32a00f305948a18f7c301fe27f780eb07fa61b5e664d51c1011718','Seeder','2026-08-03 02:36:19','2026-08-03 02:39:19'),
(5,1,1,'{\"full_name\":\"Vikram Singh\",\"email\":\"vikram@example.com\",\"overall_rating\":3,\"recommend\":\"no\",\"improvement\":\"The checkout flow is confusing.\"}','Vikram Singh | vikram@example.com | 3 | No | The checkout flow is confusing.','20e98ed255fc5b1683eecfde5e917384d22f99efe335ce172d2032394cfc35ba','Seeder','2026-08-04 02:36:19','2026-08-04 02:39:19');
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Demo User','demo@example.com','2026-08-05 02:39:19','$2y$12$nTzRslUESAhhrDj.mJRbee8xE.WYTMsGPrN/nUVTO86xLXQ3kifD.','w1QoGODUmD','2026-08-05 02:39:19','2026-08-05 02:39:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

