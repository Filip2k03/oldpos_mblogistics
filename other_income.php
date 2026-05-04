<?php
// other_income.php
// Admin-only page for adding other income, now including currency selection.

include_template('header', ['page' => 'other_income']);

if (!is_logged_in() || !is_admin()) {
    flash_message('error', 'Access denied. You must be an ADMIN to manage other income.');
    redirect('index.php?page=dashboard');
}

$user_id = $_SESSION['user_id'];

// Access the global $connection variable
global $connection;

// Define currencies for the dropdown (must match those used elsewhere, e.g., in vouchers/expenses)
$currencies = ['MMK', 'RM', 'BHAT'];

$should_redirect = false; // Flag to control redirect after POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = trim($_POST['currency'] ?? ''); // Get the selected currency
    $income_date = trim($_POST['income_date'] ?? date('Y-m-d'));

    if (empty($description) || $amount <= 0 || empty($currency) || empty($income_date)) {
        flash_message('error', 'Please fill in all fields correctly (amount must be positive and currency must be selected).');
        $should_redirect = true;
    } 
    // Validate selected currency
    else if (!in_array($currency, $currencies)) {
        flash_message('error', 'Invalid currency selected.');
        $should_redirect = true;
    }
    else {
        // Insert currency into the other_income table
        $stmt = mysqli_prepare($connection, "INSERT INTO other_income (description, amount, currency, income_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $income_date, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                flash_message('success', 'Other income added successfully!');
                $should_redirect = true;
            } else {
                flash_message('error', 'Error adding other income: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            flash_message('error', 'Database statement preparation error: ' . mysqli_error($connection));
        }
    }

    if ($should_redirect) {
        redirect('index.php?page=other_income'); // Redirect before any output
    }
}

// Fetch existing other income entries for display, including currency
$other_income_entries = [];
$query = "SELECT oi.*, u.username AS created_by_username
          FROM other_income oi
          JOIN users u ON oi.created_by_user_id = u.id
          ORDER BY oi.income_date DESC, oi.created_at DESC";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $other_income_entries[] = $row;
    }
    mysqli_free_result($result);
} else {
    flash_message('error', 'Error fetching other income entries: ' . mysqli_error($connection));
}

include_template('header', ['page' => 'other_income']);
?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Manage Other Income</h2>

    <div class="bg-sky-100 p-6 rounded-lg shadow-inner">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Add New Other Income</h3>
        <form action="index.php?page=other_income" method="POST">
            <div class="mb-4">
                <label for="description" class="block text-gray-700 text-sm font-semibold mb-2">Description:</label>
                <textarea id="description" name="description" rows="3" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Interest income, Sale of assets" required></textarea>
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
                            <option value="<?php echo htmlspecialchars($curr); ?>" <?php echo ($curr === 'MMK') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="income_date" class="block text-gray-700 text-sm font-semibold mb-2">Date:</label>
                    <input type="date" id="income_date" name="income_date" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300">Add Income</button>
            </div>
        </form>
    </div>

    <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Recent Other Income Entries</h3>
    <?php if (empty($other_income_entries)): ?>
        <div class="text-center py-5">
            <p class="text-gray-600">No other income recorded yet.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-400 p-6 rounded-lg shadow-inner">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th> <!-- New column header -->
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Recorded At</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($other_income_entries as $income): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($income['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format($income['amount'], 2)); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($income['currency']); ?></td> <!-- Display currency -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($income['income_date']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($income['created_by_username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($income['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_template('footer'); ?>
