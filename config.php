<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$hostname = "localhost";
$username = "oldmbpos_usrS";
$password = "zzZywznDaxB8YpET";
$database = "oldmbpos";

// Establish the database connection
$connection = mysqli_connect($hostname, $username, $password, $database);

// Check connection
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
} else {
    // echo "Connected successfully"; // Uncomment for debugging
}

// Application Configuration
define('APP_NAME', 'MBLOGISTICS POS');

// Voucher Code Configuration
define('VOUCHER_CODE_LENGTH', 7); // e.g., 0000001
define('USER_TYPE_ADMIN', 'ADMIN');
define('USER_TYPE_MYANMAR', 'Myanmar'); // Assuming 'Myanmar' is the user_type string
define('USER_TYPE_MALAY', 'Malay');     // Assuming 'Malay' is the user_type string
?>