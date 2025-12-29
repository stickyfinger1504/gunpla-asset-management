<?php
// DATABASE CONNECTION
$host = 'db';
$user = 'user01';
$pass = '123456';
$dbname = 'gunpladb';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }


$sql = "CREATE TABLE IF NOT EXISTS transactions (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(50),
    image_path VARCHAR(255) NOT NULL,   
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

$message = "";

// 1. CHECK IF FORM WAS SUBMITTED
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["gunpla_image"])) {
    
    // 2. GET & CLEAN PROJECT NAME
    // We remove weird characters so they don't break the file system
    $raw_project = $_POST['project_name']; 
    $safe_project = preg_replace('/[^a-zA-Z0-9_-]/', '_', $raw_project);
    
    // Default to 'General' if they left it empty
    if(empty($safe_project)) { $safe_project = "General"; }

    // 3. DEFINE THE NEW PATH: assets/ProjectName/
    $base_dir = "transaction_images/"; 
    $target_dir = $base_dir . $safe_project . "/";

    // 4. CREATE SUBFOLDER IF IT DOESN'T EXIST
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["gunpla_image"]["name"]);
    $target_path = $target_dir . $file_name;

    // 5. MOVE THE FILE
    if (move_uploaded_file($_FILES["gunpla_image"]["tmp_name"], $target_path)) {
        
        // 6. SAVE TO DB (Now including the Project Name)
        $stmt = $conn->prepare("INSERT INTO transactions (project_name, image_path) VALUES (?, ?)");
        $stmt->bind_param("ss", $safe_project, $target_path);
        
        if ($stmt->execute()) {
            $message = "✅ Saved to folder: $target_path";
        } else {
            $message = "❌ Database Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "❌ Failed to move file. Check permissions!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gunpla Asset Manager</title>
    <style> body { font-family: sans-serif; padding: 20px; } .msg { background: #eee; padding: 10px; border: 1px solid #ccc; } </style>
</head>
<body>

    <h2>New Transaction</h2>
    <?php if ($message): ?> <div class="msg"><?php echo $message; ?></div> <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        
        <label>Project Name:</label><br>
        <input type="text" name="project_name" placeholder="e.g. Wing Zero" required>
        <br><br>

        <label>Image:</label><br>
        <input type="file" name="gunpla_image" required>
        <br><br>
        
        <input type="submit" value="Upload">
    </form>

    <hr>
    <h3>Gallery</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php
        $result = $conn->query("SELECT * FROM transactions ORDER BY id DESC");
        while ($row = $result->fetch_assoc()) {
            echo '<div style="border: 1px solid #ddd; padding: 5px;">';
            // Display the Project Name above the image
            echo '<strong>' . htmlspecialchars($row['project_name']) . '</strong><br>';
            echo '<img src="' . $row['image_path'] . '" width="150"><br>';
            echo '<small>' . $row['image_path'] . '</small>';
            echo '</div>';
        }
        ?>
    </div>

</body>
</html>