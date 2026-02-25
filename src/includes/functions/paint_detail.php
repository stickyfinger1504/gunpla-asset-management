<?php
/**
 * Paint Detail Page — data fetching functions
 */

function get_paint_detail($conn, int $inventoryid): ?array {
    $stmt = $conn->prepare("SELECT * FROM vw_paint_inventory WHERE actualid = ?");
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $paint = $stmt->get_result()->fetch_assoc();

    if (!$paint) return null;

    $stmt = $conn->prepare(
        "SELECT pw.*, b.name AS brand, dc.label AS priority
         FROM paint_wishlist pw
         LEFT JOIN dim_brand b ON pw.brandid = b.id
         LEFT JOIN dim_category dc ON pw.priorityid = dc.id
         WHERE pw.inventoryid = ?"
    );
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $wishlist = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare(
        "SELECT r.recipeid, r.name, r.thinner_ratio, r.imagepath, ri.percentage
         FROM paint_recipe_item ri
         JOIN paint_recipe r ON ri.recipeid = r.recipeid
         WHERE ri.paintid = ?
         ORDER BY r.name ASC"
    );
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'paint'    => $paint,
        'wishlist' => $wishlist,
        'recipes'  => $recipes,
    ];
}
