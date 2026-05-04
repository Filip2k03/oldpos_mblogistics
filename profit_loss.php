<?php
// profit_loss.php
// Admin-only page for calculating profits and expenses, now broken down by currency, daily, and monthly

include_template('header', ['page' => 'profit_loss']);

if (!is_logged_in() || !is_admin()) {
    flash_message('error', 'Access denied. You must be an ADMIN to view financial reports.');
    redirect('index.php?page=dashboard');
}

// Access the global $connection variable
global $connection;

// Initialize arrays to store income and expenses by currency
$voucher_income_by_currency = [];
$other_income_by_currency = [];
$expenses_by_currency = [];
$all_currencies = []; // To keep track of all unique currencies found

// Initialize arrays for daily and monthly data
$daily_net_worth = [];
$monthly_net_worth = [];
$all_dates = []; // To keep track of all unique dates for daily
$all_months = []; // To keep track of all unique months for monthly

// --- Helper function to fetch and process data ---
function fetch_financial_data($connection, $table, $amount_column, $date_column = null) {
    $data = [];
    $group_by_clause = $date_column ? "GROUP BY currency, DATE($date_column)" : "GROUP BY currency";
    if ($table === 'vouchers') { // Special case for vouchers as it uses total_amount
        $query = "SELECT SUM(total_amount) AS total_amount, currency" . ($date_column ? ", DATE(created_at) AS report_date" : "") . " FROM $table $group_by_clause";
    } else {
        $query = "SELECT SUM($amount_column) AS total_amount, currency" . ($date_column ? ", DATE($date_column) AS report_date" : "") . " FROM $table $group_by_clause";
    }

    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency']);
            $total_amount = (float)$row['total_amount'];
            if ($date_column) {
                $report_date = $row['report_date'];
                $data[$currency][$report_date] = $total_amount;
            } else {
                $data[$currency] = $total_amount;
            }
        }
        mysqli_free_result($result);
    } else {
        flash_message('error', 'Error fetching data from ' . $table . ': ' . mysqli_error($connection));
    }
    return $data;
}

// --- Fetch overall data by currency ---
$voucher_income_by_currency = fetch_financial_data($connection, 'vouchers', 'total_amount');
$other_income_by_currency = fetch_financial_data($connection, 'other_income', 'amount');
$expenses_by_currency = fetch_financial_data($connection, 'expenses', 'amount');

// Populate all_currencies
foreach ($voucher_income_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}
foreach ($other_income_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}
foreach ($expenses_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}

// --- Consolidate overall data for display ---
$financial_summary_by_currency = [];
foreach ($all_currencies as $currency => $dummy) {
    $voucher_income = $voucher_income_by_currency[$currency] ?? 0;
    $other_income = $other_income_by_currency[$currency] ?? 0;
    $expenses = $expenses_by_currency[$currency] ?? 0;

    $total_revenue = $voucher_income + $other_income;
    $net_worth = $total_revenue - $expenses;

    $financial_summary_by_currency[$currency] = [
        'voucher_income' => $voucher_income,
        'other_income' => $other_income,
        'expenses' => $expenses,
        'total_revenue' => $total_revenue,
        'net_worth' => $net_worth
    ];
}
ksort($financial_summary_by_currency);

// --- Fetch Daily Data ---
$daily_voucher_income = fetch_financial_data($connection, 'vouchers', 'total_amount', 'created_at');
$daily_other_income = fetch_financial_data($connection, 'other_income', 'amount', 'created_at');
$daily_expenses = fetch_financial_data($connection, 'expenses', 'amount', 'created_at');

