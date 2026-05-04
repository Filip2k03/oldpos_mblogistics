<?php
// db_connect.php
require_once 'config.php'; // Include your configuration file

$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}
?>