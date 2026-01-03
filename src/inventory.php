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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-animate { animation: slideDown 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include 'header.php'; ?>

    <div class="max-w-5xl mx-auto p-6">

        <h1 class="text-3xl font-bold text-gray-700 text-center mb-8">🤖 Gunpla Hangar</h1>
        
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Add New Kit</h2>
            
            <?php if($message) echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>$message</div>"; ?>

            <form method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="add">

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Kit Name:</label>
                    <input type="text" name="kit_name" required placeholder="e.g. MG Barbatos Lupus Rex" 
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                        <select name="brandid" required class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">-- Select Brand --</option>
                            <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Status:</label>
                        <select name="statusid" required class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <?php echo getOptions2($conn, 'dim_category', 'id', 'label', 'section','kitinventory', 'module','status'); ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Date Bought:</label>
                        <input type="date" name="datebought" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Price (IDR):</label>
                        <input type="number" step="1" name="pricebought" placeholder="Example: 150000" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                    <textarea name="notes" rows="3" placeholder="Details..." class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-200">
                    Save to Database
                </button>
            </form>
        </div>

        <h3 class="text-xl font-bold text-gray-700 mb-2">📦 Current Inventory</h3>
        <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Filter Brand</label>
                    <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">All Brands</option>
                        <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Sort By</label>
                    <select name="sortby" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="date_desc">Date Bought (Newest)</option>
                        <option value="date_asc">Date Bought (Oldest)</option>
                        <option value="price_desc">Price (Highest)</option>
                        <option value="price_asc">Price (Lowest)</option>
                    </select>
                </div>
                <div class="pb-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="show_archived" value="1" <?php echo isset($_GET['show_archived']) ? 'checked' : ''; ?> class="form-checkbox h-4 w-4 text-blue-600"> 
                        <span class="ml-2 text-gray-700">Show <?php echo ucfirst($label_target); ?></span>
                    </label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Apply</button>
                    <a href="inventory.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Clear</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Kit Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Brand</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
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

                            echo "<tr class='hover:bg-gray-50 border-b border-gray-100'>
                                <td class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'>{$display_id}</td>
                                <td class='px-4 py-3 text-sm font-semibold text-gray-800'>{$row['name']}</td>
                                <td class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>{$row['brand']}</td>
                                <td class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>{$row['status']}</td>
                                <td class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>{$row['datebought']}</td>
                                <td class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>{$price_display}</td>
                                <td class='px-4 py-3 text-sm'>
                                    <div class='flex items-center space-x-2'>
                                        <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
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
                                        
                                        <form method='POST' class='inline' onsubmit='return confirm(\"Archive {$safe_name}?\");'>
                                            <input type='hidden' name='archiveid' value='$real_id'>
                                            <button type='submit' class='p-1 hover:bg-blue-100 rounded text-lg' title='Archive'>📦</button>
                                        </form>

                                        <form method='POST' class='inline' onsubmit='return confirm(\"Delete this kit?\");'>
                                            <input type='hidden' name='deleteid' value='$real_id'>
                                            <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>";
                        }
                    } 
                    else {
                        echo "<tr><td colspan='7' class='text-center py-6 text-gray-500'>No kits found in the hangar yet! Start buying!</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="editModal" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-md p-6 modal-animate relative">
            <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="closeEditModal()">&times;</span>
            <h2 class="text-xl font-bold text-gray-700 mb-4">✏️ Edit Kit</h2>
            
            <form method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="edit"> 
                <input type="hidden" name="edit_id" id="modal_id">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Kit Name:</label>
                    <input type="text" name="kit_name" id="modal_name" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                        <select name="brandid" id="modal_brand" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">-- Select --</option>
                            <?php echo getoptions($conn, 'dim_brand', 'id', 'name', 'section', 'kit'); ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Status:</label>
                        <select name="statusid" id="modal_status" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <?php echo getoptions2($conn, 'dim_category', 'id', 'label', 'section','kitinventory', 'module','status'); ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Date Bought:</label>
                        <input type="date" name="datebought" id="modal_date" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Price:</label>
                        <input type="number" step="1" name="pricebought" id="modal_price" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                    <textarea name="notes" id="modal_notes" rows="3" class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
                </div>

                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Save Changes</button>
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
        
        // Use flex to show the modal
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').style.display = 'flex'; 
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeEditModal();
        }
    }
</script>
</body>
</html>