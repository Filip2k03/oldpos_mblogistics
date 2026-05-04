<?php
// status_bulk_update.php - Allows bulk updating of voucher statuses.

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
    <title>Voucher Status Bulk Update</title>
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

// Dummy is_admin function for demonstration
if (!function_exists('is_admin')) {
    function is_admin() {
        return ($_SESSION['user_type'] ?? '') === 'Admin';
    }
}

global $connection; // Access the global database connection

include_template('header', ['page' => 'status_bulk_update']);

if (!is_logged_in()) {
    flash_message('error', 'Please log in to access this page.');
    redirect('index.php?page=login');
}

// Removed: Restrict access - All logged-in users can now perform bulk updates.
/*
if (!is_admin()) {
    flash_message('error', 'You do not have permission to access this page.');
    redirect('index.php?page=dashboard'); // Or 'voucher_list'
}
*/

// Define possible voucher statuses for dropdown and updates
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned']; // Added 'Received'
// Define possible search columns
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];


// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_region_id = $_GET['region_id'] ?? 'All'; // Filter by region (origin or destination)
$filter_status = $_GET['status'] ?? 'All';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code'; // Default search column


$errors = [];
$vouchers = [];

// Fetch user's region if applicable (Copied from voucher_list.php)
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$user_region_id = null;

if ($user_id && ($user_type === 'Myanmar' || $user_type === 'Malay')) {
    $stmt_user_region = mysqli_prepare($connection, "SELECT region_id FROM users WHERE id = ?");
    if ($stmt_user_region) {
        mysqli_stmt_bind_param($stmt_user_region, 'i', $user_id);
        mysqli_stmt_execute($stmt_user_region);
        mysqli_stmt_bind_result($stmt_user_region, $region_id_result);
        mysqli_stmt_fetch($stmt_user_region);
        $user_region_id = $region_id_result;
        mysqli_stmt_close($stmt_user_region);
    } else {
        $errors[] = 'Error fetching user region: ' . mysqli_error($connection);
    }
}

// Fetch all regions for the filter dropdown (Copied from voucher_list.php)
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


// --- Handle POST request for bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_voucher_ids = $_POST['selected_vouchers'] ?? [];
    $new_status = trim($_POST['new_status'] ?? '');
    $bulk_notes = trim($_POST['bulk_notes'] ?? '');

    if (empty($selected_voucher_ids)) {
        $errors[] = 'No vouchers selected for update.';
    }
    if (!in_array($new_status, $possible_statuses)) {
        $errors[] = 'Invalid status selected for bulk update.';
    }

    if (empty($errors)) {
        mysqli_begin_transaction($connection);
        try {
            // Prepare the update statement
            $update_sql = "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($connection, $update_sql);

            if (!$stmt_update) {
                throw new Exception("Failed to prepare update statement: " . mysqli_error($connection));
            }

            foreach ($selected_voucher_ids as $voucher_id) {
                $voucher_id = intval($voucher_id);
                if ($voucher_id > 0) {
                    // BEFORE updating, verify that the user is allowed to update this specific voucher
                    // (i.e., it originates from their region if they are Myanmar/Malay)
                    $verification_query = "SELECT COUNT(*) FROM vouchers WHERE id = ?";
                    $bind_verification_params = 'i';
                    $bind_verification_values = [$voucher_id];

                    if (($user_type === 'Myanmar' || $user_type === 'Malay') && $user_region_id) {
                        $verification_query .= " AND region_id = ?";
                        $bind_verification_params .= 'i';
                        $bind_verification_values[] = $user_region_id;
                    }

                    $stmt_verify = mysqli_prepare($connection, $verification_query);
                    if ($stmt_verify) {
                        mysqli_stmt_bind_param($stmt_verify, $bind_verification_params, ...$bind_verification_values);
                        mysqli_stmt_execute($stmt_verify);
                        mysqli_stmt_bind_result($stmt_verify, $count);
                        mysqli_stmt_fetch($stmt_verify);
                        mysqli_stmt_close($stmt_verify);

                        if ($count == 0) {
                            // This voucher does not exist or does not belong to the user's region. Skip or throw error.
                            // For bulk updates, skipping is usually better than failing the whole batch.
                            error_log("Attempt to update unauthorized voucher ID: {$voucher_id} by user {$user_id}");
                            continue; // Skip this voucher and proceed with others
                        }
                    } else {
                        throw new Exception("Failed to prepare voucher verification statement: " . mysqli_error($connection));
                    }

                    // Bind parameters for each update
                    mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $bulk_notes, $voucher_id);
                    if (!mysqli_stmt_execute($stmt_update)) {
                        throw new Exception("Failed to update voucher ID {$voucher_id}: " . mysqli_stmt_error($stmt_update));
                    }
                }
            }
            mysqli_stmt_close($stmt_update);
            mysqli_commit($connection);
            flash_message('success', count($selected_voucher_ids) . ' vouchers updated successfully to "' . htmlspecialchars($new_status) . '".');
            // Redirect back to the same page with current filters to see updated results
            redirect('index.php?page=status_bulk_update&' . http_build_query($_GET));
            exit();

        } catch (Exception $e) {
            mysqli_rollback($connection);
            flash_message('error', 'Bulk update failed: ' . $e->getMessage());
            // Fall through to display the page with error message
        }
    } else {
        flash_message('error', implode('<br>', $errors));
        // Fall through to display the page with error message
    }
}

