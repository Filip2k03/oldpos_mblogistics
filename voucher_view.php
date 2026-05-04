<?php
// voucher_view.php - Displays details of a specific voucher and allows status/notes update.

// --- Include Configuration and Database Connection ---
require_once 'config.php'; // Make sure the path to config.php is correct

// --- Dummy Functions (These should ideally be in a shared 'helpers.php' or 'functions.php') ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dummy is_logged_in function
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

// Dummy flash_message function
if (!function_exists('flash_message')) {
    function flash_message($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

// Dummy redirect function
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit();
    }
}

// Dummy is_admin function (assuming user_type 'ADMIN' means admin)
if (!function_exists('is_admin')) {
    function is_admin() {
        // Assuming USER_TYPE_ADMIN is defined in config.php
        return isset($_SESSION['user_type']) && defined('USER_TYPE_ADMIN') && $_SESSION['user_type'] === USER_TYPE_ADMIN;
    }
}

// Dummy include_template function (for header/footer, adjust as per your template system)
if (!function_exists('include_template')) {
    function include_template($template_name, $data = []) {
        extract($data); // Makes array keys available as variables in the included file
        if ($template_name === 'header') {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>View Voucher</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
                <style>
                    /* Styles for flash messages to ensure they are visible and fade out */
                    .flash-message {
                        padding: 0.75rem 1.25rem;
                        margin-bottom: 1rem;
                        border: 1px solid transparent;
                        border-radius: 0.25rem;
                        opacity: 1;
                        transition: opacity 0.5s ease-out;
                    }
                    .flash-message.hidden {
                        opacity: 0;
                    }
                </style>
            </head>
            <body class="bg-gray-100 p-6">
            <div class="container mx-auto">
            <?php
            // Display flash messages if any (adapted from voucher_list.php)
            if (isset($_SESSION['flash'])) {
                $flash_type = $_SESSION['flash']['type'];
                $flash_message = $_SESSION['flash']['message'];
                $class = '';
                if ($flash_type === 'success') {
                    $class = 'bg-green-100 text-green-700 border-green-400';
                } elseif ($flash_type === 'error') {
                    $class = 'bg-red-100 text-red-700 border-red-400';
                } elseif ($flash_type === 'info') {
                    $class = 'bg-blue-100 text-blue-700 border-blue-400';
                } elseif ($flash_type === 'warning') {
                    $class = 'bg-yellow-100 text-yellow-700 border-yellow-400';
                }
                echo "<div class='flash-message p-4 mb-4 text-sm rounded-lg border {$class}' role='alert'>{$flash_message}</div>";
                unset($_SESSION['flash']); // Clear the flash message
                ?>
                <script>
                    // Automatically hide flash messages after a few seconds
                    document.querySelectorAll('.flash-message').forEach(messageDiv => {
                        setTimeout(() => {
                            messageDiv.classList.add('hidden'); // Add hidden class for CSS transition
                            setTimeout(() => messageDiv.remove(), 500); // Remove from DOM after transition
                        }, 3000); // 3 seconds
                    });
                </script>
                <?php
            }
        } elseif ($template_name === 'footer') {
            echo '</div></body></html>';
        }
    }
}


// Ensure user type constants are defined (from config.php or functions.php)
if (!defined('USER_TYPE_ADMIN')) define('USER_TYPE_ADMIN', 'ADMIN');
if (!defined('USER_TYPE_MYANMAR')) define('USER_TYPE_MYANMAR', 'Myanmar'); // Check your DB for exact casing
if (!defined('USER_TYPE_MALAY')) define('USER_TYPE_MALAY', 'Malay');       // Check your DB for exact casing


// --- Authentication Check ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to view vouchers.');
    redirect('index.php?page=login');
    exit();
}

// Get voucher ID from URL
$voucher_id = intval($_GET['id'] ?? 0); // Use intval for security

if ($voucher_id <= 0) { // Check for valid integer ID
    flash_message('error', 'Invalid voucher ID provided.');
    redirect('index.php?page=voucher_list');
    exit();
}

global $connection; // Access the global database connection

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? null; // Ensure user_type is set
$is_admin = is_admin();

// Fetch user's region ID (needed for the new permission check)
$user_region_id = null;
$stmt_user_region = mysqli_prepare($connection, "SELECT region_id FROM users WHERE id = ?");
if ($stmt_user_region) {
    mysqli_stmt_bind_param($stmt_user_region, 'i', $user_id);
    mysqli_stmt_execute($stmt_user_region);
    $result_user_region = mysqli_stmt_get_result($stmt_user_region);
    if ($row = mysqli_fetch_assoc($result_user_region)) {
        $user_region_id = $row['region_id'];
    }
    mysqli_free_result($result_user_region);
    mysqli_stmt_close($stmt_user_region);
}


