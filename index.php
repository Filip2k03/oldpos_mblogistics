<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// index.php
// This acts as the main router for the application

session_start();

// Load configuration and database connection first
require_once 'config.php';
// Load common functions
require_once 'includes/functions.php';

// Get the requested page from the URL
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Define routes and required authentication levels
$routes = [
    'login' => ['file' => 'login.php', 'auth' => 'none'],
    'register' => ['file' => 'register.php', 'auth' => 'none'],
    'dashboard' => ['file' => 'dashboard.php', 'auth' => 'user'],
    'admin_dashboard' => ['file' => 'admin_dashboard.php', 'auth' => 'admin'],
    'voucher_create' => ['file' => 'voucher_create.php', 'auth' => 'user'],
    'voucher_list' => ['file' => 'voucher_list.php', 'auth' => 'user'],
    'voucher_view' => ['file' => 'voucher_view.php', 'auth' => 'user'],
    'export_vouchers' => ['file' => 'export_vouchers.php', 'auth' => 'user'],
    'status_bulk_update' => ['file' => 'status_bulk_update.php', 'auth' => 'user'], // Changed to user auth
    'stock_list' => ['file' => 'stock_list.php', 'auth' => 'user'],
    'stock_edit' => ['file' => 'stock_edit.php', 'auth' => 'user'],
    'expenses' => ['file' => 'expenses.php', 'auth' => 'user'], // Changed to user auth
    'other_income' => ['file' => 'other_income.php', 'auth' => 'admin'], // Remains admin
    'profit_loss' => ['file' => 'profit_loss.php', 'auth' => 'admin'],
    'logout' => ['file' => 'logout.php', 'auth' => 'user'],
    'home' => ['file' => 'home.php', 'auth' => 'user'] // Default after login
];

// Handle authentication
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

// Determine if user is logged in
$is_logged_in = ($user_id !== null);

// Check if the requested route exists
if (!array_key_exists($page, $routes)) {
    // Page not found, redirect to login or dashboard
    if ($is_logged_in) {
        header('Location: index.php?page=dashboard');
    } else {
        header('Location: index.php?page=login');
    }
    exit();
}

$route = $routes[$page];

// Authentication logic (This happens BEFORE including the page file)
if ($route['auth'] === 'user' && !$is_logged_in) {
    flash_message('error', 'You must be logged in to access this page.');
    header('Location: index.php?page=login');
    exit();
}

if ($route['auth'] === 'admin' && ($user_type !== 'ADMIN' || !$is_logged_in)) {
    flash_message('error', 'You must be an ADMIN to access this page.');
    header('Location: index.php?page=dashboard'); // Redirect non-admins to dashboard
    exit();
}

// Include the requested page file
// No HTML output or header modifications should happen before this point for redirects to work.
require_once $route['file'];
?>