// --- Fetch Vouchers based on filters (for initial load and after POST) ---
$where_clauses = [];
$bind_params = '';
$bind_values = [];

// Apply user-specific origin region filter first for Myanmar/Malay users
if (($user_type === 'Myanmar' || $user_type === 'Malay') && $user_region_id) {
    $where_clauses[] = "v.region_id = ?";
    $bind_params .= 'i';
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

// Region filter (applies to either origin or destination region, UNLESS user-specific filter is active)
if ($filter_region_id !== 'All' && is_numeric($filter_region_id)) {
    // If a user-specific origin filter is already applied, this filter should only apply to destination
    // Otherwise, it applies to both origin OR destination
    if (($user_type === 'Myanmar' || $user_type === 'Malay') && $user_region_id) {
           // User-specific origin filter is active, so the region filter applies only to destination
        $where_clauses[] = "v.destination_region_id = ?";
        $bind_params .= 'i';
        $bind_values[] = intval($filter_region_id);
    } else {
        // No user-specific origin filter, so the region filter applies to either origin or destination
        $where_clauses[] = "(v.region_id = ? OR v.destination_region_id = ?)";
        $bind_params .= 'ii';
        $bind_values[] = intval($filter_region_id);
        $bind_values[] = intval($filter_region_id);
    }
}

// Status filter
if ($filter_status !== 'All') {
    $where_clauses[] = "v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}

// Search term filter
if (!empty($search_term)) {
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

$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, v.created_at,
                    r_origin.region_name AS origin_region_name,
                    r_dest.region_name AS destination_region_name
           FROM vouchers v
           JOIN regions r_origin ON v.region_id = r_origin.id
           JOIN regions r_dest ON v.destination_region_id = r_dest.id";

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
    while ($row = mysqli_fetch_assoc($result)) {
        $vouchers[] = $row;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching vouchers: ' . mysqli_error($connection));
}

// Display any accumulated errors (from post-back as well)
if (!empty($errors)) {
    // Already handled by flash_message in POST block, but good to have a fallback
}

?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Voucher Status Bulk Update</h2>

    <form action="index.php" method="GET" class="mb-6 bg-blue-100 p-4 rounded-lg shadow-inner flex flex-wrap items-center gap-4">
        <input type="hidden" name="page" value="status_bulk_update">

        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-input mt-1" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-input mt-1" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>

        <?php if (!is_admin()): // Only show region filter if not an admin (as admin sees all, or if user is Myanmar/Malay, their filter is auto-applied) ?>
            <?php if (($user_type === 'Myanmar' || $user_type === 'Malay') && $user_region_id) : ?>
                <div>
                    <label for="filter_region_id" class="block text-sm font-medium text-gray-700">Filter by Destination Region:</label>
                    <select id="filter_region_id" name="region_id" class="form-select mt-1">
                        <option value="All">All Destination Regions</option>
                        <?php foreach ($regions as $region_option): ?>
                            <option value="<?php echo htmlspecialchars($region_option['id']); ?>" <?php echo (strval($filter_region_id) === strval($region_option['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region_option['region_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div>
                    <label for="filter_region_id" class="block text-sm font-medium text-gray-700">Filter by Region (Origin/Dest):</label>
                    <select id="filter_region_id" name="region_id" class="form-select mt-1">
                        <option value="All">All Regions</option>
                        <?php foreach ($regions as $region_option): ?>
                            <option value="<?php echo htmlspecialchars($region_option['id']); ?>" <?php echo (strval($filter_region_id) === strval($region_option['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region_option['region_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        <?php else: // Admin users can see all regions, but the filter still works to narrow down?>
            <div>
                <label for="filter_region_id" class="block text-sm font-medium text-gray-700">Filter by Region (Origin/Dest):</label>
                <select id="filter_region_id" name="region_id" class="form-select mt-1">
                    <option value="All">All Regions</option>
                    <?php foreach ($regions as $region_option): ?>
                        <option value="<?php echo htmlspecialchars($region_option['id']); ?>" <?php echo (strval($filter_region_id) === strval($region_option['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region_option['region_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div>
            <label for="filter_status" class="block text-sm font-medium text-gray-700">Filter by Status:</label>
            <select id="filter_status" name="status" class="form-select mt-1">
                <option value="All">All Statuses</option>
                <?php foreach ($possible_statuses as $status_option): ?>
                    <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo ($filter_status === $status_option) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex-grow">
            <label for="search_term" class="block text-sm font-medium text-gray-700">Search:</label>
            <div class="flex mt-1">
                <select name="search_column" class="form-select rounded-r-none border-r-0">
                    <option value="voucher_code" <?php echo ($search_column === 'voucher_code') ? 'selected' : ''; ?>>Voucher Code</option>
                    <option value="sender_name" <?php echo ($search_column === 'sender_name') ? 'selected' : ''; ?>>Sender Name</option>
                    <option value="receiver_name" <?php echo ($search_column === 'receiver_name') ? 'selected' : ''; ?>>Receiver Name</option>
                    <option value="receiver_phone" <?php echo ($search_column === 'receiver_phone') ? 'selected' : ''; ?>>Receiver Phone</option>
                </select>
                <input type="text" id="search_term" name="search" placeholder="Enter search term..."
                        class="form-input flex-grow rounded-l-none" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn bg-indigo-600 hover:bg-indigo-700 text-white ml-2 px-4 py-2 rounded-md">Filter / Search</button>
            </div>
        </div>
    </form>

    <?php if (empty($vouchers)): ?>
        <p class="text-center text-gray-600">No vouchers found matching your criteria.</p>
    <?php else: ?>
        <form action="index.php?page=status_bulk_update&<?php echo http_build_query($_GET); ?>" method="POST" id="bulk_update_form">
            <div class="mb-4 flex flex-wrap items-center gap-4 p-4 bg-yellow-50 rounded-lg shadow-inner">
                <div>
                    <label for="new_status" class="block text-sm font-medium text-gray-700">Set selected to Status:</label>
                    <select id="new_status" name="new_status" class="form-select mt-1" required>
                        <option value="">Select New Status</option>
                        <?php foreach ($possible_statuses as $status_option): ?>
                            <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-grow">
                    <label for="bulk_notes" class="block text-sm font-medium text-gray-700">Notes for Bulk Update (Optional):</label>
                    <input type="text" id="bulk_notes" name="bulk_notes" class="form-input mt-1" placeholder="Add a note for this bulk update">
                </div>
                <div>
                    <button type="submit" class="btn bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md mt-4 md:mt-0">Update Selected Vouchers</button>
                </div>
            </div>

            <div class="bg-lime-500 overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 shadow-sm rounded-lg">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider rounded-tl-lg">
                                <input type="checkbox" id="select_all_vouchers" class="form-checkbox h-4 w-4 text-indigo-600">
                            </th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Voucher Code</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Sender</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Receiver</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Origin Region</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Destination Region</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Current Status</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Created At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">
                                    <input type="checkbox" name="selected_vouchers[]" value="<?php echo htmlspecialchars($voucher['id']); ?>" class="form-checkbox h-4 w-4 text-indigo-600 voucher-checkbox">
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($voucher['voucher_code']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($voucher['sender_name']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">
                                    <?php echo htmlspecialchars($voucher['receiver_name']); ?><br>
                                    <span class="text-gray-500 text-xs"><?php echo htmlspecialchars($voucher['receiver_phone']); ?></span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($voucher['origin_region_name']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($voucher['destination_region_name']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php
                                            switch ($voucher['status']) {
                                                case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'In Transit': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                                case 'Received': echo 'bg-teal-100 text-teal-800'; break; // Added styling for 'Received'
                                                case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                                case 'Returned': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                        ?>">
                                        <?php echo htmlspecialchars($voucher['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($voucher['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select_all_vouchers');
    const voucherCheckboxes = document.querySelectorAll('.voucher-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            voucherCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    // Optional: If you want to uncheck "Select All" if any individual checkbox is unchecked
    voucherCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked && selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            } else if (selectAllCheckbox) {
                // If all are checked, check selectAll
                const allChecked = Array.from(voucherCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        });
    });
});
</script>

<?php include_template('footer'); ?>