// Define possible voucher statuses for dropdown
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

$voucher = null;
$breakdowns = [];
$total_breakdown_kg = 0.00; // Initialize total kg from breakdown items

// --- Handle POST request (Form Submission for Status Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = trim($_POST['status'] ?? '');
    $notes_update = trim($_POST['notes'] ?? '');

    $errors = [];
    if (!in_array($new_status, $possible_statuses)) {
        $errors[] = 'Invalid status selected.';
    }

    // Fetch current voucher details to verify permission before update
    $auth_check_sql = "SELECT created_by_user_id, status, destination_region_id FROM vouchers WHERE id = ?";
    $stmt_auth_check = mysqli_prepare($connection, $auth_check_sql);
    if ($stmt_auth_check) {
        mysqli_stmt_bind_param($stmt_auth_check, 'i', $voucher_id);
        mysqli_stmt_execute($stmt_auth_check);
        $result_auth_check = mysqli_stmt_get_result($stmt_auth_check);
        $voucher_current_data = mysqli_fetch_assoc($result_auth_check);
        mysqli_free_result($result_auth_check);
        mysqli_stmt_close($stmt_auth_check);

        if (!$voucher_current_data) {
            $errors[] = 'Voucher not found for permission check.';
        } else {
            $can_update = false;
            // Admins, Myanmar, and Malay users can always update
            if ($is_admin || $user_type === USER_TYPE_MYANMAR || $user_type === USER_TYPE_MALAY) {
                $can_update = true;
            }
            // Creator can update
            elseif ($voucher_current_data['created_by_user_id'] == $user_id) {
                $can_update = true;
            }

            if (!$can_update) {
                $errors[] = 'You do not have permission to update this voucher.';
            }
        }
    } else {
        $errors[] = 'Database error during permission check: ' . mysqli_error($connection);
    }

    if (!empty($errors)) {
        flash_message('error', implode('<br>', $errors));
        // No redirect here, so the user can see the errors and the form with current values
    } else {
        $update_sql = "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($connection, $update_sql);

        if ($stmt_update) {
            mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $notes_update, $voucher_id);
            if (mysqli_stmt_execute($stmt_update)) {
                flash_message('success', 'Voucher status and notes updated successfully!');
                // No redirect needed here, the GET logic below will re-fetch and display
            } else {
                flash_message('error', 'Failed to update voucher: ' . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            flash_message('error', 'Failed to prepare update statement: ' . mysqli_error($connection));
        }
    }
}

