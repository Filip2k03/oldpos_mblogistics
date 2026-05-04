<?php
// status_edit.php - Handles updating the status of a specific voucher.

global $connection; // Access the global database connection

if (!is_logged_in()) {
    flash_message('error', 'Please log in to edit voucher status.');
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

$voucher_id = intval($_GET['id'] ?? 0);

if ($voucher_id <= 0) {
    flash_message('error', 'Invalid voucher ID.');
    redirect('index.php?page=voucher_list'); // Redirect if no valid ID
}

// Define possible voucher statuses for dropdown
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

$voucher = null; // Initialize voucher data

// Fetch voucher details for display and update
$sql = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, v.notes,
               r_origin.region_name AS origin_region_name,
               r_dest.region_name AS destination_region_name,
               v.created_by_user_id
        FROM vouchers v
        JOIN regions r_origin ON v.region_id = r_origin.id
        JOIN regions r_dest ON v.destination_region_id = r_dest.id
        WHERE v.id = ?";

// Security check: allows ADMIN to edit any, regular user to edit only their own
if ($user_type !== 'ADMIN') {
    $sql .= " AND v.created_by_user_id = ?";
}

$stmt = mysqli_prepare($connection, $sql);

if ($stmt) {
    if ($user_type !== 'ADMIN') {
        mysqli_stmt_bind_param($stmt, 'ii', $voucher_id, $user_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $voucher_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $voucher = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Database query failed: ' . mysqli_error($connection));
    redirect('index.php?page=voucher_list'); // Redirect on query preparation failure
}

if (!$voucher) {
    flash_message('error', 'Voucher not found or you do not have permission to edit it.');
    redirect('index.php?page=voucher_list'); // Redirect if voucher not found or no permission
}

// --- Handle POST request (Form Submission for Status Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = trim($_POST['status'] ?? '');
    $notes_update = trim($_POST['notes'] ?? $voucher['notes']); // Allow notes to be updated too

    $errors = [];
    if (!in_array($new_status, $possible_statuses)) {
        $errors[] = 'Invalid status selected.';
    }

    if (!empty($errors)) {
        flash_message('error', implode('<br>', $errors));
        // No redirect here, let the page reload with errors
    } else {
        $update_sql = "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?";
        // Add security check for update as well
        if ($user_type !== 'ADMIN') {
            $update_sql .= " AND created_by_user_id = ?";
        }

        $stmt_update = mysqli_prepare($connection, $update_sql);

        if ($stmt_update) {
            if ($user_type !== 'ADMIN') {
                mysqli_stmt_bind_param($stmt_update, 'ssii', $new_status, $notes_update, $voucher_id, $user_id);
            } else {
                mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $notes_update, $voucher_id);
            }

            if (mysqli_stmt_execute($stmt_update)) {
                flash_message('success', 'Voucher status updated successfully!');
                redirect('index.php?page=voucher_view&id=' . $voucher_id); // Redirect back to voucher view
            } else {
                flash_message('error', 'Failed to update voucher status: ' . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            flash_message('error', 'Failed to prepare update statement: ' . mysqli_error($connection));
        }
    }
}

include_template('header', ['page' => 'status_edit']);
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-2xl mx-auto">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Edit Voucher Status</h2>

    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <h3 class="text-xl font-semibold text-blue-800 mb-2">Voucher Details</h3>
        <p class="text-gray-700 mb-1"><strong>Voucher Code:</strong> <?php echo htmlspecialchars($voucher['voucher_code']); ?></p>
        <p class="text-gray-700 mb-1"><strong>Sender:</strong> <?php echo htmlspecialchars($voucher['sender_name']); ?></p>
        <p class="text-gray-700 mb-1"><strong>Receiver:</strong> <?php echo htmlspecialchars($voucher['receiver_name']); ?> (<?php echo htmlspecialchars($voucher['receiver_phone']); ?>)</p>
        <p class="text-gray-700 mb-1"><strong>Origin Region:</strong> <?php echo htmlspecialchars($voucher['origin_region_name']); ?></p>
        <p class="text-gray-700 mb-1"><strong>Destination Region:</strong> <?php echo htmlspecialchars($voucher['destination_region_name']); ?></p>
        <p class="text-gray-700"><strong>Current Status:</strong>
            <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full
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
        </p>
        <?php if (!empty($voucher['notes'])): ?>
            <p class="text-gray-700 mt-2"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($voucher['notes'])); ?></p>
        <?php endif; ?>
    </div>

    <form action="index.php?page=status_edit&id=<?php echo htmlspecialchars($voucher['id']); ?>" method="POST">
        <div class="mb-4">
            <label for="status" class="block text-gray-700 text-sm font-semibold mb-2">Update Status:</label>
            <select id="status" name="status" class="form-select" required>
                <?php foreach ($possible_statuses as $status_option): ?>
                    <option value="<?php echo htmlspecialchars($status_option); ?>"
                        <?php echo ($voucher['status'] === $status_option) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-6">
            <label for="notes" class="block text-gray-700 text-sm font-semibold mb-2">Update Notes (Optional):</label>
            <textarea id="notes" name="notes" rows="4" class="form-input"><?php echo htmlspecialchars($voucher['notes'] ?? ''); ?></textarea>
        </div>

        <div class="flex justify-between items-center">
            <a href="index.php?page=voucher_view&id=<?php echo htmlspecialchars($voucher['id']); ?>" class="btn bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md">Cancel</a>
            <button type="submit" class="btn bg-green-600 hover:bg-green-700 text-white px-8 py-3 text-lg rounded-md shadow-md">Update Status</button>
        </div>
    </form>
</div>

<?php include_template('footer'); ?>
