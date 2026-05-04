<?php
// pos/voucher_bulk_update.php - Page for filtering and bulk updating voucher statuses.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Define possible statuses and search columns ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];


// --- Handle POST request for bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucher_ids = $_POST['voucher_ids'] ?? [];
    $new_status = $_POST['new_status'] ?? '';

    if (empty($voucher_ids)) {
        flash_message('error', 'No vouchers were selected for update.');
    } elseif (!in_array($new_status, $possible_statuses)) {
        flash_message('error', 'An invalid status was selected.');
    } else {
        $ids_placeholder = implode(',', array_fill(0, count($voucher_ids), '?'));
        $stmt = mysqli_prepare($connection, "UPDATE vouchers SET status = ? WHERE id IN ($ids_placeholder)");
        
        $types = 's' . str_repeat('i', count($voucher_ids));
        mysqli_stmt_bind_param($stmt, $types, $new_status, ...$voucher_ids);

        if (mysqli_stmt_execute($stmt)) {
            $count = mysqli_stmt_affected_rows($stmt);
            flash_message('success', "$count vouchers were successfully updated to '" . htmlspecialchars($new_status) . "'.");
        } else {
            flash_message('error', 'Failed to update vouchers: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
    // Redirect back to the same page with filters preserved to see the result
    redirect('index.php?page=voucher_bulk_update&' . http_build_query($_GET));
}


// --- Fetch Data for Filters and Display ---
$regions = [];
$vouchers = [];

// Fetch regions for the filter dropdown
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_region_id = $_GET['region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

// Build the main query
$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.status, v.created_at,
                 r_origin.region_name AS origin_region,
                 r_dest.region_name AS destination_region
          FROM vouchers v
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
          WHERE 1=1";

$bind_params = '';
$bind_values = [];

// --- Apply Filters ---
if (!empty($start_date)) {
    $query .= " AND DATE(v.created_at) >= ?";
    $bind_params .= 's';
    $bind_values[] = $start_date;
}
if (!empty($end_date)) {
    $query .= " AND DATE(v.created_at) <= ?";
    $bind_params .= 's';
    $bind_values[] = $end_date;
}
if ($filter_region_id !== 'All' && is_numeric($filter_region_id)) {
    $query .= " AND (v.region_id = ? OR v.destination_region_id = ?)";
    $bind_params .= 'ii';
    $bind_values[] = intval($filter_region_id);
    $bind_values[] = intval($filter_region_id);
}
if (!empty($filter_status)) {
    $query .= " AND v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}
if (!empty($search_term) && in_array($search_column, $allowed_search_columns)) {
    $query .= " AND v.$search_column LIKE ?";
    $bind_params .= 's';
    $bind_values[] = '%' . $search_term . '%';
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
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching vouchers: ' . mysqli_error($connection));
}

include_template('header', ['page' => 'voucher_bulk_update']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Voucher Bulk Status Update</h1>

    <!-- Advanced Filter Form -->
    <form action="index.php" method="GET" class="mb-6 bg-blue-50 p-4 rounded-lg shadow-inner flex flex-wrap items-center gap-4">
        <input type="hidden" name="page" value="voucher_bulk_update">
        <div>
            <label for="start_date" class="form-label-sm">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-input mt-1" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div>
            <label for="end_date" class="form-label-sm">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-input mt-1" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div>
            <label for="filter_region_id" class="form-label-sm">Region:</label>
            <select id="filter_region_id" name="region_id" class="form-select mt-1">
                <option value="All">All Regions</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= $region['id'] ?>" <?= (strval($filter_region_id) === strval($region['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($region['region_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter_status" class="form-label-sm">Status:</label>
            <select id="filter_status" name="status" class="form-select mt-1">
                <option value="">All Statuses</option>
                <?php foreach ($possible_statuses as $status): ?>
                    <option value="<?= $status ?>" <?= ($filter_status === $status) ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-grow">
            <label for="search_term" class="form-label-sm">Search:</label>
            <div class="flex mt-1">
                <select name="search_column" class="form-select rounded-r-none border-r-0">
                    <option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?>>Voucher Code</option>
                    <option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?>>Sender</option>
                    <option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?>>Receiver</option>
                </select>
                <input type="text" name="search" placeholder="Enter search term..." class="form-input flex-grow rounded-l-none" value="<?= htmlspecialchars($search_term) ?>">
            </div>
        </div>
        <div class="self-end">
            <button type="submit" class="btn">Filter</button>
        </div>
    </form>

    <!-- Main Content and Bulk Actions Form -->
    <form action="index.php?page=voucher_bulk_update&<?= http_build_query($_GET) ?>" method="POST" id="bulk-update-form">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="mb-4 flex flex-wrap items-center gap-4 p-4 bg-yellow-50 rounded-lg shadow-inner">
                <label for="new_status" class="font-semibold">Set selected to:</label>
                <select id="new_status" name="new_status" class="form-select w-auto" required>
                    <option value="">Select New Status</option>
                    <?php foreach ($possible_statuses as $status): ?>
                        <option value="<?= $status ?>"><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secondary">Update Selected</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="table-header w-4"><input type="checkbox" id="select-all-vouchers"></th>
                            <th class="table-header">Voucher Code</th>
                            <th class="table-header">Sender/Receiver</th>
                            <th class="table-header">Origin/Destination</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No vouchers found matching your criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td class="table-cell"><input type="checkbox" name="voucher_ids[]" value="<?= $voucher['id'] ?>" class="voucher-checkbox"></td>
                                    <td class="table-cell font-mono text-indigo-600"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                                    <td class="table-cell">
                                        <?= htmlspecialchars($voucher['sender_name']) ?><br>
                                        <span class="text-sm text-gray-500">&rarr; <?= htmlspecialchars($voucher['receiver_name']) ?></span>
                                    </td>
                                    <td class="table-cell">
                                        <?= htmlspecialchars($voucher['origin_region'] ?? 'N/A') ?><br>
                                        <span class="text-sm text-gray-500">&rarr; <?= htmlspecialchars($voucher['destination_region'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="table-cell">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $voucher['status'])) ?>">
                                            <?= htmlspecialchars($voucher['status']) ?>
                                        </span>
                                    </td>
                                    <td class="table-cell"><?= date('Y-m-d', strtotime($voucher['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('select-all-vouchers');
    const voucherCheckboxes = document.querySelectorAll('.voucher-checkbox');

    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            voucherCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
});
</script>

<?php
include_template('footer');
?>