// --- Fetch Voucher Details (for GET request or after POST) ---
try {
    // Select all necessary columns from the vouchers table
    $query_voucher = "SELECT
                        v.id,
                        v.voucher_code,
                        v.sender_name,
                        v.sender_phone,
                        v.sender_address,
                        v.use_sender_address_for_checkout,
                        v.receiver_name,
                        v.receiver_phone,
                        v.receiver_address,
                        v.payment_method,
                        v.weight_kg,
                        v.price_per_kg_at_voucher,
                        v.delivery_charge,
                        v.total_amount,
                        v.currency,
                        v.delivery_type,
                        v.notes,
                        v.region_id,
                        v.destination_region_id,
                        v.created_by_user_id,
                        v.created_at,
                        v.status,
                        r_origin.region_name AS origin_region_name,
                        r_origin.prefix AS origin_prefix,
                        r_dest.region_name AS destination_region_name,
                        u.username AS created_by_username
                      FROM vouchers v
                      LEFT JOIN regions r_origin ON v.region_id = r_origin.id
                      LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
                      LEFT JOIN users u ON v.created_by_user_id = u.id
                      WHERE v.id = ?";

    $stmt_voucher = mysqli_prepare($connection, $query_voucher);

    if ($stmt_voucher) {
        mysqli_stmt_bind_param($stmt_voucher, 'i', $voucher_id);
        mysqli_stmt_execute($stmt_voucher);
        $result_voucher = mysqli_stmt_get_result($stmt_voucher);
        $voucher = mysqli_fetch_assoc($result_voucher);
        mysqli_free_result($result_voucher);
        mysqli_stmt_close($stmt_voucher);

        if (!$voucher) {
            flash_message('error', 'Voucher not found.');
            redirect('index.php?page=voucher_list');
            exit();
        }

        // --- VOUCHER VIEW PERMISSION LOGIC ---
        // MODIFICATION: This change allows all logged-in users to view any voucher.
        $has_view_permission = true;

        if (!$has_view_permission) {
            flash_message('error', 'You do not have permission to view this voucher.');
            redirect('index.php?page=voucher_list');
            exit();
        }

        // Fetch voucher breakdowns and calculate total_breakdown_kg
        $stmt_breakdowns = mysqli_prepare($connection, "SELECT item_type, kg, price_per_kg FROM voucher_breakdowns WHERE voucher_id = ?");
        if ($stmt_breakdowns) {
            mysqli_stmt_bind_param($stmt_breakdowns, 'i', $voucher_id);
            mysqli_stmt_execute($stmt_breakdowns);
            $result_breakdowns = mysqli_stmt_get_result($stmt_breakdowns);
            while ($row = mysqli_fetch_assoc($result_breakdowns)) {
                $breakdowns[] = $row;
                $total_breakdown_kg += (float)$row['kg']; // Accumulate total kg from breakdown items
            }
            mysqli_free_result($result_breakdowns);
            mysqli_stmt_close($stmt_breakdowns);
        } else {
            error_log("Voucher View DB Error: Failed to prepare breakdown statement: " . mysqli_error($connection));
            flash_message('error', 'Error loading voucher breakdown details.');
        }

        // --- Determine Current Region Display ---
        $current_region_display = 'N/A'; // Default value
        switch ($voucher['status']) {
            case 'Pending':
                $current_region_display = htmlspecialchars($voucher['origin_region_name']);
                break;
            case 'In Transit':
            case 'Delivered': // If delivered, it's at the destination
                $current_region_display = htmlspecialchars($voucher['destination_region_name']);
                break;
            case 'Cancelled':
            case 'Returned':
                $current_region_display = 'N/A (Status: ' . htmlspecialchars($voucher['status']) . ')';
                break;
            default:
                $current_region_display = 'Unknown';
                break;
        }

    } else {
        error_log("Voucher View DB Error: Failed to prepare voucher fetch statement: " . mysqli_error($connection));
        flash_message('error', 'Database error fetching voucher details. Please try again.');
        redirect('index.php?page=voucher_list');
        exit();
    }
} catch (Exception $e) {
    error_log("Voucher View Unexpected Error: " . $e->getMessage());
    flash_message('error', 'An unexpected error occurred: ' . $e->getMessage());
    redirect('index.php?page=voucher_list');
    exit();
}

