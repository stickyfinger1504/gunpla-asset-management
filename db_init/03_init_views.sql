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

CREATE OR REPLACE view vw_kit_wishlist as (

	With calculatedwishlist as (
		Select
		a.wishlistid as  id,
		a.inventoryid,
		a.name,
		a.brandid,
		a.obtained,
		a.link,
		a.notes,
		b.name as brand,
		c.label as label,
		c.id as priorityid,
		ROW_NUmBER() OVER (order by a.wishlistid, a.name) as rn
		from kit_wishlist a
		left join dim_brand b on a.brandid=b.id
		left join dim_category c on a.priorityid=c.id and c.section='wishlist' and c.module='priority'
	)
	select concat('WSH-',a.rn) as id,
			a.id as actualid,
			b.id as inventoryid,
			a.name,
			a.brandid,
			a.brand,
			a.obtained as obtainedid,
			case when a.obtained = 1 then 'Yes'
			when a.obtained = 0 then 'No'
			end as obtained,
			a.label as priority,
			a.priorityid,
			a.link,
			a.notes
	from calculatedwishlist a
	left join vw_kit_inventory b on a.inventoryid=b.actualid
	
);

CREATE OR REPLACE VIEW vw_kit_backlog_plan as (
	With calculatedbacklog as (
		Select 
			a.backlogid,
			a.inventoryid,
			b.id as inventory_id,
			a.buildplanid,
			a.status,
			a.notes,
			a.`references`,
			b.name,
			c.label as buildplan_label,
			d.label as status_label,
			row_number() over (order by a.backlogid) as rn
			from kit_backlog_plan a
			left join vw_kit_inventory b on a.inventoryid = b.actualid
			left join dim_category c on a.buildplanid = c.id and c.section ='backlogplan' and c.module='buildplan'
			left join dim_category d on a.status = d.id and d.section = 'backlogplan' and d.module='status'
	)
	select
		concat('BCKLG-',rn),
		backlogid 'actualid',
		inventoryid,
		inventory_id,
		name,
		buildplanid,
		buildplan_label,
		status,
		status_label,
		notes,
		`references`
	from calculatedbacklog
);

CREATE OR REPLACE view vw_kit_transaction_log as (
	select a.logid,
		a.backlogid as actual_backlogid,
		b.backlogid,
		b.inventory_id,
		b.name,
		a.logname,
		a.notes,
		a.createdat,
		a.modifiedat,
		a.imagepath
		from kit_transaction_log a
		left join vw_kit_backlog_plan b on a.backlogid=b.actualid
);

CREATE OR REPLACE VIEW vw_kit_task AS (
    SELECT
        t.taskid,
        t.backlogid,
        t.description,
        t.is_done,
        t.sort_order,
        t.createdat,
        t.modifiedat,
		t.imagepath,
        bp.name AS kit_name,
        bp.inventoryid,
        bp.buildplan_label,
        bp.status_label AS backlog_status
    FROM kit_task t
    LEFT JOIN vw_kit_backlog_plan bp ON t.backlogid = bp.actualid
);

CREATE OR REPLACE VIEW vw_paint_inventory as (
with calculatedpaint as(
	SELECT a.*,
	b.brandprefix,
	b.name as brand_label,
	c.label as painttype_label,
	d.label as thinned_label,
	e.label as amount_label,
    ROW_NUMBER() OVER (
    PARTITION BY a.brand
    ORDER BY a.createddate
    ) AS rn
	FROM paint_inventory a
	LEFT JOIN dim_brand b on a.brand=b.id and b.section='paint'
	LEFT JOIN dim_category c on a.painttype=c.id AND c.section = 'paintlist'
    AND c.module = 'painttype'
	LEFT JOIN dim_category d on a.thinned=d.id AND d.section = 'paintlist'
    AND d.module = 'thinnedstatus'
	LEFT JOIN dim_category e on a.amount=e.id AND e.section = 'paintlist'
    AND e.module = 'amount'
	)
	SELECT 
		a.inventoryid as actualid,
		concat(a.brandprefix,'-',a.rn) as id,
		a.name,
		a.brand_label as brand,
		a.brand as brandid,
		a.painttype_label as painttype,
		a.painttype as painttypeid,
		a.thinned_label as thinned,
		a.thinned as thinnedid,
		a.amount_label as amount,
		a.amount as amountid,
		a.createddate,
		a.lastupdate,
		a.notes,
		a.imagepath
	from calculatedpaint a
);