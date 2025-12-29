ALTER VIEW vw_kit_inventory as (
WITH CalculatedInventory AS (
    SELECT
    	a.inventoryid as id,
    	b.id as brandid,
        b.brandprefix,
        b.name as brand,
        a.name, 
        c.id as statusid,
        c.label, 
        a.datebought, 
        a.pricebought, 
        a.notes,
        ROW_NUMBER() OVER (PARTITION BY a.brandid ORDER BY a.datebought) as rn
    FROM kit_inventory a
    LEFT JOIN dim_brand b ON a.brandid = b.id
    LEFT JOIN dim_category c on a.status =c.id  and c.section='kitinventory' and c.module='status'
)
SELECT 
	id as actualid,
    CONCAT(brandprefix, '-', rn) AS id,
    name, 
    brand,
    brandid,
    statusid,
    label as status, 
    datebought, 
    pricebought, 
    notes
FROM CalculatedInventory
);