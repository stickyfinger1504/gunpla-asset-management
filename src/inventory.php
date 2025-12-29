<?php
require 'db_connect.php';

$message = "";
$label_target = 'archived';
$archived_id = null;

$sql = "SELECT id FROM dim_category WHERE section='kitinventory' AND module='status' AND label='$label_target' LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $archived_id = $row['id'];
} else {
    $sql_create = "INSERT INTO dim_category (section, module, label) VALUES ('kitinventory', 'status', '$label_target')";
    if ($conn->query($sql_create)) {
        $archived_id = $conn->insert_id;
    } else {
        $message = "Error creating '$label_target': " . $conn->error;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['deleteid'])) {
        $del_id = $_POST['deleteid'];
        $stmt = $conn->prepare("DELETE FROM kit_inventory WHERE inventoryid=?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            $message = "✅ Successfully Deleted";   
        } else {
            $message = "❌ Delete failed " . $conn->error;
        }
        $stmt->close();
    }
    
    elseif (isset($_POST['archiveid'])) {
        $id = $_POST['archiveid'];
        if ($archived_id) {
            $stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
            $stmt->bind_param("ii", $archived_id, $id);
            if ($stmt->execute()) {
                $message = "✅ Successfully marked as " . $label_target;
            } else {
                $message = "❌ Failed to archive.";
            }
            $stmt->close();
        }
    }

    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $kit_name = $_POST["kit_name"];
        $status = $_POST["statusid"];
        $datebought = !empty($_POST["datebought"]) ? $_POST["datebought"] : null;
        $pricebought = !empty($_POST["pricebought"]) ? $_POST["pricebought"] : null;
        $notes = !empty($_POST["notes"]) ? $_POST['notes'] : null;
        $brand_id = $_POST["brandid"];

        $stmt = $conn->prepare("INSERT INTO kit_inventory (name, status, datebought, pricebought, notes, brandid) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sisisi", $kit_name, $status, $datebought, $pricebought, $notes, $brand_id);

        if ($stmt->execute()) {
            $message = "✅ Successfully added " . htmlspecialchars($kit_name);
        } else {
            $message = "❌ Error adding: " . $conn->error;
        }
        $stmt->close();
    }

    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $id = $_POST['edit_id'];
        $kit_name = $_POST["kit_name"];
        $status = $_POST["statusid"];
        
        $datebought = !empty($_POST["datebought"]) ? $_POST["datebought"] : null;
        $pricebought = !empty($_POST["pricebought"]) ? $_POST["pricebought"] : null;
        $notes = (isset($_POST["notes"]) && $_POST["notes"] !== '') ? $_POST['notes'] : null;
        $brand_id = $_POST["brandid"];

        $stmt = $conn->prepare("UPDATE kit_inventory SET name=?, status=?, datebought=?, pricebought=?, notes=?, brandid=? WHERE inventoryid=?");
        $stmt->bind_param("sisisii", $kit_name, $status, $datebought, $pricebought, $notes, $brand_id, $id);

        if ($stmt->execute()) {
            $message = "✅ Kit updated successfully.";
        } else {
            $message = "❌ Error updating: " . $conn->error;
        }
        $stmt->close();
    }
}

function getoptions($conn, $table, $id, $name, $section, $section_value){
    $options = "";
    $sql = "SELECT * FROM $table WHERE $section= '$section_value' ORDER BY $name ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id_row = $row[$id];
            $name_row = $row[$name];
            $selected = (isset($_GET['filter_brand']) && $_GET['filter_brand'] == $id_row) ? 'selected' : '';
            $options .= "<option value='$id_row' $selected>$name_row</option>";
        }
    }
    return $options;
}

