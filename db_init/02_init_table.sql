CREATE TABLE `dim_brand` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `brandprefix` varchar(10) NOT NULL,
  `section` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
);


CREATE TABLE `dim_category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(50) NOT NULL,
  `section` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `kit_backlog_plan` (
  `backlogid` int NOT NULL AUTO_INCREMENT,
  `inventoryid` int DEFAULT NULL,
  `buildplanid` int DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `notes` varchar(300) DEFAULT NULL,
  `references` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`backlogid`),
  KEY `fk_backlog_inventoryid` (`inventoryid`),
  KEY `fk_backlog_buildplanid` (`buildplanid`),
  CONSTRAINT `fk_backlog_buildplanid` FOREIGN KEY (`buildplanid`) REFERENCES `dim_category` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_backlog_inventoryid` FOREIGN KEY (`inventoryid`) REFERENCES `kit_inventory` (`inventoryid`) ON DELETE CASCADE
);

CREATE TABLE `kit_inventory` (
  `inventoryid` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL,
  `datebought` date DEFAULT NULL,
  `pricebought` int DEFAULT NULL,
  `notes` varchar(300) DEFAULT NULL,
  `brandid` int NOT NULL,
  PRIMARY KEY (`inventoryid`),
  KEY `fk_inventory_brand` (`brandid`),
  CONSTRAINT `fk_inventory_brand` FOREIGN KEY (`brandid`) REFERENCES `dim_brand` (`id`)
);

CREATE TABLE `kit_transaction_log` (
  `logid` varchar(255) NOT NULL,
  `backlogid` int DEFAULT NULL,
  `logname` varchar(255) NOT NULL,
  `notes` varchar(300) DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifiedat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagepath` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`logid`),
  KEY `fk_transaction_backlog` (`backlogid`),
  CONSTRAINT `fk_transaction_backlog` FOREIGN KEY (`backlogid`) REFERENCES `kit_backlog_plan` (`backlogid`)
);
