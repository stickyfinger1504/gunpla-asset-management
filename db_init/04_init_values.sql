-- =============================================================================
-- 1. Brands (dim_brand: Kits, Paints, Tools)
-- =============================================================================
INSERT INTO dim_brand (name, brandprefix, `section`) VALUES
    -- Kit Brands
    ('Bandai', 'BAN', 'kit'),
    ('Bootleg', 'BOT', 'kit'),
    ('Third Party', 'THP', 'kit'),
    ('P-Bandai', 'PBAN', 'kit'),

    -- Paint Brands
    ('Mr Hobby', 'MRH', 'paint'),
    ('Tamiya', 'TAM', 'paint'),
    ('Diton', 'DIT', 'paint'),
    ('JumpWind', 'JPW', 'paint'),
    ('Other', 'OTH', 'paint'),
    ('Samurai Paint', 'SMP', 'paint'),

    -- Tool Brands
    ('Tamiya', 'TAM', 'tool'),
    ('Godhand', 'GH', 'tool'),
    ('Mr. Hobby', 'MRH', 'tool'),
    ('Olfa', 'OLF', 'tool'),
    ('SM-F', 'SMF', 'tool'),
    ('Other', 'OTH', 'tool');

-- =============================================================================
-- 2. Kit Inventory Statuses (dim_category: kitinventory / status)
-- =============================================================================
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Not Started', 'kitinventory', 'status', NULL),
    ('Straight build', 'kitinventory', 'status', NULL),
    ('In Progress', 'kitinventory', 'status', NULL),
    ('Done', 'kitinventory', 'status', NULL),
    ('Archived', 'kitinventory', 'status', NULL);

-- =============================================================================
-- 3. Backlog Build Plans (dim_category: backlogplan / buildplan)
-- =============================================================================
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Clean Build', 'backlogplan', 'buildplan', NULL),
    ('Custom Build', 'backlogplan', 'buildplan', NULL),
    ('Painted Build', 'backlogplan', 'buildplan', NULL),
    ('Full Build', 'backlogplan', 'buildplan', NULL),
    ('Kitbash Material', 'backlogplan', 'buildplan', NULL);

-- =============================================================================
-- 4. Backlog Statuses (dim_category: backlogplan / status)
-- =============================================================================
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Not Started', 'backlogplan', 'status', NULL),
    ('On Hold', 'backlogplan', 'status', NULL),
    ('In Progress', 'backlogplan', 'status', NULL),
    ('Done', 'backlogplan', 'status', NULL);

-- =============================================================================
-- 5. Wishlist Priorities (dim_category: wishlist / priority)
-- =============================================================================
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Low', 'wishlist', 'priority', NULL),
    ('Mid', 'wishlist', 'priority', NULL),
    ('High', 'wishlist', 'priority', NULL);

-- =============================================================================
-- 6. Paint Specifications (dim_category: paintlist)
-- =============================================================================
-- Paint Types (Deduplicated: single 'Thinner' entry)
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Spray Can', 'paintlist', 'painttype', NULL),
    ('Lacquer', 'paintlist', 'painttype', NULL),
    ('Thinner', 'paintlist', 'painttype', NULL),
    ('Acrylic', 'paintlist', 'painttype', NULL),
    ('Acrylic-Solvent mixed', 'paintlist', 'painttype', NULL),
    ('Enamel', 'paintlist', 'painttype', NULL);

-- Paint Finishes
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Matte', 'paintlist', 'finish', NULL),
    ('Gloss', 'paintlist', 'finish', NULL),
    ('Metallic', 'paintlist', 'finish', NULL),
    ('Pearlescent', 'paintlist', 'finish', NULL),
    ('Clear', 'paintlist', 'finish', NULL);

-- Paint Amounts
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Full or virtually full', 'paintlist', 'amount', NULL),
    ('Partially used', 'paintlist', 'amount', NULL),
    ('Half used', 'paintlist', 'amount', NULL),
    ('Few left', 'paintlist', 'amount', NULL),
    ('Little amount left', 'paintlist', 'amount', NULL),
    ('Finished', 'paintlist', 'amount', NULL);

-- Paint Thinned Statuses
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Thinned', 'paintlist', 'thinnedstatus', NULL),
    ('Partially used', 'paintlist', 'thinnedstatus', NULL),
    ('Not Thinned', 'paintlist', 'thinnedstatus', NULL),
    ('Partially Dry', 'paintlist', 'thinnedstatus', NULL),
    ('Dried Out', 'paintlist', 'thinnedstatus', NULL),
    ('Partially Thinned', 'paintlist', 'thinnedstatus', NULL);

-- =============================================================================
-- 7. Tool Specifications (dim_category: toolbox)
-- =============================================================================
-- Tool Categories
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('Nipper', 'toolbox', 'category', NULL),
    ('Sanding Stick', 'toolbox', 'category', NULL),
    ('Sandpaper', 'toolbox', 'category', NULL),
    ('Brush', 'toolbox', 'category', NULL),
    ('Airbrush', 'toolbox', 'category', NULL),
    ('Knife / Scriber', 'toolbox', 'category', NULL),
    ('Replacement Blade', 'toolbox', 'category', NULL),
    ('Tweezer', 'toolbox', 'category', NULL),
    ('Putty / Filler', 'toolbox', 'category', NULL),
    ('Other', 'toolbox', 'category', NULL);

-- Tool Condition Statuses
INSERT INTO dim_category (label, `section`, module, remarks) VALUES
    ('New', 'toolbox', 'status', NULL),
    ('Good', 'toolbox', 'status', NULL),
    ('Worn', 'toolbox', 'status', NULL),
    ('Needs Replacement', 'toolbox', 'status', NULL),
    ('Broken', 'toolbox', 'status', NULL),
    ('Retired', 'toolbox', 'status', NULL);
