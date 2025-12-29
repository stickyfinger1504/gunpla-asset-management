CREATE OR REPLACE VIEW vw_kit_inventory AS
WITH CalculatedInventory AS (
    SELECT
        a.inventoryid AS id,
        b.id AS brandid,
        b.brandprefix AS brandprefix,
        b.name AS brand,
        a.name AS name,
        c.id AS statusid,
        c.label AS label,
        a.datebought AS datebought,
        a.pricebought AS pricebought,
        a.notes AS notes,
        ROW_NUMBER() OVER (
            PARTITION BY a.brandid
            ORDER BY a.datebought
        ) AS rn
    FROM kit_inventory a
    LEFT JOIN dim_brand b ON a.brandid = b.id
    LEFT JOIN dim_category c ON a.status = c.id
        AND c.section = 'kitinventory'
        AND c.module = 'status'
)
SELECT
    CalculatedInventory.id AS actualid,
    CONCAT(CalculatedInventory.brandprefix, '-', CalculatedInventory.rn) AS id,
    CalculatedInventory.name AS name,
    CalculatedInventory.brand AS brand,
    CalculatedInventory.brandid AS brandid,
    CalculatedInventory.statusid AS statusid,
    CalculatedInventory.label AS status,
    CalculatedInventory.datebought AS datebought,
    CalculatedInventory.pricebought AS pricebought,
    CalculatedInventory.notes AS notes
FROM CalculatedInventory;