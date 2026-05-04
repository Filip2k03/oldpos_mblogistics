<?php
// voucher_list.php

// Assume these functions are defined elsewhere or create dummy ones for standalone testing
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('flash_message')) {
    function flash_message($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit();
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        // Assuming USER_TYPE_ADMIN is defined in config.php
        return isset($_SESSION['user_type']) && defined('USER_TYPE_ADMIN') && $_SESSION['user_type'] === USER_TYPE_ADMIN;
    }
}

if (!function_exists('include_template')) {
    function include_template($template_name, $data = []) {
        extract($data);
        // Minimal header for demonstration
        if ($template_name === 'header') {
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher List</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .form-input, .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d2d6dc;
            border-radius: 0.375rem;
            background-color: #fff;
            color: #4a5568;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-input:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.45);
            outline: none;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .btn-blue {
            background-color: #4299e1;
            color: white;
        }
        .btn-blue:hover {
            background-color: #3182ce;
        }
        .bg-burgundy {
            background-color: #800020;
        }
        .hover\:bg-burgundy-dark:hover {
            background-color: #66001a;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">';
            // Display flash messages
            if (isset($_SESSION['flash'])) {
                $flash_type = $_SESSION['flash']['type'];
                $flash_message = $_SESSION['flash']['message'];
                echo "<div class='p-4 mb-4 text-sm rounded-lg " . ($flash_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') . "' role='alert'>{$flash_message}</div>";
                unset($_SESSION['flash']);
            }
        } else if ($template_name === 'footer') {
            echo '</body></html>';
        }
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dummy $connection for demonstration. In a real app, this would be a valid mysqli connection.
if (!isset($connection)) {
    // Replace with your actual database connection
    $connection = mysqli_connect('localhost', 'root', '', 'your_database_name');
    if (!$connection) {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// Ensure user type constants are defined (from config.php)
if (!defined('USER_TYPE_ADMIN')) define('USER_TYPE_ADMIN', 'ADMIN');
if (!defined('USER_TYPE_MYANMAR')) define('USER_TYPE_MYANMAR', 'Myanmar');
if (!defined('USER_TYPE_MALAY')) define('USER_TYPE_MALAY', 'Malay');


include_template('header', ['page' => 'voucher_list']);

if (!is_logged_in()) {
    flash_message('error', 'Please log in to view vouchers.');
    redirect('index.php?page=login');
}

global $connection; // Access the global database connection

// Define possible voucher statuses for filtering
$possible_statuses = ['All', 'Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
// --- Changed these parameters ---
$filter_origin_region_id = $_GET['origin_region_id'] ?? 'All'; // Filter by origin region
$filter_destination_region_id = $_GET['destination_region_id'] ?? 'All'; // Filter by destination region
// --- End changes ---
$filter_status = $_GET['status'] ?? 'All';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code'; // Default search column

$vouchers = [];
$errors = [];

// Fetch user's region and type
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$user_region_id = null;

// Fetch user's detailed info including region_id for filtering
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
    } else {
        $errors[] = 'Error fetching user information for filter: ' . mysqli_error($connection);
    }
}

$is_admin = is_admin();


// Fetch all regions for the filter dropdowns
$regions = [];
$stmt_regions = mysqli_query($connection, "SELECT id, region_name, prefix FROM regions ORDER BY region_name");
if ($stmt_regions) {
    while ($row = mysqli_fetch_assoc($stmt_regions)) {
        $regions[] = $row;
    }
    mysqli_free_result($stmt_regions);
} else {
    $errors[] = 'Error loading regions for filter: ' . mysqli_error($connection);
}


// --- Build the SQL query with filters ---
$where_clauses = [];
$bind_params = '';
$bind_values = [];

// Apply user-specific filter for Myanmar/Malay users, UNLESS it's an admin
// This now filters by either origin OR destination matching the user's region
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

// --- New Region filters ---
if ($filter_origin_region_id !== 'All' && is_numeric($filter_origin_region_id)) {
    $where_clauses[] = "v.region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_origin_region_id);
}

if ($filter_destination_region_id !== 'All' && is_numeric($filter_destination_region_id)) {
    $where_clauses[] = "v.destination_region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_destination_region_id);
}
// --- End new region filters ---


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
        // Fallback or error for invalid search column
        $where_clauses[] = "v.voucher_code LIKE ?"; // Default to voucher_code search
        $bind_params .= 's';
        $bind_values[] = '%' . $search_term . '%';
    }
}

$query = "SELECT v.*,
                    r_origin.region_name AS origin_region_name,
                    r_dest.region_name AS destination_region_name,
                    u.username AS created_by_username
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
        // Use call_user_func_array for mysqli_stmt_bind_param as it expects parameters by reference
        // This is crucial when the number of parameters is dynamic
        mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $vouchers[] = $row;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    $errors[] = 'Error fetching vouchers: ' . mysqli_error($connection);
}

// Display any accumulated errors
if (!empty($errors)) {
    flash_message('error', implode('<br>', $errors));
}

?>

<div class="bg-sky-50 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Voucher List</h2>

    <form action="index.php" method="GET" class="bg-sky-100 mb-6 p-4 rounded-lg shadow-inner flex flex-wrap items-end gap-4">
        <input type="hidden" name="page" value="voucher_list">

        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>

        <?php // This filter dropdown is always shown, but the query logic handles user permissions ?>
        <div>
            <label for="filter_origin_region_id" class="block text-sm font-medium text-gray-700">Filter by Origin Region:</label>
            <select id="filter_origin_region_id" name="origin_region_id" class="form-select">
                <option value="All">All Origins</option>
                <?php foreach ($regions as $region_option): ?>
                    <option value="<?php echo htmlspecialchars($region_option['id']); ?>" <?php echo (strval($filter_origin_region_id) === strval($region_option['id'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region_option['region_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="filter_destination_region_id" class="block text-sm font-medium text-gray-700">Filter by Destination Region:</label>
            <select id="filter_destination_region_id" name="destination_region_id" class="form-select">
                <option value="All">All Destinations</option>
                <?php foreach ($regions as $region_option): ?>
                    <option value="<?php echo htmlspecialchars($region_option['id']); ?>" <?php echo (strval($filter_destination_region_id) === strval($region_option['id'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region_option['region_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter_status" class="block text-sm font-medium text-gray-700">Filter by Status:</label>
            <select id="filter_status" name="status" class="form-select">
                <option value="All">All Statuses</option>
                <?php foreach ($possible_statuses as $status_option): ?>
                    <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo ($filter_status === $status_option) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex-grow flex items-end">
            <div class="flex flex-col w-full">
                <label for="search_term" class="block text-sm font-medium text-gray-700">Search:</label>
                <div class="flex w-full">
                    <select name="search_column" class="form-select rounded-r-none border-r-0 max-w-[150px]">
                        <option value="voucher_code" <?php echo ($search_column === 'voucher_code') ? 'selected' : ''; ?>>Voucher Code</option>
                        <option value="sender_name" <?php echo ($search_column === 'sender_name') ? 'selected' : ''; ?>>Sender Name</option>
                        <option value="receiver_name" <?php echo ($search_column === 'receiver_name') ? 'selected' : ''; ?>>Receiver Name</option>
                        <option value="receiver_phone" <?php echo ($search_column === 'receiver_phone') ? 'selected' : ''; ?>>Receiver Phone</option>
                    </select>
                    <input type="text" id="search_term" name="search" placeholder="Enter search term..."
                            class="form-input flex-grow rounded-l-none" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
            </div>
            <button type="submit" class="btn bg-indigo-600 hover:bg-indigo-700 text-white ml-2 px-4 py-2 rounded-md">Filter / Search</button>
        </div>
    </form>

    <div class="flex justify-end mb-4">
        <a href="export_vouchers.php?<?php echo http_build_query($_GET); ?>" class="btn bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-md">Export Vouchers</a>
    </div>

    <?php if (empty($vouchers)): ?>
        <div class="text-center py-10">
            <p class="text-gray-600 text-lg">No vouchers found matching your criteria.
            <?php if (!$is_admin): // Check against the now-defined $is_admin variable ?>
                <a href="index.php?page=voucher_create" class="text-indigo-600 hover:text-indigo-800 font-semibold">Create one now!</a>
            <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="bg-Lime-500 p-6 rounded-lg shadow-inner overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Voucher Code</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sender</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receiver</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($voucher['voucher_code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($voucher['sender_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($voucher['receiver_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo htmlspecialchars($voucher['origin_region_name'] . ' to ' . $voucher['destination_region_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php
                                // Display blank if weight_kg is 0.00
                                if ((float)$voucher['weight_kg'] === 0.00) {
                                    echo '';
                                } else {
                                    echo htmlspecialchars(number_format((float)$voucher['weight_kg'], 2));
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format((float)$voucher['total_amount'], 2) . ' ' . $voucher['currency']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?php
                                        switch ($voucher['status']) {
                                            case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'In Transit': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                            case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                            case 'Returned': echo 'bg-purple-100 text-purple-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>">
                                    <?php echo htmlspecialchars($voucher['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($voucher['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php
                                // Default settings for View link
                                $view_href = 'index.php?page=voucher_view&id=' . htmlspecialchars($voucher['id']);
                                $view_class = 'bg-sky-500 hover:bg-sky-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300';
                                $view_onclick = '';
                                $button_label = 'View';

                                $is_delivered = ($voucher['status'] === 'Delivered');
                                $is_myanmar_or_malay_user = ($user_type === USER_TYPE_MYANMAR || $user_type === USER_TYPE_MALAY);

                                // Logic to disable "View" button
                                $should_disable_view = false;
                                if ($is_admin) {
                                    // Admins can always view, so no need to disable
                                    $should_disable_view = false;
                                } elseif ($is_delivered && $is_myanmar_or_malay_user) {
                                    // Myanmar/Malay users can view if delivered AND their region matches destination
                                    if ($user_region_id !== null && $user_region_id == $voucher['destination_region_id']) {
                                        $should_disable_view = false; // Enable if matching destination
                                    } else {
                                        $should_disable_view = true; // Disable if not matching destination
                                    }
                                } elseif ($is_delivered && !$is_myanmar_or_malay_user) {
                                    // Non-Myanmar/Malay user cannot view delivered vouchers
                                    $should_disable_view = true;
                                }

                                if ($should_disable_view) {
                                    $view_href = '#'; // Prevent actual navigation
                                    $view_class = 'bg-gray-300 text-gray-600 cursor-not-allowed font-semibold py-2 px-4 rounded-lg shadow-md'; // Disabled style
                                    $view_onclick = 'event.preventDefault(); alert(\'You do not have permission to view this voucher.\');'; // User feedback
                                    $button_label = 'No View'; // Change label to indicate disabled for visual clarity
                                }
                                ?>
                                <a href="<?php echo $view_href; ?>" class="<?php echo $view_class; ?>" <?php echo $view_onclick ? 'onclick="' . $view_onclick . '"' : ''; ?>>
                                    <?php echo $button_label; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_template('footer'); ?>
