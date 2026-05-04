<?php
// export_vouchers.php - Exports filtered voucher data to a CSV file.

// Load configuration and database connection first
require_once 'config.php';
// Load common functions
require_once 'includes/functions.php';

session_start(); // Start session to access user authentication

if (!is_logged_in()) {
    // If not logged in, redirect to login page
    flash_message('error', 'Please log in to export vouchers.');
    redirect('index.php?page=login');
    exit();
}

global $connection; // Access the global database connection

// Define possible voucher statuses for filtering (must match voucher_list.php)
$possible_statuses = ['All', 'Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

// Get filter parameters from GET request (these will determine what gets exported)
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_origin_region_id = $_GET['origin_region_id'] ?? 'All';
$filter_destination_region_id = $_GET['destination_region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

// Fetch user's region and type for user-specific filtering
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$user_region_id = null;

if ($user_id) {
    $stmt_user_info = mysqli_prepare($connection, "SELECT user_type, region_id FROM users WHERE id = ?");
    if ($stmt_user_info) {
        mysqli_stmt_bind_param($stmt_user_info, 'i', $user_id);
        mysqli_stmt_execute($stmt_user_info);
        $result_user_info = mysqli_stmt_get_result($stmt_user_info);
        if ($user_info = mysqli_fetch_assoc($result_user_info)) {
            $user_type = $user_info['user_type'];
            $user_region_id = $user_info['region_id'];
        }
        mysqli_free_result($result_user_info);
        mysqli_stmt_close($stmt_user_info);
    }
}

$is_admin = (isset($_SESSION['user_type']) && defined('USER_TYPE_ADMIN') && $_SESSION['user_type'] === USER_TYPE_ADMIN);


// --- Build the SQL query with filters (same logic as voucher_list.php) ---
$where_clauses = [];
$bind_params = '';
$bind_values = [];

// Apply user-specific filter for Myanmar/Malay users (origin OR destination matching user's region)
if (!$is_admin && ($user_type === USER_TYPE_MYANMAR || $user_type === USER_TYPE_MALAY) && $user_region_id) {
    $where_clauses[] = "(v.region_id = ? OR v.destination_region_id = ?)";
    $bind_params .= 'ii';
    $bind_values[] = $user_region_id;
    $bind_values[] = $user_region_id;
}


// Date range filter
if (!empty($start_date)) {
    $where_clauses[] = "DATE(v.created_at) >= ?";
    $bind_params .= 's';
    $bind_values[] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(v.created_at) <= ?";
    $bind_params .= 's';
    $bind_values[] = $end_date;
}

// Separate Origin Region filter
if ($filter_origin_region_id !== 'All' && is_numeric($filter_origin_region_id)) {
    $where_clauses[] = "v.region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_origin_region_id);
}

// Separate Destination Region filter
if ($filter_destination_region_id !== 'All' && is_numeric($filter_destination_region_id)) {
    $where_clauses[] = "v.destination_region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_destination_region_id);
}


// Status filter
if ($filter_status !== 'All') {
    $where_clauses[] = "v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}

// Search term filter
if (!empty($search_term)) {
    $allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];
    if (in_array($search_column, $allowed_search_columns)) {
        $where_clauses[] = "$search_column LIKE ?";
        $bind_params .= 's';
        $bind_values[] = '%' . $search_term . '%';
    } else {
        // Fallback for invalid search column
        $where_clauses[] = "v.voucher_code LIKE ?";
        $bind_params .= 's';
        $bind_values[] = '%' . $search_term . '%';
    }
}

// MODIFIED: Only select the columns you want to output
$query = "SELECT v.voucher_code, v.sender_name, v.receiver_name,
                  v.weight_kg, v.delivery_charge, v.total_amount,
                  v.currency, v.delivery_type
           FROM vouchers v
           JOIN regions r_origin ON v.region_id = r_origin.id
           JOIN regions r_dest ON v.destination_region_id = r_dest.id
           JOIN users u ON v.created_by_user_id = u.id";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " ORDER BY v.created_at DESC";

$stmt = mysqli_prepare($connection, $query);

if ($stmt) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // --- Prepare for CSV download ---
    $filename = "vouchers_export_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // MODIFIED: Write CSV Headers to match the desired output
    fputcsv($output, [
        'Voucher Code', 'Sender Name', 'Receiver Name',
        'Weight (kg)', 'Delivery Charge', 'Total Amount',
        'Currency', 'Delivery Type'
    ]);

    // MODIFIED: Write data rows to match the desired output
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['voucher_code'],
            $row['sender_name'],
            $row['receiver_name'],
            $row['weight_kg'],
            $row['delivery_charge'],
            $row['total_amount'],
            $row['currency'],
            $row['delivery_type'],
        ]);
    }

    fclose($output);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    exit(); // Important: Stop script execution after file download
} else {
    // If query preparation fails, redirect with an error message
    flash_message('error', 'Error preparing export data: ' . mysqli_error($connection));
    redirect('index.php?page=voucher_list');
    exit();
}
?>