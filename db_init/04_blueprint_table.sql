CREATE TABLE `kit_blueprint` (
  `blueprintid` int NOT NULL AUTO_INCREMENT,
  `backlogid` int NOT NULL,
  `canvas_data` longtext,
  `imagepath` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`blueprintid`),
  UNIQUE KEY `uk_blueprint_backlog` (`backlogid`),
  CONSTRAINT `fk_blueprint_backlog` FOREIGN KEY (`backlogid`) REFERENCES `kit_backlog_plan` (`backlogid`) ON DELETE CASCADE
);
