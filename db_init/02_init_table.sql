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


CREATE TABLE kit_wishlist (
    wishlistid INT NOT NULL AUTO_INCREMENT,
    inventoryid INT DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    brandid INT DEFAULT NULL,
    obtained BOOLEAN NOT NULL,
    priorityid INT DEFAULT NULL,
    link VARCHAR(300) DEFAULT NULL,
    notes varchar(300) DEFAULT NULL,
    PRIMARY KEY (wishlistid),
    
    CONSTRAINT fk_wishlist_inventory 
        FOREIGN KEY (inventoryid) REFERENCES kit_inventory(inventoryid)
        ON DELETE SET NULL,
        
    CONSTRAINT fk_wishlist_brand 
        FOREIGN KEY (brandid) REFERENCES dim_brand(id)
        ON DELETE SET NULL,
        
    CONSTRAINT fk_wishlist_priority 
        FOREIGN KEY (priorityid) REFERENCES dim_category(id)
        ON DELETE SET NULL
);

CREATE TABLE kit_task (
    taskid INT NOT NULL AUTO_INCREMENT,
    backlogid INT DEFAULT NULL,
    description VARCHAR(300) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    imagepath VARCHAR(255) DEFAULT NULL,
    createdat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifiedat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (taskid),
    KEY fk_task_backlog (backlogid),
    CONSTRAINT fk_task_backlog FOREIGN KEY (backlogid)
        REFERENCES kit_backlog_plan(backlogid) ON DELETE CASCADE
);

CREATE TABLE paint_inventory (
    inventoryid INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    brand INT NOT NULL,
    painttype INT NOT NULL,
    thinned INT DEFAULT NULL,
    amount INT DEFAULT NULL,
    createddate TIMESTAMP DEFAULT NULL,
    lastupdate TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes VARCHAR(300) DEFAULT NULL,
    imagepath VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (inventoryid),
    CONSTRAINT fk_p_inventory_brand FOREIGN KEY (brand) REFERENCES dim_brand (id),
    CONSTRAINT fk_p_inventory_painttype FOREIGN KEY (painttype) REFERENCES dim_category (id),
    CONSTRAINT fk_p_inventory_thinned FOREIGN KEY (thinned) REFERENCES dim_category (id),
    CONSTRAINT fk_p_inventory_amount FOREIGN KEY (amount) REFERENCES dim_category (id)
);


CREATE TABLE paint_wishlist (
    wishlistid INT NOT NULL AUTO_INCREMENT,
    inventoryid INT DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    brandid INT DEFAULT NULL,
    obtained BOOLEAN NOT NULL,
    priorityid INT DEFAULT NULL,
    painttypeid INT DEFAULT NULL,
    link VARCHAR(300) DEFAULT NULL,
    notes varchar(300) DEFAULT NULL,
    PRIMARY KEY (wishlistid),
    CONSTRAINT fk_p_wishlist_inventory 
        FOREIGN KEY (inventoryid) REFERENCES paint_inventory(inventoryid)
        ON DELETE SET NULL,
    CONSTRAINT fk_p_wishlist_brand 
        FOREIGN KEY (brandid) REFERENCES dim_brand(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_p_wishlist_priority 
        FOREIGN KEY (priorityid) REFERENCES dim_category(id)
        ON DELETE SET NULL,   
    CONSTRAINT fk_p_wishlist_painttype 
    	FOREIGN KEY (painttypeid) REFERENCES dim_category(id)
    	ON DELETE SET NULL
);

CREATE TABLE paint_recipe (
    recipeid       INT NOT NULL AUTO_INCREMENT,
    name           VARCHAR(255) NOT NULL,
    thinner_ratio  VARCHAR(50) DEFAULT NULL,
    imagepath      VARCHAR(255) DEFAULT NULL,
    notes          VARCHAR(300) DEFAULT NULL,
    createdat      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifiedat     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (recipeid)
);

CREATE TABLE paint_recipe_item (
    itemid        INT NOT NULL AUTO_INCREMENT,
    recipeid      INT NOT NULL,
    paintid       INT NOT NULL,
    percentage    INT NOT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    PRIMARY KEY (itemid),
    KEY fk_recipe_item_recipe (recipeid),
    KEY fk_recipe_item_paint (paintid),
    CONSTRAINT fk_recipe_item_recipe FOREIGN KEY (recipeid)
        REFERENCES paint_recipe(recipeid) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_item_paint FOREIGN KEY (paintid)
        REFERENCES paint_inventory(inventoryid) ON DELETE CASCADE
);