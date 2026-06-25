CREATE TABLE `tool_log` (
  `logid` varchar(255) NOT NULL,
  `toolid` int NOT NULL,
  `log_type` varchar(20) NOT NULL, -- 'Usage', 'Maintenance', 'General'
  `log_date` date NOT NULL,
  `notes` varchar(300) DEFAULT NULL,
  `imagepath` varchar(255) DEFAULT NULL,
  `backlogid` int DEFAULT NULL,
  PRIMARY KEY (`logid`),
  CONSTRAINT `fk_tool_log_tool` FOREIGN KEY (`toolid`) REFERENCES `tool_inventory` (`toolid`) ON DELETE CASCADE,
  CONSTRAINT `fk_tool_log_backlog` FOREIGN KEY (`backlogid`) REFERENCES `kit_backlog_plan` (`backlogid`) ON DELETE SET NULL
);
