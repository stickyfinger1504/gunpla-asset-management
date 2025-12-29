<?php

$server=getenv('DB_HOST');
$user=getenv('DB_USER');
$database=getenv('DB_NAME');
$password=getenv('DB_PASSWORD');

if (!$server || !$user || !$password || !$database) {
    die("Database config missing! Check your .env file.");
}

$conn = new mysqli($server,$user,$password,$database);

if($conn->connect_error){
    die("Connection Failed: " . $conn->connect_error);
}
?>