<?php
// dashboard.php
// Default dashboard for general users, now with financial summaries at the top

include_template('header', ['page' => 'dashboard']);

if (!is_logged_in()) {
    flash_message('error', 'Please log in to view the dashboard.');
    redirect('index.php?page=login');
}

$username = $_SESSION['username'] ?? 'Guest';
$user_type = $_SESSION['user_type'] ?? 'General';

global $connection; // Access the global database connection

// --- Fetch Total Voucher Count ---
$total_voucher_count = 0;
$stmt = mysqli_prepare($connection, "SELECT COUNT(id) AS total_vouchers FROM vouchers");
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $total_voucher_count = $row['total_vouchers'] ?: 0;
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching total voucher count: ' . mysqli_error($connection));
}

// --- Fetch Daily Voucher Count ---
$today = date('Y-m-d'); // Current time is Thursday, July 3, 2025 at 8:08:44 AM +0630.
$daily_voucher_count = 0;
$stmt = mysqli_prepare($connection, "SELECT COUNT(id) AS total_vouchers FROM vouchers WHERE DATE(created_at) = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $daily_voucher_count = $row['total_vouchers'] ?: 0;
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching daily voucher count: ' . mysqli_error($connection));
}

?>

<div class="bg-white p-8 rounded-lg shadow-xl text-center">
    <h2 class="text-4xl font-bold text-gray-800 mb-4">Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <p class="text-xl text-gray-600 mb-8">Your User Type: <span class="font-semibold text-indigo-600"><?php echo htmlspecialchars($user_type); ?></span></p>

    <div class="mb-10 flex flex-col items-center space-y-6">
        <div class="bg-blue-100 p-8 rounded-lg shadow-md w-full max-w-xs">
            <p class="text-lg text-blue-800 font-semibold mb-2">Total Vouchers Issued</p>
            <p class="text-5xl font-bold text-blue-700"><?php echo number_format($total_voucher_count); ?></p>
        </div>

        <div class="bg-yellow-100 p-6 rounded-lg shadow-md w-full max-w-xs">
            <p class="text-lg text-yellow-800 font-semibold mb-2">Daily Vouchers (Today)</p>
            <p class="text-4xl font-bold text-yellow-700"><?php echo number_format($daily_voucher_count); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <a href="index.php?page=voucher_create" class="block p-6 bg-indigo-500 text-white rounded-lg shadow-md hover:bg-indigo-600 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Create New Voucher</h3>
            <p>Start a new shipment entry.</p>
        </a>
        <a href="index.php?page=voucher_list" class="block p-6 bg-green-500 text-white rounded-lg shadow-md hover:bg-green-600 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">View Vouchers</h3>
            <p>Browse all existing vouchers.</p>
        </a>
        <a href="index.php?page=status_bulk_update" class="block p-6 bg-yellow-500 text-white rounded-lg shadow-md hover:bg-yellow-600 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Manage Status</h3>
            <p>View and update inventory.</p>
        </a>
        <a href="index.php?page=expenses" class="block p-6 bg-red-500 text-white rounded-lg shadow-md hover:bg-red-600 transition duration-300 transform hover:scale-105">
            <h3 class="text-2xl font-semibold mb-2">Manage Expenses</h3>
            <p>Add and track company expenses.</p>
        </a>
        <?php if (is_admin()): ?>
            <a href="index.php?page=other_income" class="block p-6 bg-blue-500 text-white rounded-lg shadow-md hover:bg-blue-600 transition duration-300 transform hover:scale-105">
                <h3 class="text-2xl font-semibold mb-2">Manage Other Income</h3>
                <p>Record additional income sources.</p>
            </a>
            <a href="index.php?page=profit_loss" class="block p-6 bg-purple-500 text-white rounded-lg shadow-md hover:bg-purple-600 transition duration-300 transform hover:scale-105">
                <h3 class="text-2xl font-semibold mb-2">View Profit/Loss</h3>
                <p>Analyze financial performance.</p>
            </a>
            <a href="index.php?page=register" class="block p-6 bg-gray-700 text-white rounded-lg shadow-md hover:bg-gray-800 transition duration-300 transform hover:scale-105">
                <h3 class="text-2xl font-semibold mb-2">Register New User</h3>
                <p>Create new user accounts.</p>
            </a>
        <?php endif; ?>
    </div>

    <div class="mt-12 text-center">
        <button id="contactDeveloperBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
            Contact Developer
        </button>
    </div>