function getoptions2($conn, $table, $id, $name, $section, $section_value, $section2, $section2_value){
    $options = "";
    $sql = "SELECT * FROM $table WHERE $section= '$section_value' AND $section2='$section2_value' ORDER BY $name ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $options .= "<option value='{$row[$id]}'>{$row[$name]}</option>";
        }
    }
    return $options;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f9; color: #333; max-width: 950px; margin: 2rem auto; padding: 20px; }
        .input-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 30px; }
        h1 { color: #2c3e50; text-align: center; }
        label { display: block; margin-top: 15px; font-weight: 600; color: #555; }
        input, select, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button.save-btn { margin-top: 20px; width: 100%; padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        button.save-btn:hover { background-color: #2980b9; }
        .msg { padding: 10px; border-radius: 4px; margin-bottom: 10px; background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        
        /* Table Styling */
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        th { background-color: #2c3e50; color: white; text-transform: uppercase; font-size: 14px; letter-spacing: 0.5px; }
        tr:hover { background-color: #f1f1f1; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; background: #eee; color: #555; }

        .action-row { display: flex; gap: 5px; align-items: center; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            border: none; cursor: pointer; text-decoration: none; font-size: 16px; transition: background 0.2s;
        }
        .btn-edit { background-color: #f0f0f0; } 
        .btn-archive { background-color: #e3f2fd; color: #1976d2; } 
        .btn-delete { background-color: #ffebee; color: #c62828; }
        .action-btn:hover { filter: brightness(0.9); }
        form.inline-form { margin: 0; padding: 0; }

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; top: 15px; right: 15px; font-size: 24px; cursor: pointer; color: #888; line-height: 1; }
        .close-btn:hover { color: #333; }
    </style>
</head>
<body>

    <h1>🤖 Gunpla Hangar</h1>
    
    <div class="input-card">
        <h2>Add New Kit</h2>
        <?php if($message) echo "<div class='msg'>$message</div>"; ?>

        <form method="post">
            <input type="hidden" name="action_type" value="add">

            <label>Kit Name:</label>
            <input type="text" name="kit_name" required placeholder="e.g. MG Barbatos Lupus Rex">

            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label>Brand:</label>
                    <select name="brandid" required>
                        <option value="">-- Select Brand --</option>
                        <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Status:</label>
                    <select name="statusid" required>
                        <?php echo getOptions2($conn, 'dim_category', 'id', 'label', 'section','kitinventory', 'module','status'); ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label>Date Bought:</label>
                    <input type="date" name="datebought">
                </div>
                <div style="flex: 1;">
                    <label>Price (IDR):</label>
                    <input type="number" step="1" name="pricebought" placeholder="0.00">
                </div>
            </div>

            <label>Notes:</label>
            <textarea name="notes" rows="3" placeholder="First batch, heavy weathering planned..."></textarea>

            <button type="submit" class="save-btn">Save to Database</button>
        </form>
    </div>

    <h3>📦 Current Inventory</h3>
    <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
            <div style="flex: 1;">
                <label>Filter Brand:</label>
                <select name="filter_brand">
                    <option value="">All Brands</option>
                    <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Sort By:</label>
                <select name="sortby">
                    <option value="date_desc">Date Bought (Newest)</option>
                    <option value="date_asc">Date Bought (Oldest)</option>
                    <option value="price_desc">Price (Highest)</option>
                    <option value="price_asc">Price (Lowest)</option>
                </select>
            </div>
            <div style="padding-bottom:10px;">
                <label style="margin-top:0; font-weight:normal;">
                    <input type="checkbox" name="show_archived" value="1" <?php echo isset($_GET['show_archived']) ? 'checked' : ''; ?>> 
                    Show <?php echo ucfirst($label_target); ?>
                </label>
            </div>
            <div>
                <button type="submit" class="save-btn" style="margin-top:0;">Apply</button>
                <a href="inventory.php" style="margin-left:10px; text-decoration:none; color:#555;">Clear</a>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kit Name</th>
                <th>Brand</th>
                <th>Status</th>
                <th>Date Bought</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT * FROM vw_kit_inventory WHERE 1=1";

            if (!isset($_GET['show_archived'])) {
                if ($archived_id) {
                    $sql .= " AND statusid != $archived_id";
                }
            }

            if (isset($_GET['filter_brand']) && $_GET['filter_brand'] != '') {
                $f_brand = (int)$_GET['filter_brand'];
                $sql .= " AND brandid='$f_brand'";
            }
            
            $sort = $_GET['sortby'] ?? 'date_desc';
            switch($sort) {
                case 'price_desc': $sql .= " ORDER BY pricebought DESC"; break;
                case 'price_asc':  $sql .= " ORDER BY pricebought ASC"; break;
                case 'date_asc':   $sql .= " ORDER BY datebought ASC"; break;
                default:           $sql .= " ORDER BY datebought DESC";
            }

            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $real_id = $row['actualid'];
                    $display_id = $row['id'] ?? 'ERR';
                    $price_display = $row['pricebought'] ? "Rp. " . number_format($row['pricebought'], 0, ',', '.') : "-";
                    

                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                    $safe_notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES);

                    echo "<tr>
                        <td><span class='badge'>$display_id</span></td>
                        <td><strong>{$row['name']}</strong></td>
                        <td>{$row['brand']}</td> 
                        <td>{$row['status']}</td>
                        <td>{$row['datebought']}</td>
                        <td>$price_display</td>
                        <td>
                            <div class='action-row'>
                                <button type='button' class='action-btn btn-edit' title='Edit'
                                    data-id='{$real_id}'
                                    data-name='{$safe_name}'
                                    data-brand='{$row['brandid']}'
                                    data-status='{$row['statusid']}'
                                    data-date='{$row['datebought']}'
                                    data-price='{$row['pricebought']}'
                                    data-notes='{$safe_notes}'
                                    onclick='openEditModal(this)'>
                                    ✏️
                                </button>
                                
                                <form method='POST' class='inline-form' onsubmit='return confirm(\"Archive {$safe_name}?\");'>
                                    <input type='hidden' name='archiveid' value='$real_id'>
                                    <button type='submit' class='action-btn btn-archive' title='Archive'>📦</button>
                                </form>

                                <form method='POST' class='inline-form' onsubmit='return confirm(\"Delete this kit?\");'>
                                    <input type='hidden' name='deleteid' value='$real_id'>
                                    <button type='submit' class='action-btn btn-delete' title='Delete'>🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>";
                }
            } 
            else {
                echo "<tr><td colspan='7' style='text-align:center; padding:20px;'>No kits found in the hangar yet! Start buying!</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h2>✏️ Edit Kit</h2>
            
            <form method="post">
                <input type="hidden" name="action_type" value="edit"> 
                <input type="hidden" name="edit_id" id="modal_id">
                
                <label>Kit Name:</label>
                <input type="text" name="kit_name" id="modal_name" required>

                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Brand:</label>
                        <select name="brandid" id="modal_brand" required>
                            <option value="">-- Select --</option>
                            <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label>Status:</label>
                        <select name="statusid" id="modal_status" required>
                            <?php echo getoptions2($conn, 'dim_category', 'id', 'label', 'section','kitinventory', 'module','status'); ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Date Bought:</label>
                        <input type="date" name="datebought" id="modal_date">
                    </div>
                    <div style="flex: 1;">
                        <label>Price (IDR):</label>
                        <input type="number" step="1" name="pricebought" id="modal_price">
                    </div>
                </div>

                <label>Notes:</label>
                <textarea name="notes" id="modal_notes" rows="3"></textarea>

                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

<script>
    function openEditModal(button) {
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const brand = button.getAttribute('data-brand');
        const status = button.getAttribute('data-status');
        const date = button.getAttribute('data-date');
        const price = button.getAttribute('data-price');
        const notes = button.getAttribute('data-notes');


        document.getElementById('modal_id').value = id;
        document.getElementById('modal_name').value = name;
        document.getElementById('modal_brand').value = brand;   
        document.getElementById('modal_status').value = status; 
        document.getElementById('modal_date').value = date;
        document.getElementById('modal_price').value = price;
        document.getElementById('modal_notes').value = notes;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal if user clicks outside the white box
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeEditModal();
        }
    }
</script>
</body>
</html>