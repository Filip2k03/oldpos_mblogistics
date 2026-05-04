<?php
// logout.php
session_start();
// Unset all session variables 
session_unset();
// Destroy the session
session_destroy();
// Redirect to login page with a success message
header('Location: index.php?page=login&message=Logged out successfully');
exit();