$daily_financial_summary = [];
// Gather all unique dates and currencies
foreach ($daily_voucher_income as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
foreach ($daily_other_income as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
foreach ($daily_expenses as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
ksort($all_dates);

foreach ($all_dates as $date => $dummy_date) {
    foreach ($all_currencies as $currency => $dummy_currency) {
        $voucher_income = $daily_voucher_income[$currency][$date] ?? 0;
        $other_income = $daily_other_income[$currency][$date] ?? 0;
        $expenses = $daily_expenses[$currency][$date] ?? 0;

        $total_revenue = $voucher_income + $other_income;
        $net_worth = $total_revenue - $expenses;

        if (!isset($daily_financial_summary[$date])) {
            $daily_financial_summary[$date] = [];
        }
        $daily_financial_summary[$date][$currency] = [
            'voucher_income' => $voucher_income,
            'other_income' => $other_income,
            'expenses' => $expenses,
            'total_revenue' => $total_revenue,
            'net_worth' => $net_worth
        ];
    }
}

// --- Fetch Monthly Data ---
// For monthly, we'll extract the year-month from the created_at column
$query_monthly_voucher_income = "SELECT SUM(total_amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM vouchers GROUP BY currency, report_month";
$query_monthly_other_income = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM other_income GROUP BY currency, report_month";
$query_monthly_expenses = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM expenses GROUP BY currency, report_month";

$monthly_voucher_income = [];
$monthly_other_income = [];
$monthly_expenses = [];

function process_monthly_query($connection, $query, &$target_array) {
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency']);
            $report_month = $row['report_month'];
            $total_amount = (float)$row['total_amount'];
            $target_array[$currency][$report_month] = $total_amount;
            global $all_months;
            $all_months[$report_month] = true;
            global $all_currencies;
            $all_currencies[$currency] = true;
        }
        mysqli_free_result($result);
    } else {
        flash_message('error', 'Error fetching monthly data: ' . mysqli_error($connection));
    }
}

process_monthly_query($connection, $query_monthly_voucher_income, $monthly_voucher_income);
process_monthly_query($connection, $query_monthly_other_income, $monthly_other_income);
process_monthly_query($connection, $query_monthly_expenses, $monthly_expenses);

$monthly_financial_summary = [];
ksort($all_months);

foreach ($all_months as $month => $dummy_month) {
    foreach ($all_currencies as $currency => $dummy_currency) {
        $voucher_income = $monthly_voucher_income[$currency][$month] ?? 0;
        $other_income = $monthly_other_income[$currency][$month] ?? 0;
        $expenses = $monthly_expenses[$currency][$month] ?? 0;

        $total_revenue = $voucher_income + $other_income;
        $net_worth = $total_revenue - $expenses;

        if (!isset($monthly_financial_summary[$month])) {
            $monthly_financial_summary[$month] = [];
        }
        $monthly_financial_summary[$month][$currency] = [
            'voucher_income' => $voucher_income,
            'other_income' => $other_income,
            'expenses' => $expenses,
            'total_revenue' => $total_revenue,
            'net_worth' => $net_worth
        ];
    }
}

// Prepare data for Chart.js
$chart_labels_daily = array_keys($daily_financial_summary);
$chart_net_worth_daily = [];
$chart_total_revenue_daily = [];
$chart_expenses_daily = [];

foreach ($chart_labels_daily as $date) {
    $daily_total_net_worth = 0;
    $daily_total_revenue = 0;
    $daily_total_expenses = 0;
    foreach ($daily_financial_summary[$date] as $currency_data) {
        $daily_total_net_worth += $currency_data['net_worth'];
        $daily_total_revenue += $currency_data['total_revenue'];
        $daily_total_expenses += $currency_data['expenses'];
    }
    $chart_net_worth_daily[] = $daily_total_net_worth;
    $chart_total_revenue_daily[] = $daily_total_revenue;
    $chart_expenses_daily[] = $daily_total_expenses;
}

$chart_labels_monthly = array_keys($monthly_financial_summary);
$chart_net_worth_monthly = [];
$chart_total_revenue_monthly = [];
$chart_expenses_monthly = [];

foreach ($chart_labels_monthly as $month) {
    $monthly_total_net_worth = 0;
    $monthly_total_revenue = 0;
    $monthly_total_expenses = 0;
    foreach ($monthly_financial_summary[$month] as $currency_data) {
        $monthly_total_net_worth += $currency_data['net_worth'];
        $monthly_total_revenue += $currency_data['total_revenue'];
        $monthly_total_expenses += $currency_data['expenses'];
    }
    $chart_net_worth_monthly[] = $monthly_total_net_worth;
    $chart_total_revenue_monthly[] = $monthly_total_revenue;
    $chart_expenses_monthly[] = $monthly_total_expenses;
}

?>

<div class="bg-white p-8 rounded-lg shadow-xl w-full">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Profit & Loss Statement</h2>

    <div class="mb-8">
        <div class="flex border-b border-gray-200">
            <button class="tab-button py-2 px-4 text-sm font-medium text-gray-600 focus:outline-none hover:text-indigo-600 active-tab" data-tab="currency">By Currency</button>
            <button class="tab-button py-2 px-4 text-sm font-medium text-gray-600 focus:outline-none hover:text-indigo-600" data-tab="daily">Daily Summary</button>
            <button class="tab-button py-2 px-4 text-sm font-medium text-gray-600 focus:outline-none hover:text-indigo-600" data-tab="monthly">Monthly Summary</button>
            <button class="tab-button py-2 px-4 text-sm font-medium text-gray-600 focus:outline-none hover:text-indigo-600" data-tab="charts">Charts</button>
        </div>

        <div id="currency" class="tab-content mt-4 active">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Summary by Currency</h3>
            <?php if (empty($financial_summary_by_currency)): ?>
                <p class="text-center text-gray-600">No financial data available to display by currency.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200 mb-8">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Currency</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voucher Income</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Other Income</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Expenses</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Net Worth</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($financial_summary_by_currency as $currency => $data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-gray-900"><?php echo $currency; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($data['voucher_income'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($data['other_income'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600"><?php echo number_format($data['total_revenue'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600"><?php echo number_format($data['expenses'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-bold <?php echo ($data['net_worth'] >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo number_format($data['net_worth'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="daily" class="tab-content mt-4 hidden">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Daily Financial Summary</h3>
            <?php if (empty($daily_financial_summary)): ?>
                <p class="text-center text-gray-600">No daily financial data available to display.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200 mb-8">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Date</th>
                                <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $curr_code; ?> (Net)</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($daily_financial_summary as $date => $currencies_data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900"><?php echo htmlspecialchars($date); ?></td>
                                    <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (($currencies_data[$curr_code]['net_worth'] ?? 0) >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo number_format($currencies_data[$curr_code]['net_worth'] ?? 0, 2); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="monthly" class="tab-content mt-4 hidden">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Monthly Financial Summary</h3>
            <?php if (empty($monthly_financial_summary)): ?>
                <p class="text-center text-gray-600">No monthly financial data available to display.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg shadow-md border border-gray-200 mb-8">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Month</th>
                                <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo $curr_code; ?> (Net)</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($monthly_financial_summary as $month => $currencies_data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900"><?php echo htmlspecialchars($month); ?></td>
                                    <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (($currencies_data[$curr_code]['net_worth'] ?? 0) >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo number_format($currencies_data[$curr_code]['net_worth'] ?? 0, 2); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="charts" class="tab-content mt-4 hidden">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Financial Charts</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="text-xl font-medium text-gray-700 mb-2">Daily Net Worth Over Time (All Currencies Combined)</h4>
                    <canvas id="dailyNetWorthChart"></canvas>
                </div>
                <div>
                    <h4 class="text-xl font-medium text-gray-700 mb-2">Monthly Net Worth Over Time (All Currencies Combined)</h4>
                    <canvas id="monthlyNetWorthChart"></canvas>
                </div>
            </div>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                <div>
                    <h4 class="text-xl font-medium text-gray-700 mb-2">Daily Revenue vs. Expenses (All Currencies Combined)</h4>
                    <canvas id="dailyRevenueExpensesChart"></canvas>
                </div>
                <div>
                    <h4 class="text-xl font-medium text-gray-700 mb-2">Monthly Revenue vs. Expenses (All Currencies Combined)</h4>
                    <canvas id="monthlyRevenueExpensesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 text-center">
        <a href="index.php?page=other_income" class="btn bg-green-600 hover:bg-green-700 px-6 py-2">Manage Other Income</a>
        <a href="index.php?page=expenses" class="btn btn-blue px-6 py-2 ml-4">Manage Expenses</a>
        <a href="index.php?page=admin_dashboard" class="btn bg-gray-700 hover:bg-gray-800 px-6 py-2 ml-4">Back to Admin Dashboard</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching logic
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                tabButtons.forEach(btn => btn.classList.remove('active-tab'));
                tabContents.forEach(content => content.classList.add('hidden'));

                button.classList.add('active-tab');
                const targetTab = button.dataset.tab;
                document.getElementById(targetTab).classList.remove('hidden');

                // Re-render charts when the charts tab is activated
                if (targetTab === 'charts') {
                    renderCharts();
                }
            });
        });

        // Initialize active tab style
        const activeTabButton = document.querySelector('.tab-button.active-tab');
        if (activeTabButton) {
            activeTabButton.style.borderBottom = '2px solid #4F46E5'; // Example indigo-600
            activeTabButton.style.color = '#4F46E5';
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                tabButtons.forEach(btn => {
                    btn.classList.remove('active-tab');
                    btn.style.borderBottom = 'none';
                    btn.style.color = '#4B5563'; // gray-600
                });

                button.classList.add('active-tab');
                button.style.borderBottom = '2px solid #4F46E5'; // indigo-600
                button.style.color = '#4F46E5';

                tabContents.forEach(content => content.classList.add('hidden'));
                const targetTab = button.dataset.tab;
                document.getElementById(targetTab).classList.remove('hidden');

                if (targetTab === 'charts') {
                    renderCharts();
                }
            });
        });


        // Chart Data from PHP
        const dailyLabels = <?php echo json_encode($chart_labels_daily); ?>;
        const dailyNetWorthData = <?php echo json_encode($chart_net_worth_daily); ?>;
        const dailyRevenueData = <?php echo json_encode($chart_total_revenue_daily); ?>;
        const dailyExpensesData = <?php echo json_encode($chart_expenses_daily); ?>;

        const monthlyLabels = <?php echo json_encode($chart_labels_monthly); ?>;
        const monthlyNetWorthData = <?php echo json_encode($chart_net_worth_monthly); ?>;
        const monthlyRevenueData = <?php echo json_encode($chart_total_revenue_monthly); ?>;
        const monthlyExpensesData = <?php echo json_encode($chart_expenses_monthly); ?>;

        // Function to render charts
        function renderCharts() {
            // Daily Net Worth Chart
            new Chart(document.getElementById('dailyNetWorthChart'), {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Daily Net Worth',
                        data: dailyNetWorthData,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Net Worth'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });

            // Monthly Net Worth Chart
            new Chart(document.getElementById('monthlyNetWorthChart'), {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Monthly Net Worth',
                        data: monthlyNetWorthData,
                        borderColor: 'rgb(153, 102, 255)',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Net Worth'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });

            // Daily Revenue vs Expenses Chart
            new Chart(document.getElementById('dailyRevenueExpensesChart'), {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [
                        {
                            label: 'Daily Total Revenue',
                            data: dailyRevenueData,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        },
                        {
                            label: 'Daily Total Expenses',
                            data: dailyExpensesData,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Revenue vs. Expenses'
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true
                        }
                    }
                }
            });

            // Monthly Revenue vs Expenses Chart
            new Chart(document.getElementById('monthlyRevenueExpensesChart'), {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Monthly Total Revenue',
                            data: monthlyRevenueData,
                            backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        },
                        {
                            label: 'Monthly Total Expenses',
                            data: monthlyExpensesData,
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Revenue vs. Expenses'
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    });
</script>

<style>
    /* Basic styling for tabs, assuming Tailwind CSS is available */
    .tab-button.active-tab {
        border-bottom: 2px solid #4F46E5; /* Tailwind indigo-600 */
        color: #4F46E5; /* Tailwind indigo-600 */
    }
</style>

<?php include_template('footer'); ?>
