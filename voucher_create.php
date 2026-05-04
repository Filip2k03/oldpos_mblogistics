<?php
// voucher_create.php - Handles the creation of new vouchers.

// --- Include Configuration and Database Connection ---
require_once 'config.php'; // Ensure this path is correct

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
                <title>Create New Voucher</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            </head>
            <body class="bg-gray-100 p-6">
            <div class="container mx-auto">
            <?php
            // Display flash messages if any
            if (isset($_SESSION['flash'])) {
                $flash_type = $_SESSION['flash']['type'];
                $flash_message = $_SESSION['flash']['message'];
                $class = '';
                if ($flash_type === 'success') {
                    $class = 'bg-green-100 text-green-700';
                } elseif ($flash_type === 'error') {
                    $class = 'bg-red-100 text-red-700';
                }
                echo "<div class='p-4 mb-4 text-sm rounded-lg {$class}' role='alert'>{$flash_message}</div>";
                unset($_SESSION['flash']); // Clear the flash message
            }
        } elseif ($template_name === 'footer') {
            echo '</div></body></html>';
        }
    }
}

// --- Function to generate a unique voucher code based on region prefix and sequence ---
if (!function_exists('generate_unique_voucher_code')) {
    /**
     * Generates a unique voucher code using region prefix and increments sequence.
     * @param mysqli $conn The database connection.
     * @param int $region_id The ID of the region from which to take the prefix and sequence.
     * @return string The generated unique voucher code.
     * @throws Exception If region data cannot be fetched or sequence cannot be updated.
     */
    function generate_unique_voucher_code($conn, $region_id) {
        // Fetch region prefix and current_sequence
        $region_data = null;
        // Use a transaction lock to prevent race conditions during sequence increment
        mysqli_begin_transaction($conn);
        try {
            $stmt_region = mysqli_prepare($conn, "SELECT prefix, current_sequence FROM regions WHERE id = ? FOR UPDATE"); // FOR UPDATE to lock row
            if (!$stmt_region) {
                throw new Exception("Failed to prepare statement for region data: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_region, 'i', $region_id);
            mysqli_stmt_execute($stmt_region);
            $result_region = mysqli_stmt_get_result($stmt_region);
            if ($row = mysqli_fetch_assoc($result_region)) {
                $region_data = $row;
            }
            mysqli_free_result($result_region);
            mysqli_stmt_close($stmt_region);

            if (!$region_data) {
                throw new Exception("Could not find region data for selected region ID: " . $region_id);
            }

            $prefix = $region_data['prefix'];
            $current_sequence = $region_data['current_sequence'];
            $next_sequence = $current_sequence + 1;

            // Determine padding length (fixed to 6 as requested)
            $sequence_length = 6;
            $padded_sequence = str_pad($next_sequence, $sequence_length, '0', STR_PAD_LEFT);

            // Construct the voucher code without a random suffix
            $code = $prefix . $padded_sequence;

            // Update the current_sequence in the regions table
            $update_sequence_sql = "UPDATE regions SET current_sequence = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $update_sequence_sql);
            if (!$stmt_update) {
                throw new Exception("Failed to prepare statement for updating sequence: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_update, 'ii', $next_sequence, $region_id);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Failed to update region sequence: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);

            mysqli_commit($conn); // Commit the transaction
            return $code;

        } catch (Exception $e) {
            mysqli_rollback($conn); // Rollback the transaction on error
            throw $e; // Re-throw the exception to be caught by the calling block
        }
    }
}


// --- Authentication Check ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to create vouchers.');
    redirect('index.php?page=login');
    exit();
}

$user_id = $_SESSION['user_id']; // Current logged-in user
$logged_in_user_type = null;
$logged_in_user_region_id = null;

// Fetch user type and region_id for the logged-in user
$stmt_user_info = mysqli_prepare($connection, "SELECT user_type, region_id FROM users WHERE id = ?");
if ($stmt_user_info) {
    mysqli_stmt_bind_param($stmt_user_info, 'i', $user_id);
    mysqli_stmt_execute($stmt_user_info);
    $result_user_info = mysqli_stmt_get_result($stmt_user_info);
    if ($user_info = mysqli_fetch_assoc($result_user_info)) {
        $logged_in_user_type = $user_info['user_type'];
        $logged_in_user_region_id = $user_info['region_id'];
    }
    mysqli_free_result($result_user_info);
    mysqli_stmt_close($stmt_user_info);
} else {
    flash_message('error', 'Error fetching user information: ' . mysqli_error($connection));
    redirect('index.php?page=dashboard'); // Or an appropriate error page
    exit();
}

// If for some reason user info isn't found, log out or redirect
if ($logged_in_user_type === null) {
    flash_message('error', 'User information not found. Please log in again.');
    redirect('index.php?page=logout'); // Assuming you have a logout script
    exit();
}


// --- Define your Item Types Here ---
$item_types_list = [
    'အထည်',
    'အစားအသောက်',
    'အလှကုန်',
    'ဆေးဝါး',
    'လျှပ်စစ်ပစ္စည်း',
    'Fancy',
    'Gold',
    'Document'
];

// Initialize form data with defaults or empty values
$form_data = [
    'sender_name' => '',
    'sender_phone' => '',
    'sender_address' => '',
    'use_sender_address_for_checkout' => false,
    'receiver_name' => '',
    'receiver_phone' => '',
    'receiver_address' => '',
    'payment_method' => '',
    'delivery_charge' => 0.00,
    'total_amount' => 0.00, // Will be calculated
    'currency' => 'MMK', // Default currency
    'delivery_type' => '',
    'notes' => '',
    'region_id' => '', // Origin region
    'destination_region_id' => '',
    'item_types' => [''], // For dynamic breakdown items - now stores selected values
    'item_kgs' => [''],
    'item_prices_per_kg' => [''],
];

$errors = []; // To store validation errors
$subtotal = 0.00; // Initialize subtotal as float
$grand_total = 0.00; // Initialize grand total as float
$total_calculated_weight_kg = 0.00; // NEW: Initialize total calculated weight from breakdown items

// --- Fetch data for dropdowns (Regions) ---
$all_regions = [];
$stmt_regions = mysqli_prepare($connection, "SELECT id, region_name, prefix FROM regions ORDER BY region_name ASC");
if ($stmt_regions) {
    mysqli_stmt_execute($stmt_regions);
    $result_regions = mysqli_stmt_get_result($stmt_regions);
    while ($row = mysqli_fetch_assoc($result_regions)) {
        $all_regions[] = $row;
    }
    mysqli_free_result($result_regions);
    mysqli_stmt_close($stmt_regions);
} else {
    flash_message('error', 'Error fetching regions: ' . mysqli_error($connection));
}

// Filter regions based on user type
$display_regions = [];
// Assuming constants for user types are defined in config.php (e.g., USER_TYPE_ADMIN, USER_TYPE_MYANMAR, USER_TYPE_MALAY)
if (defined('USER_TYPE_ADMIN') && $logged_in_user_type === USER_TYPE_ADMIN) {
    $display_regions = $all_regions;
} else {
    // For non-admin users, filter to show only their associated origin region
    foreach ($all_regions as $region) {
        if ($region['id'] == $logged_in_user_region_id) {
            $display_regions[] = $region;
            // Pre-select the origin region for non-admin users
            $form_data['region_id'] = $logged_in_user_region_id;
            break; // Found the user's region, no need to continue loop
        }
    }
    if (empty($display_regions) && $logged_in_user_region_id !== null) {
         flash_message('error', 'Your assigned region could not be found. Please contact support.');
         redirect('index.php?page=dashboard');
         exit();
    }
}


$payment_methods = ['Cash', 'Bank Transfer', 'Mobile Pay']; // Example payment methods
$delivery_types = ['POST', 'Take In Office', 'Delivery']; // Example delivery types
$currencies = ['MMK', 'RM', 'SGD']; // Example currencies

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and collect input
    $form_data['sender_name'] = trim($_POST['sender_name'] ?? '');
    $form_data['sender_phone'] = trim($_POST['sender_phone'] ?? '');
    $form_data['use_sender_address_for_checkout'] = isset($_POST['use_sender_address_for_checkout']); // Checkbox

    // Handle sender_address based on checkbox
    if ($form_data['use_sender_address_for_checkout']) {
        $form_data['sender_address'] = trim($_POST['sender_address'] ?? '');
    } else {
        $form_data['sender_address'] = 'N/A'; // Store N/A if not using for checkout
    }

    $form_data['receiver_name'] = trim($_POST['receiver_name'] ?? '');
    $form_data['receiver_phone'] = trim($_POST['receiver_phone'] ?? '');
    $form_data['receiver_address'] = trim($_POST['receiver_address'] ?? '');

    $form_data['payment_method'] = trim($_POST['payment_method'] ?? '');
    $form_data['delivery_charge'] = floatval($_POST['delivery_charge'] ?? 0); // Ensure float after post
    $form_data['currency'] = trim($_POST['currency'] ?? '');
    $form_data['delivery_type'] = trim($_POST['delivery_type'] ?? '');
    $form_data['notes'] = trim($_POST['notes'] ?? '');
    $form_data['region_id'] = intval($_POST['region_id'] ?? 0);
    $form_data['destination_region_id'] = intval($_POST['destination_region_id'] ?? 0);

    // Collect breakdown items
    $form_data['item_types'] = $_POST['item_type'] ?? [''];
    $form_data['item_kgs'] = $_POST['item_kg'] ?? [''];
    $form_data['item_prices_per_kg'] = $_POST['item_price_per_kg'] ?? [''];

    // 2. Validate input
    if (empty($form_data['sender_name'])) $errors[] = 'Sender Name is required.';
    if (empty($form_data['sender_phone'])) $errors[] = 'Sender Phone is required.';

    // Validate sender address only if checkbox is checked
    if ($form_data['use_sender_address_for_checkout'] && empty($form_data['sender_address'])) {
        $errors[] = 'Sender Address is required if "Use sender address for checkout" is checked.';
    }

    if (empty($form_data['receiver_name'])) $errors[] = 'Receiver Name is required.';
    if (empty($form_data['receiver_phone'])) $errors[] = 'Receiver Phone is required.';
    if (empty($form_data['receiver_address'])) $errors[] = 'Receiver Address is required.';

    if (!in_array($form_data['payment_method'], $payment_methods)) $errors[] = 'Invalid Payment Method selected.';
    if (!in_array($form_data['delivery_type'], $delivery_types)) $errors[] = 'Invalid Delivery Type selected.';
    if (!in_array($form_data['currency'], $currencies)) $errors[] = 'Invalid Currency selected.';

    if ($form_data['delivery_charge'] < 0) $errors[] = 'Delivery Charge cannot be negative.';

    if ($form_data['region_id'] <= 0) $errors[] = 'Origin Region is required.';
    if ($form_data['destination_region_id'] <= 0) $errors[] = 'Destination Region is required.';
    if ($form_data['region_id'] === $form_data['destination_region_id']) $errors[] = 'Origin and Destination Regions cannot be the same.';

    // NEW VALIDATION: Ensure user can only select their own region as origin, unless they are admin
    if ((defined('USER_TYPE_ADMIN') && $logged_in_user_type !== USER_TYPE_ADMIN) && $form_data['region_id'] != $logged_in_user_region_id) {
        $errors[] = 'You can only select your assigned region as the Origin Region.';
        // Force the origin region back to the user's assigned region for display
        $form_data['region_id'] = $logged_in_user_region_id;
    }


    // Validate breakdown items and calculate subtotal AND total_calculated_weight_kg
    $validated_breakdowns = [];
    $calculated_subtotal = 0.00;
    $total_calculated_weight_kg = 0.00; // Reset for calculation
    $has_valid_breakdown_item = false;

    foreach ($form_data['item_types'] as $key => $type) {
        $kg = floatval($form_data['item_kgs'][$key] ?? 0);
        $price = floatval($form_data['item_prices_per_kg'][$key] ?? 0);

        // Only process if at least one field for the item is filled, otherwise ignore empty rows
        // Also validate if the selected type is in our allowed list
        if ((!empty(trim($type)) && in_array(trim($type), $item_types_list)) || $kg > 0 || $price > 0) {
            $has_valid_breakdown_item = true; // Mark that at least one item row is being processed

            if (empty(trim($type)) || !in_array(trim($type), $item_types_list)) {
                $errors[] = 'Invalid or empty Item Type selected for a breakdown item.';
            }
            if ($kg <= 0) {
                $errors[] = 'Kg for breakdown item "' . htmlspecialchars($type) . '" must be a positive number.';
            }
            if ($price < 0) { // Price per kg can be 0 if it's a free item, but not negative
                $errors[] = 'Price per Kg for breakdown item "' . htmlspecialchars($type) . '" cannot be negative.';
            }
            $item_subtotal = $kg * $price;
            $calculated_subtotal += $item_subtotal;
            $total_calculated_weight_kg += $kg; // Accumulate total kg

            $validated_breakdowns[] = [
                'item_type' => trim($type),
                'kg' => $kg,
                'price_per_kg' => $price,
            ];
        }
    }

    if (!$has_valid_breakdown_item && empty($errors)) {
        // Only add this error if no other errors prevent processing and no breakdown items are valid
        $errors[] = 'At least one valid item breakdown is required to create the voucher.';
    }

    // Update the subtotal for display and final calculation
    $subtotal = $calculated_subtotal;
    $grand_total = $subtotal + $form_data['delivery_charge'];

    if (empty($errors)) {
        // Use the calculated total amount
        $form_data['total_amount'] = $grand_total;

        // Start transaction for the entire voucher creation process
        mysqli_begin_transaction($connection);

        try {
            // --- MODIFICATION START ---
            // Generate Unique Voucher Code based on selected DESTINATION Region
            // Changed $form_data['region_id'] to $form_data['destination_region_id']
            $voucher_code = generate_unique_voucher_code($connection, $form_data['destination_region_id']);
            // --- MODIFICATION END ---


            // 5. Insert data into vouchers table
            // Pass the calculated total_calculated_weight_kg to the weight_kg column
            $insert_voucher_sql = "INSERT INTO vouchers (
                                    voucher_code, sender_name, sender_phone, sender_address,
                                    use_sender_address_for_checkout, receiver_name, receiver_phone,
                                    receiver_address, payment_method, weight_kg, price_per_kg_at_voucher,
                                    delivery_charge, total_amount, currency, delivery_type, notes,
                                    region_id, destination_region_id, created_by_user_id, created_at, status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";

            $stmt_voucher = mysqli_prepare($connection, $insert_voucher_sql);

            if ($stmt_voucher) {
                // Still a dummy, as it's per item now
                $dummy_price_per_kg = 0.00;

                mysqli_stmt_bind_param($stmt_voucher, 'ssssissssddddssssii',
                    $voucher_code,
                    $form_data['sender_name'],
                    $form_data['sender_phone'],
                    $form_data['sender_address'],
                    $form_data['use_sender_address_for_checkout'],
                    $form_data['receiver_name'],
                    $form_data['receiver_phone'],
                    $form_data['receiver_address'],
                    $form_data['payment_method'],
                    $total_calculated_weight_kg, // NEW: Use the calculated total weight here
                    $dummy_price_per_kg,
                    $form_data['delivery_charge'],
                    $form_data['total_amount'],
                    $form_data['currency'],
                    $form_data['delivery_type'],
                    $form_data['notes'],
                    $form_data['region_id'],
                    $form_data['destination_region_id'],
                    $user_id
                );

                if (!mysqli_stmt_execute($stmt_voucher)) {
                    throw new Exception('Failed to create voucher: ' . mysqli_stmt_error($stmt_voucher));
                }
                $new_voucher_id = mysqli_insert_id($connection);
                mysqli_stmt_close($stmt_voucher);

                // 6. Insert item breakdowns into voucher_breakdowns table
                if (!empty($validated_breakdowns)) {
                    $insert_breakdown_sql = "INSERT INTO voucher_breakdowns (voucher_id, item_type, kg, price_per_kg) VALUES (?, ?, ?, ?)";
                    $stmt_breakdown = mysqli_prepare($connection, $insert_breakdown_sql);

                    if ($stmt_breakdown) {
                        foreach ($validated_breakdowns as $item) {
                            mysqli_stmt_bind_param($stmt_breakdown, 'isdd',
                                $new_voucher_id,
                                $item['item_type'],
                                $item['kg'],
                                $item['price_per_kg']
                            );
                            if (!mysqli_stmt_execute($stmt_breakdown)) {
                                throw new Exception('Failed to insert breakdown item "' . htmlspecialchars($item['item_type']) . '": ' . mysqli_stmt_error($stmt_breakdown));
                            }
                        }
                        mysqli_stmt_close($stmt_breakdown);
                    } else {
                        throw new Exception('Failed to prepare breakdown insert statement: ' . mysqli_error($connection));
                    }
                } else {
                     throw new Exception('No valid breakdown items provided for the voucher. Please add at least one item.');
                }

                mysqli_commit($connection); // Commit the main transaction
                flash_message('success', 'Voucher created successfully with code: ' . $voucher_code);
                redirect('index.php?page=voucher_view&id=' . $new_voucher_id);
                exit();

            } else {
                throw new Exception('Failed to prepare voucher insert statement: ' . mysqli_error($connection));
            }

        } catch (Exception $e) {
            mysqli_rollback($connection); // Rollback the main transaction on error
            error_log("Voucher Creation Error: " . $e->getMessage());
            flash_message('error', 'Error creating voucher: ' . $e->getMessage());
            // Fall through to display form with errors
        }
    } else {
        // If validation errors exist, display them on the form
        flash_message('error', 'Please correct the following errors:<br>' . implode('<br>', $errors));
    }
}

// --- Include Header and Render HTML Form ---
include_template('header', ['page' => 'voucher_create']);
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-4xl mx-auto my-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Create New Voucher</h2>

    <form action="index.php?page=voucher_create" method="POST">
        <div class="mb-8 p-6 bg-blue-50 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Sender Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="sender_name" class="block text-gray-700 text-sm font-semibold mb-2">Sender Name <span class="text-red-500">*</span></label>
                    <input type="text" id="sender_name" name="sender_name" value="<?php echo htmlspecialchars($form_data['sender_name']); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label for="sender_phone" class="block text-gray-700 text-sm font-semibold mb-2">Sender Phone <span class="text-red-500">*</span></label>
                    <input type="text" id="sender_phone" name="sender_phone" value="<?php echo htmlspecialchars($form_data['sender_phone']); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="md:col-span-2">
                    <label for="sender_address" class="block text-gray-700 text-sm font-semibold mb-2">Sender Address</label>
                    <textarea id="sender_address" name="sender_address" rows="3" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" <?php echo !$form_data['use_sender_address_for_checkout'] ? 'disabled' : ''; ?>><?php echo htmlspecialchars($form_data['sender_address'] === 'N/A' ? '' : $form_data['sender_address']); ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <input type="checkbox" id="use_sender_address_for_checkout" name="use_sender_address_for_checkout" class="mr-2" <?php echo $form_data['use_sender_address_for_checkout'] ? 'checked' : ''; ?>>
                    <label for="use_sender_address_for_checkout" class="text-gray-700 text-sm font-semibold">Use sender address for checkout</label>
                </div>
            </div>
        </div>

        <div class="mb-8 p-6 bg-green-50 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Receiver Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="receiver_name" class="block text-gray-700 text-sm font-semibold mb-2">Receiver Name <span class="text-red-500">*</span></label>
                    <input type="text" id="receiver_name" name="receiver_name" value="<?php echo htmlspecialchars($form_data['receiver_name']); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <div>
                    <label for="receiver_phone" class="block text-gray-700 text-sm font-semibold mb-2">Receiver Phone <span class="text-red-500">*</span></label>
                    <input type="text" id="receiver_phone" name="receiver_phone" value="<?php echo htmlspecialchars($form_data['receiver_phone']); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="md:col-span-2">
                    <label for="receiver_address" class="block text-gray-700 text-sm font-semibold mb-2">Receiver Address <span class="text-red-500">*</span></label>
                    <textarea id="receiver_address" name="receiver_address" rows="3" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-green-500" required><?php echo htmlspecialchars($form_data['receiver_address']); ?></textarea>
                </div>
            </div>
        </div>

        <div class="mb-8 p-6 bg-yellow-50 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Voucher Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="region_id" class="block text-gray-700 text-sm font-semibold mb-2">Origin Region <span class="text-red-500">*</span></label>
                    <select id="region_id" name="region_id" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required <?php echo ((defined('USER_TYPE_ADMIN') && $logged_in_user_type !== USER_TYPE_ADMIN)) ? 'disabled' : ''; ?>>
                        <?php if ((defined('USER_TYPE_ADMIN') && $logged_in_user_type === USER_TYPE_ADMIN)): ?>
                            <option value="">Select Origin Region</option>
                        <?php endif; ?>
                        <?php foreach ($display_regions as $region): ?>
                            <option value="<?php echo htmlspecialchars($region['id']); ?>"
                                <?php echo ($form_data['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['region_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ((defined('USER_TYPE_ADMIN') && $logged_in_user_type !== USER_TYPE_ADMIN)): ?>
                        <input type="hidden" name="region_id" value="<?php echo htmlspecialchars($form_data['region_id']); ?>">
                    <?php endif; ?>
                </div>
                <div>
                    <label for="destination_region_id" class="block text-gray-700 text-sm font-semibold mb-2">Destination Region <span class="text-red-500">*</span></label>
                    <select id="destination_region_id" name="destination_region_id" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        <option value="">Select Destination Region</option>
                        <?php foreach ($all_regions as $region): // All regions available for destination ?>
                            <option value="<?php echo htmlspecialchars($region['id']); ?>"
                                <?php echo ($form_data['destination_region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['region_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="payment_method" class="block text-gray-700 text-sm font-semibold mb-2">Payment Method <span class="text-red-500">*</span></label>
                    <select id="payment_method" name="payment_method" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        <option value="">Select Payment Method</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?php echo htmlspecialchars($method); ?>"
                                <?php echo ($form_data['payment_method'] === $method) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="delivery_type" class="block text-gray-700 text-sm font-semibold mb-2">Delivery Type <span class="text-red-500">*</span></label>
                    <select id="delivery_type" name="delivery_type" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        <option value="">Select Delivery Type</option>
                        <?php foreach ($delivery_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                <?php echo ($form_data['delivery_type'] === $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="currency" class="block text-gray-700 text-sm font-semibold mb-2">Currency <span class="text-red-500">*</span></label>
                    <select id="currency" name="currency" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        <?php foreach ($currencies as $curr): ?>
                            <option value="<?php echo htmlspecialchars($curr); ?>"
                                <?php echo ($form_data['currency'] === $curr) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="delivery_charge" class="block text-gray-700 text-sm font-semibold mb-2">Delivery Charge <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" id="delivery_charge" name="delivery_charge" value="<?php echo htmlspecialchars((float)$form_data['delivery_charge']); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                </div>
                <div class="md:col-span-2">
                    <label for="notes" class="block text-gray-700 text-sm font-semibold mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-yellow-500"><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                </div>
            </div>
        </div>

        <div class="mb-8 p-6 bg-purple-50 rounded-lg shadow-inner">
            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Item Breakdown</h3>
            <div id="item_breakdown_container">
                <?php foreach ($form_data['item_types'] as $index => $type): ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 item-row">
                    <div>
                        <label for="item_type_<?php echo $index; ?>" class="block text-gray-700 text-sm font-semibold mb-2">Item Type</label>
                        <select id="item_type_<?php echo $index; ?>" name="item_type[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Select Type</option>
                            <?php foreach ($item_types_list as $item_type_option): ?>
                                <option value="<?php echo htmlspecialchars($item_type_option); ?>"
                                    <?php echo ($type === $item_type_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item_type_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="item_kg_<?php echo $index; ?>" class="block text-gray-700 text-sm font-semibold mb-2">Kg</label>
                        <input type="number" step="0.01" min="0" id="item_kg_<?php echo $index; ?>" name="item_kg[]" value="<?php echo htmlspecialchars((float)$form_data['item_kgs'][$index]); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label for="item_price_per_kg_<?php echo $index; ?>" class="block text-gray-700 text-sm font-semibold mb-2">Price/Kg</label>
                        <input type="number" step="0.01" min="0" id="item_price_per_kg_${itemIndex}" name="item_price_per_kg[]" value="<?php echo htmlspecialchars((float)$form_data['item_prices_per_kg'][$index]); ?>" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div class="flex items-end">
                        <?php if ($index > 0): // Allow removing rows after the first one ?>
                        <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 w-full">Remove</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add_item_btn" class="mt-4 bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">Add Another Item</button>

            <div class="mt-8 pt-4 border-t border-gray-200">
                <p class="text-xl font-bold text-gray-800 mb-2">Subtotal (from items): <span class="text-blue-600"><?php echo htmlspecialchars(number_format((float)$subtotal, 2) . ' ' . $form_data['currency']); ?></span></p>
                <p class="text-xl font-bold text-gray-800 mb-2">Delivery Charge: <span class="text-red-600"><?php echo htmlspecialchars(number_format((float)$form_data['delivery_charge'], 2) . ' ' . $form_data['currency']); ?></span></p>
                <p class="text-2xl font-extrabold text-gray-900 mt-4">Grand Total: <span class="text-green-600"><?php echo htmlspecialchars(number_format((float)$grand_total, 2) . ' ' . $form_data['currency']); ?></span></p>
                <p class="text-lg font-bold text-gray-800 mt-2">Calculated Total Weight: <span class="text-purple-600"><?php echo htmlspecialchars(number_format((float)$total_calculated_weight_kg, 2)); ?> kg</span></p>
            </div>
        </div>

        <div class="flex justify-center mt-8">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-10 rounded-lg shadow-lg text-xl transition duration-300">Create Voucher</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addItemBtn = document.getElementById('add_item_btn');
    const itemBreakdownContainer = document.getElementById('item_breakdown_container');
    const deliveryChargeInput = document.getElementById('delivery_charge');
    let itemIndex = <?php echo count($form_data['item_types']) > 0 ? count($form_data['item_types']) : 1; ?>;

    const subtotalDisplay = document.querySelector('.bg-purple-50 .text-blue-600');
    const deliveryChargeDisplay = document.querySelector('.bg-purple-50 .text-red-600');
    const grandTotalDisplay = document.querySelector('.bg-purple-50 .text-green-600');
    const totalWeightDisplay = document.querySelector('.bg-purple-50 .text-purple-600'); // NEW: For total weight
    const currencySelect = document.getElementById('currency');

    // For sender address logic
    const useSenderAddressCheckbox = document.getElementById('use_sender_address_for_checkout');
    const senderAddressTextarea = document.getElementById('sender_address');

    // Initial state based on PHP value (if form was submitted with errors, maintain state)
    if (!useSenderAddressCheckbox.checked) {
        senderAddressTextarea.disabled = true;
        if (senderAddressTextarea.value === '' || senderAddressTextarea.value === 'N/A') {
            senderAddressTextarea.value = '';
        }
    }

    useSenderAddressCheckbox.addEventListener('change', function() {
        if (this.checked) {
            senderAddressTextarea.disabled = false;
            senderAddressTextarea.focus();
        } else {
            senderAddressTextarea.disabled = true;
            senderAddressTextarea.value = '';
        }
    });

    // Your predefined item types (for JavaScript to use when adding new rows)
    const itemTypesList = <?php echo json_encode($item_types_list); ?>;

    function updateTotals() {
        let currentSubtotal = 0;
        let currentTotalWeight = 0; // NEW: Initialize for dynamic calculation
        document.querySelectorAll('.item-row').forEach(row => {
            const kgInput = row.querySelector('input[name="item_kg[]"]');
            const priceInput = row.querySelector('input[name="item_price_per_kg[]"]');

            const kg = parseFloat(kgInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;

            currentSubtotal += (kg * price);
            currentTotalWeight += kg; // NEW: Accumulate total weight
        });

        const currentDeliveryCharge = parseFloat(deliveryChargeInput.value) || 0;
        const currentGrandTotal = currentSubtotal + currentDeliveryCharge;
        const currentCurrency = currencySelect.value;

        subtotalDisplay.textContent = currentSubtotal.toFixed(2) + ' ' + currentCurrency;
        deliveryChargeDisplay.textContent = currentDeliveryCharge.toFixed(2) + ' ' + currentCurrency;
        grandTotalDisplay.textContent = currentGrandTotal.toFixed(2) + ' ' + currentCurrency;
        totalWeightDisplay.textContent = currentTotalWeight.toFixed(2) + ' kg'; // NEW: Update total weight display
    }

    updateTotals(); // Initial calculation

    addItemBtn.addEventListener('click', function() {
        const newItemRow = document.createElement('div');
        newItemRow.classList.add('grid', 'grid-cols-1', 'md:grid-cols-4', 'gap-4', 'mb-4', 'item-row');

        let optionsHtml = '<option value="">Select Type</option>';
        itemTypesList.forEach(type => {
            optionsHtml += `<option value="${type}">${type}</option>`;
        });

        newItemRow.innerHTML = `
            <div>
                <label for="item_type_${itemIndex}" class="block text-gray-700 text-sm font-semibold mb-2">Item Type</label>
                <select id="item_type_${itemIndex}" name="item_type[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
                    ${optionsHtml}
                </select>
            </div>
            <div>
                <label for="item_kg_${itemIndex}" class="block text-gray-700 text-sm font-semibold mb-2">Kg</label>
                <input type="number" step="0.01" min="0" id="item_kg_${itemIndex}" name="item_kg[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <label for="item_price_per_kg_${itemIndex}" class="block text-gray-700 text-sm font-semibold mb-2">Price/Kg</label>
                <input type="number" step="0.01" min="0" id="item_price_per_kg_${itemIndex}" name="item_price_per_kg[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="flex items-end">
                <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 w-full">Remove</button>
            </div>
        `;
        itemBreakdownContainer.appendChild(newItemRow);
        itemIndex++;

        // Add event listeners to new inputs and select for live calculation
        newItemRow.querySelectorAll('input[type="number"], select[name="item_type[]"]').forEach(element => {
            element.addEventListener('input', updateTotals);
            element.addEventListener('change', updateTotals); // For the select dropdown
        });
        updateTotals(); // Recalculate after adding a new row
    });

    // Event delegation for remove buttons
    itemBreakdownContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item-btn')) {
            e.target.closest('.item-row').remove();
            updateTotals(); // Recalculate after removing a row
        }
    });

    // Add event listeners to existing inputs and selects for live calculation
    document.querySelectorAll('#item_breakdown_container input[type="number"], #item_breakdown_container select[name="item_type[]"], #delivery_charge, #currency').forEach(element => {
        element.addEventListener('input', updateTotals);
        element.addEventListener('change', updateTotals); // Important for select elements
    });

});
</script>

<?php include_template('footer'); ?>