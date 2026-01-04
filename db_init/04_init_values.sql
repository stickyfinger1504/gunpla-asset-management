INSERT INTO dim_brand (name,brandprefix,`section`) VALUES
	 ('Bandai','BAN','kit'),
	 ('Bootleg','BOT','kit'),
	 ('Third Party','THP','kit'),
	 ('P-Bandai','PBAN','kit'),
	 ('Mr Hobby','MRH','paint'),
	 ('Tamiya','TAM','paint'),
	 ('Diton','DIT','paint'),
	 ('JumpWind','JPW','paint'),
	 ('Other','OTH','paint'),
	 ('Samurai Paint','SMP','paint');

INSERT INTO dim_category (label,`section`,module,remarks) VALUES
	 ('Clean Build','backlogplan','buildplan',NULL),
	 ('Custom Build','backlogplan','buildplan',NULL),
	 ('Painted Build','backlogplan','buildplan',NULL),
	 ('Full Build','backlogplan','buildplan',NULL),
	 ('Kitbash Material','backlogplan','buildplan',NULL),
	 ('Not Started','kitinventory','status',NULL),
	 ('Straight build','kitinventory','status',NULL),
	 ('In Progress','kitinventory','status',NULL),
	 ('Done','kitinventory','status',NULL),
	 ('Not Started','backlogplan','status',NULL);
INSERT INTO dim_category (label,`section`,module,remarks) VALUES
	 ('On Hold','backlogplan','status',NULL),
	 ('In Progress','backlogplan','status',NULL),
	 ('Done','backlogplan','status',NULL),
	 ('Low','wishlist','priority',NULL),
	 ('Mid','wishlist','priority',NULL),
	 ('High','wishlist','priority',NULL),
	 ('Full or virtually full','paintlist','amount',NULL),
	 ('Partially used','paintlist','amount',NULL),
	 ('Half used','paintlist','amount',NULL),
	 ('Few left','paintlist','amount',NULL);
INSERT INTO dim_category (label,`section`,module,remarks) VALUES
	 ('Little amount left','paintlist','amount',NULL),
	 ('Finished','paintlist','amount',NULL),
	 ('Thinned','paintlist','thinnedstatus',NULL),
	 ('Partially used','paintlist','thinnedstatus',NULL),
	 ('Not Thinned','paintlist','thinnedstatus',NULL),
	 ('Partially Dry','paintlist','thinnedstatus',NULL),
	 ('Dried Out','paintlist','thinnedstatus',NULL),
	 ('Spray Can','paintlist','painttype',NULL),
	 ('Lacquer','paintlist','painttype',NULL),
	 ('Thinner','paintlist','painttype',NULL);
INSERT INTO dim_category (label,`section`,module,remarks) VALUES
	 ('Acrylic','paintlist','painttype',NULL),
	 ('Acrylic-Solvent mixed','paintlist','painttype',NULL),
	 ('Enamel','paintlist','painttype',NULL),
	 ('Thinner','paintlist','painttype',NULL),
	 ('Partially Thinned','paintlist','thinnedstatus',NULL),
	 ('Archived','kitinventory','status',NULL);
