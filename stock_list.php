<?php
// stock_list.php

global $connection; // Access the global database connection

if (!is_logged_in()) {
    flash_message('error', 'Please log in to view stock status.');
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Define possible voucher statuses for filtering
$possible_statuses = ['All', 'Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

// Get current filter and search terms
$filter_status = $_GET['status'] ?? 'All';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code'; // Default search column

// Build the WHERE clause for filtering and searching
$where_clauses = [];
$bind_params = '';
$bind_values = [];

if ($filter_status !== 'All') {
    $where_clauses[] = "v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}

if (!empty($search_term)) {
    // Sanitize search column to prevent SQL injection for column names
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

// All users can now see all vouchers, so no user-specific filtering here.

$sql = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, v.created_at,
               r_origin.region_name AS origin_region_name,
               r_dest.region_name AS destination_region_name
        FROM vouchers v
        JOIN regions r_origin ON v.region_id = r_origin.id
        JOIN regions r_dest ON v.destination_region_id = r_dest.id";


if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY v.created_at DESC";

$stmt = mysqli_prepare($connection, $sql);

if ($stmt) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vouchers = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Database query failed: ' . mysqli_error($connection));
    $vouchers = []; // Ensure $vouchers is an empty array on error
}


include_template('header', ['page' => 'stock_list']);
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Voucher Status List</h2>

    <!-- Filter and Search Form -->
    <form action="index.php" method="GET" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-inner flex flex-wrap items-center gap-4">
        <input type="hidden" name="page" value="stock_list">

        <div>
            <label for="filter_status" class="block text-sm font-medium text-gray-700">Filter by Status:</label>
            <select id="filter_status" name="status" class="form-select mt-1" onchange="this.form.submit()">
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
                <button type="submit" class="btn bg-indigo-600 hover:bg-indigo-700 text-white ml-2 px-4 py-2 rounded-md">Search</button>
            </div>
        </div>
    </form>

    <?php if (empty($vouchers)): ?>
        <p class="text-center text-gray-600">No vouchers found matching your criteria.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 shadow-sm rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Voucher Code</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Sender</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Receiver</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Origin Region</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Destination Region</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Created At</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr>
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
                                            case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                            case 'Returned': echo 'bg-purple-100 text-purple-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>">
                                    <?php echo htmlspecialchars($voucher['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800"><?php echo format_date($voucher['created_at']); ?></td>
                            <td class="py-3 px-4 whitespace-nowrap text-sm font-medium">
                                <a href="index.php?page=stock_edit&id=<?php echo htmlspecialchars($voucher['id']); ?>"
                                   class="text-indigo-600 hover:text-indigo-900 mr-3">Edit Status</a>
                                <a href="index.php?page=voucher_view&id=<?php echo htmlspecialchars($voucher['id']); ?>"
                                   class="text-green-600 hover:text-green-900">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_template('footer'); ?>