// --- Include Header and Render HTML ---
include_template('header', ['page' => 'voucher_view']);
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-6xl mx-auto my-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Voucher Details: #<?php echo htmlspecialchars($voucher['voucher_code']); ?></h2>

    <?php if ($voucher): // Ensure voucher data is loaded before attempting to display ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <div class="bg-blue-50 p-6 rounded-lg shadow-inner">
                <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Voucher Information</h3>
                <p class="mb-2"><strong class="text-gray-800">Voucher ID:</strong> <?php echo htmlspecialchars($voucher['id']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Voucher Code:</strong> <span class="font-mono text-indigo-600 text-lg"><?php echo htmlspecialchars($voucher['voucher_code']); ?></span></p>
                <p class="mb-2"><strong class="text-gray-800">Origin Region:</strong> <?php echo htmlspecialchars($voucher['origin_region_name']); ?> (<?php echo htmlspecialchars($voucher['origin_prefix'] ?: 'N/A'); ?>)</p>
                <p class="mb-2"><strong class="text-gray-800">Destination Region:</strong> <?php echo htmlspecialchars($voucher['destination_region_name']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Current Region:</strong> <?php echo $current_region_display; ?></p>
                <p class="mb-2"><strong class="text-gray-800">Total Weight (Stored):</strong> <?php echo htmlspecialchars(number_format((float)$voucher['weight_kg'], 2)); ?> kg</p>
                <p class="mb-2"><strong class="text-gray-800">Delivery Charge:</strong> <?php echo htmlspecialchars(number_format((float)$voucher['delivery_charge'], 2)); ?></p>
                <p class="mb-2 text-xl font-bold"><strong class="text-gray-800">Total Amount:</strong> <span class="text-green-600"><?php echo htmlspecialchars(number_format((float)$voucher['total_amount'], 2) . ' ' . $voucher['currency']); ?></span></p>
                <p class="mb-2"><strong class="text-gray-800">Payment Method:</strong> <?php echo htmlspecialchars($voucher['payment_method'] ?: 'N/A'); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Delivery Type:</strong> <?php echo htmlspecialchars($voucher['delivery_type'] ?: 'N/A'); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Status:</strong>
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
                </p>
                <p class="mb-2"><strong class="text-gray-800">Created By:</strong> <?php echo htmlspecialchars($voucher['created_by_username']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Created At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($voucher['created_at'])); ?></p>
            </div>

            <div class="bg-green-50 p-6 rounded-lg shadow-inner">
                <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Sender Information</h3>
                <p class="mb-2"><strong class="text-gray-800">Name:</strong> <?php echo htmlspecialchars($voucher['sender_name']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Phone:</strong> <?php echo htmlspecialchars($voucher['sender_phone']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Address:</strong> <?php echo htmlspecialchars($voucher['sender_address'] ?: 'N/A'); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Use sender address for checkout:</strong> <?php echo $voucher['use_sender_address_for_checkout'] ? 'Yes' : 'No'; ?></p>
            </div>

            <div class="bg-yellow-50 p-6 rounded-lg shadow-inner">
                <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Receiver Information</h3>
                <p class="mb-2"><strong class="text-gray-800">Name:</strong> <?php echo htmlspecialchars($voucher['receiver_name']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Phone:</strong> <?php echo htmlspecialchars($voucher['receiver_phone']); ?></p>
                <p class="mb-2"><strong class="text-gray-800">Address:</strong> <?php echo htmlspecialchars($voucher['receiver_address']); ?></p>
            </div>
        </div>

        <div class="bg-purple-50 p-6 rounded-lg shadow-inner mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Voucher Notes & Status Update</h3>
            <p class="mb-4"><strong class="text-gray-800">Current Notes:</strong> <?php echo nl2br(htmlspecialchars($voucher['notes'] ?: 'N/A')); ?></p>

            <?php
            // Determine if current user has permission to update status/notes
            $can_update_status_notes = false;
            // Admins, Myanmar, and Malay users can always update
            if ($is_admin || $user_type === USER_TYPE_MYANMAR || $user_type === USER_TYPE_MALAY) {
                $can_update_status_notes = true;
            }
            // Creator can update
            elseif ($voucher['created_by_user_id'] == $user_id) {
                $can_update_status_notes = true;
            }

            if ($can_update_status_notes):
            ?>
            <form action="index.php?page=voucher_view&id=<?php echo htmlspecialchars($voucher['id']); ?>" method="POST" class="mt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="status" class="block text-gray-700 text-sm font-semibold mb-2">Change Status:</label>
                        <select id="status" name="status" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php foreach ($possible_statuses as $status_option): ?>
                                <option value="<?php echo htmlspecialchars($status_option); ?>"
                                    <?php echo ($voucher['status'] === $status_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="notes" class="block text-gray-700 text-sm font-semibold mb-2">Update Notes (Optional):</label>
                        <textarea id="notes" name="notes" rows="4" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Add or update notes for this voucher..."><?php echo htmlspecialchars($voucher['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300">Update Voucher</button>
                </div>
            </form>
            <?php else: ?>
                <p class="text-gray-600 italic">You do not have permission to update the status or notes for this voucher.</p>
            <?php endif; ?>
        </div>

        <div class="bg-red-50 p-6 rounded-lg shadow-inner mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Item Breakdown</h3>
            <?php if (empty($breakdowns)): ?>
                <p class="text-gray-600">No specific item breakdowns found for this voucher.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 rounded-lg overflow-hidden mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kg</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price per Kg</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($breakdowns as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['item_type'] ?: 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format((float)$item['kg'], 2)); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format((float)$item['price_per_kg'], 2)); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format((float)$item['kg'] * (float)$item['price_per_kg'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-lg font-bold text-gray-800 mt-4">Total Kg (from breakdown items): <span class="text-blue-700"><?php echo htmlspecialchars(number_format($total_breakdown_kg, 2)); ?> kg</span></p>
            <?php endif; ?>
        </div>

        <div class="flex justify-center mt-8 space-x-4">
            <a href="voucher_print.php?id=<?php echo htmlspecialchars($voucher['id']); ?>" target="_blank" class="bg-sky-500 hover:bg-sky-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">Print Voucher</a>
            <a href="index.php?page=voucher_list" class="bg-slate-700 hover:bg-slate-950 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">Back to Voucher List</a>
        </div>
    <?php else: // Fallback if $voucher is somehow null even after checks (shouldn't happen with current logic) ?>
        <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md mx-auto my-8 text-center">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Voucher Not Found</h2>
            <p class="text-gray-600 mb-6">The voucher you are trying to view does not exist or an invalid ID was provided.</p>
            <a href="index.php?page=voucher_list" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">Go to Voucher List</a>
        </div>
    <?php endif; ?>
</div>

<?php include_template('footer'); ?>