</div>

<div id="contactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-sm relative">
        <button id="closeModalBtn" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-2xl font-bold">&times;</button>
        <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Contact Developer</h2>
        <p class="text-gray-700 text-lg mb-6 text-center">
            You can reach me at:
        </p>
        <p class="text-blue-600 text-3xl font-bold text-center mb-6">
            +95 9954480806 </p>
        <div class="text-center flex flex-wrap justify-center gap-4"> <a href="tel:+959954480806" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                </svg>
                Call Now
            </a>
            <a href="sms:+959954480806" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clip-rule="evenodd" />
                </svg>
                Send SMS
            </a>
            <a href="https://t.me/stephanfilip2k03" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-telegram mr-2" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.608 1.976l-.623 2.406.846.065 1.706-.938c.834-.46 1.137-.624 1.288-.661.04-.017.071-.03.092-.04.072-.049.088-.047.172-.004.09.042.138.113.177.26.04.148.026.316-.06.495-.061.168-.117.29-.16.38-.11.234-.2.416-.232.462-.02.036-.026.044-.028.047-.002.003-.005.006-.008.01L7.182 11.89c-.194.165-.42.261-.603.261-.586 0-1.068-.377-1.391-.659-.62-.542-1.2-.956-1.282-1.003-.054-.03-.109-.048-.163-.048-.092 0-.125.016-.166.043l-.062.042-.164.117-.104.07a1 1 0 0 1-.354.129c-.326.076-.41.07-.517-.006l-.06-.05-.167-.145-1.47-1.336c-.44-.41-.75-.6-.916-.628-.06-.01-.105-.015-.147-.015-.093 0-.178.04-.252.115-.12.148-.18.276-.22.465-.036.162-.056.28-.064.307-.005.013-.008.016-.011.018-.002.002-.004.004-.007.006-.003.003-.006.005-.008.008a6.76 6.76 0 0 1-.162.09c-.026.012-.057.028-.087.04-.03.013-.058.022-.088.033-.3.093-.654.097-.7.091C.013 9.728 0 9.693 0 9.636c0-.024.029-.074.103-.178.026-.038.059-.074.097-.108l.056-.051 1.077-.965c1.078-.96 1.45-1.295 1.552-1.356.12-.072.247-.132.379-.18.237-.087.48-.17.714-.242.42-.137.833-.242 1.092-.261.685-.045 1.38-.104 2.052-.168.973-.096 1.254-.127 1.524-.127H16V8a8 8 0 0 0-7.713-7.906z"/>
                </svg>
                Telegram
            </a>
        </div>
    </div>
</div>

<script>
    const contactDeveloperBtn = document.getElementById('contactDeveloperBtn');
    const contactModal = document.getElementById('contactModal');
    const closeModalBtn = document.getElementById('closeModalBtn');

    // Function to show the modal
    function showModal() {
        contactModal.classList.remove('hidden');
        contactModal.classList.add('flex'); // To ensure flex properties apply for centering
    }

    // Function to hide the modal
    function hideModal() {
        contactModal.classList.add('hidden');
        contactModal.classList.remove('flex');
    }

    // Event listener for the "Contact Developer" button
    if (contactDeveloperBtn) {
        contactDeveloperBtn.addEventListener('click', showModal);
    }

    // Event listener for the "X" close button
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', hideModal);
    }

    // Close modal when clicking outside of it
    if (contactModal) {
        contactModal.addEventListener('click', function(event) {
            if (event.target === contactModal) {
                hideModal();
            }
        });
    }

    // Close modal when Escape key is pressed
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !contactModal.classList.contains('hidden')) {
            hideModal();
        }
    });
</script>

<?php include_template('footer'); ?>