
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cardkey` (
  `RFID` char(17) NOT NULL,
  `mmbr_id` int(11) DEFAULT NULL,
  `mmbr_secondary_id` int(11) DEFAULT NULL,
  `expires` date DEFAULT NULL,
  `override` char(1) DEFAULT NULL,
  `override_expires` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RFID`),
  KEY `mmbr_id` (`mmbr_id`),
  KEY `mmbr_secondary_id` (`mmbr_secondary_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cardkey_log` (
  `cardkey_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `RFID` char(17) DEFAULT NULL,
  `mmbr_id` int(11) DEFAULT NULL,
  `mmbr_secondary_id` int(11) DEFAULT NULL,
  `stamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `event` enum('unlocked','denied','admin') DEFAULT NULL,
  `override` char(1) DEFAULT NULL,
  `reason` char(32) DEFAULT NULL,
  `note` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`cardkey_log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=982 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

