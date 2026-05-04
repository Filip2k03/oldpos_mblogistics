<?php
// expenses.php
// Page for managing expenses, now including currency selection and user-based filtering.

include_template('header', ['page' => 'expenses']);

if (!is_logged_in()) {
    flash_message('error', 'Please log in to manage expenses.');
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];

// Access the global $connection variable
global $connection;

// Define currencies for the dropdown
$currencies = ['MMK', 'RM', 'BHAT'];

// --- Fetch the logged-in user's currency preference ---
$user_currency_preference = null; // Initialize to null
if (!is_admin()) { // Only fetch preference if not an admin, as admin sees all
    $stmt_user_currency = mysqli_prepare($connection, "SELECT currency_preference FROM users WHERE id = ?");
    if ($stmt_user_currency) {
        mysqli_stmt_bind_param($stmt_user_currency, 'i', $user_id);
        mysqli_stmt_execute($stmt_user_currency);
        $result_user_currency = mysqli_stmt_get_result($stmt_user_currency);
        if ($row_user_currency = mysqli_fetch_assoc($result_user_currency)) {
            // FIX START: Use null coalescing operator to provide empty string if null
            $user_currency_preference = htmlspecialchars($row_user_currency['currency_preference'] ?? '');
            // FIX END
        }
        mysqli_stmt_close($stmt_user_currency);
    } else {
        // Handle error, though ideally this query should be robust
        flash_message('error', 'Error fetching user currency preference: ' . mysqli_error($connection));
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? ''); // Get the selected currency
    $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));

    if (empty($description) || $amount <= 0 || empty($currency) || empty($expense_date)) {
        flash_message('error', 'Please fill in all fields correctly (amount must be positive and currency must be selected).');
        redirect('index.php?page=expenses');
    }

    // Validate selected currency
    if (!in_array($currency, $currencies)) {
        flash_message('error', 'Invalid currency selected.');
        redirect('index.php?page=expenses');
    }

    // --- IMPORTANT SECURITY CHECK for Non-Admins: Prevent users from adding expenses in a currency they shouldn't manage ---
    // If the user is NOT an admin AND the selected currency does not match their preference, deny the request.
    if (!is_admin() && $currency !== $user_currency_preference) {
        flash_message('error', 'You are not authorized to add expenses in this currency.');
        redirect('index.php?page=expenses');
    }

    // Insert currency into the expenses table
    $stmt = mysqli_prepare($connection, "INSERT INTO expenses (description, amount, currency, expense_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $expense_date, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Expense added successfully!');
            redirect('index.php?page=expenses');
        } else {
            flash_message('error', 'Error adding expense: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Database statement preparation error: ' . mysqli_error($connection));
    }
}

// Fetch existing expenses for display, including currency
$expenses = [];
$query = "SELECT e.*, u.username AS created_by_username
          FROM expenses e
          JOIN users u ON e.created_by_user_id = u.id";

$where_clauses = [];
$param_types = '';
$param_values = [];

// Apply filtering based on user role and currency preference
if (!is_admin()) {
    if ($user_currency_preference !== null && $user_currency_preference !== '') { // Ensure preference is not null or empty
        $where_clauses[] = "e.currency = ?";
        $param_types .= 's';
        $param_values[] = $user_currency_preference;
    } else {
        // If a non-admin user has no currency preference set, they see nothing.
        $where_clauses[] = "1 = 0"; // Force no results
    }
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

$query .= " ORDER BY e.expense_date DESC, e.created_at DESC";

// Prepare and execute the query based on parameters
if (!empty($param_values)) {
    $stmt = mysqli_prepare($connection, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$param_values);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $expenses[] = $row;
            }
            mysqli_free_result($result);
        } else {
            flash_message('error', 'Error fetching expenses: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Database statement preparation error: ' . mysqli_error($connection));
    }
} else {
    // No parameters, execute directly (e.g., for admin user)
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $expenses[] = $row;
        }
        mysqli_free_result($result);
    } else {
        flash_message('error', 'Error fetching expenses: ' . mysqli_error($connection));
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Manage Expenses</h2>

    <div class="bg-sky-100 p-6 rounded-lg shadow-inner mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Add New Expense</h3>
        <form action="index.php?page=expenses" method="POST">
            <div class="mb-4">
                <label for="description" class="block text-gray-700 text-sm font-semibold mb-2">Description:</label>
                <textarea id="description" name="description" rows="3" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Office rent, Utilities bill, Employee salary" required></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="amount" class="block text-gray-700 text-sm font-semibold mb-2">Amount:</label>
                    <input type="number" id="amount" name="amount" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" step="0.01" min="0.01" required>
                </div>
                <div>
                    <label for="currency" class="block text-gray-700 text-sm font-semibold mb-2">Currency:</label>
                    <select id="currency" name="currency" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select Currency</option>
                        <?php foreach ($currencies as $curr): ?>
                            <?php
                            // Disable/hide options for non-admins if they don't match their preference
                            $disabled = '';
                            $selected = '';
                            if (!is_admin()) {
                                if ($curr !== $user_currency_preference) {
                                    $disabled = 'disabled';
                                } else {
                                    $selected = 'selected'; // Automatically select their allowed currency
                                }
                            } else {
                                // For admin, default select MMK or last selected for UX
                                // You might want to remove 'selected' here if you prefer no default for admin
                                $selected = ($curr === 'MMK') ? 'selected' : '';
                            }
                            ?>
                            <option value="<?php echo htmlspecialchars($curr); ?>" <?php echo $selected; ?> <?php echo $disabled; ?>>
                                <?php echo htmlspecialchars($curr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="expense_date" class="block text-gray-700 text-sm font-semibold mb-2">Date:</label>
                    <input type="date" id="expense_date" name="expense_date" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">Add Expense</button>
            </div>
        </form>
    </div>

    <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Recent Expenses</h3>
    <?php if (empty($expenses)): ?>
        <div class="text-center py-5">
            <p class="text-gray-600">No expenses recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Recorded At</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($expenses as $expense): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format($expense['amount'], 2)); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($expense['currency']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($expense['expense_date']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($expense['created_by_username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($expense['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_template('footer'); ?>
