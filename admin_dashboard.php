<?php
// admin_dashboard.php
// Admin specific dashboard

include_template('header', ['page' => 'admin_dashboard']);

if (!is_logged_in() || !is_admin()) {
    flash_message('error', 'Access denied. You must be an ADMIN.');
    redirect('index.php?page=dashboard'); // Redirect to user dashboard if not admin
}

$username = $_SESSION['username'] ?? 'Admin';
?>

<div class="bg-white p-8 rounded-lg shadow-xl text-center">
    <h2 class="text-4xl font-bold text-gray-800 mb-4">Admin Dashboard</h2>
    <p class="text-xl text-gray-600 mb-8">Hello, <?php echo htmlspecialchars($username); ?>!</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <a href="index.php?page=voucher_create" class="block p-6 bg-burgundy text-white rounded-lg shadow-md hover:bg-burgundy-dark transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Create Voucher</h3>
            <p>Generate new shipping vouchers.</p>
        </a>
        <a href="index.php?page=voucher_list" class="block p-6 bg-steel text-white rounded-lg shadow-md hover:bg-steel-dark transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Manage Vouchers</h3>
            <p>View, edit, and track all vouchers.</p>
        </a>
        <a href="index.php?page=stock_list" class="block p-6 bg-yellow-600 text-white rounded-lg shadow-md hover:bg-yellow-700 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Stock Management</h3>
            <p>Oversee inventory levels and stock items.</p>
        </a>
        <a href="index.php?page=other_income" class="block p-6 bg-green-600 text-white rounded-lg shadow-md hover:bg-green-700 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Add Other Income</h3>
            <p>Record additional revenue sources.</p>
        </a>
        <a href="index.php?page=expenses" class="block p-6 bg-red-600 text-white rounded-lg shadow-md hover:bg-red-700 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Track Expenses</h3>
            <p>Record and categorize company expenditures.</p>
        </a>
        <a href="index.php?page=profit_loss" class="block p-6 bg-purple-600 text-white rounded-lg shadow-md hover:bg-purple-700 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Profit & Loss</h3>
            <p>Access financial reports and net worth.</p>
        </a>
        </div>
</div>

<?php include_template('footer'); ?>