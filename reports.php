<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get report parameters
$report_type = $_GET['report_type'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Initialize variables
$salary_data = [];
$expense_data = [];
$category_data = [];
$total_income = 0;
$total_expenses = 0;

// Generate report based on type
if ($report_type === 'monthly') {
    // Monthly Salary - using total_amount instead of php_amount
    $salary_stmt = $conn->prepare("
        SELECT DATE_FORMAT(date, '%Y-%m') as period, 
               SUM(total_amount) as total_income,
               SUM(hours_worked) as total_hours
        FROM salary_entries 
        WHERE user_id = ? AND DATE_FORMAT(date, '%Y') = ?
        GROUP BY DATE_FORMAT(date, '%Y-%m') 
        ORDER BY period DESC
    ");
    if ($salary_stmt) {
        $salary_stmt->bind_param("ii", $_SESSION['user_id'], $year);
        $salary_stmt->execute();
        $salary_result = $salary_stmt->get_result();
        
        while ($row = $salary_result->fetch_assoc()) {
            $salary_data[$row['period']] = $row;
        }
    }

    // Monthly Expenses
    $expense_stmt = $conn->prepare("
        SELECT DATE_FORMAT(date, '%Y-%m') as period, 
               SUM(amount) as total_expenses,
               COUNT(*) as expense_count
        FROM expense_entries 
        WHERE user_id = ? AND DATE_FORMAT(date, '%Y') = ?
        GROUP BY DATE_FORMAT(date, '%Y-%m') 
        ORDER BY period DESC
    ");
    if ($expense_stmt) {
        $expense_stmt->bind_param("ii", $_SESSION['user_id'], $year);
        $expense_stmt->execute();
        $expense_result = $expense_stmt->get_result();
        
        while ($row = $expense_result->fetch_assoc()) {
            $expense_data[$row['period']] = $row;
        }
    }

} elseif ($report_type === 'custom') {
    // Custom Date Range - using total_amount instead of php_amount
    $salary_stmt = $conn->prepare("
        SELECT SUM(total_amount) as total_income,
               SUM(hours_worked) as total_hours,
               COUNT(*) as entries_count
        FROM salary_entries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    if ($salary_stmt) {
        $salary_stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
        $salary_stmt->execute();
        $salary_data = $salary_stmt->get_result()->fetch_assoc();
    }

    $expense_stmt = $conn->prepare("
        SELECT SUM(amount) as total_expenses,
               COUNT(*) as expense_count
        FROM expense_entries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    if ($expense_stmt) {
        $expense_stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
        $expense_stmt->execute();
        $expense_data = $expense_stmt->get_result()->fetch_assoc();
    }

    // Category breakdown for custom range
    $category_stmt = $conn->prepare("
        SELECT category, SUM(amount) as total 
        FROM expense_entries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
        GROUP BY category 
        ORDER BY total DESC
    ");
    if ($category_stmt) {
        $category_stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        while ($row = $category_result->fetch_assoc()) {
            $category_data[] = $row;
        }
    }
    
    $total_income = $salary_data['total_income'] ?? 0;
    $total_expenses = $expense_data['total_expenses'] ?? 0;
}

// Calculate overall totals for monthly report
if ($report_type === 'monthly') {
    $all_months = array_unique(array_merge(array_keys($salary_data), array_keys($expense_data)));
    rsort($all_months);
    
    foreach ($all_months as $month_item) {
        $total_income += $salary_data[$month_item]['total_income'] ?? 0;
        $total_expenses += $expense_data[$month_item]['total_expenses'] ?? 0;
    }
}

$net_balance = $total_income - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/reports.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="manifest" href="manifest.json">
</head>
    <body>
        <nav class="navbar">
            <div class="nav-container">
                <h2><a href="dashboard.php" class="nav-brand">Finance Tracker</a></h2>
                <div class="nav-links">
                    <a href="dashboard.php" class="btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="page-header">
                <h1>Financial Reports</h1>
            </div>

            <!-- Report Filters -->
            <div class="report-filters">
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" onchange="toggleDateFields()">
                                <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Overview</option>
                                <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="year_field">
                            <label for="year">Year</label>
                            <select id="year" name="year">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="custom_date_fields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button class="btn-secondary" onclick="printReport()">🖨️ Print Report</button>
                <button class="btn-secondary" onclick="exportToPDF()">📄 Export PDF</button>
            </div>

            <!-- Financial Summary -->
            <div class="summary-cards">
                <div class="summary-card summary-income">
                    <div>Total Income</div>
                    <div class="summary-amount">₱<?php echo number_format($total_income, 2); ?></div>
                    <div style="font-size: 0.8rem; color: #666;">
                        <?php 
                        if ($total_income > 0) {
                            echo "From salary entries";
                        } else {
                            echo "No income data";
                        }
                        ?>
                    </div>
                </div>
                <div class="summary-card summary-expense">
                    <div>Total Expenses</div>
                    <div class="summary-amount">₱<?php echo number_format($total_expenses, 2); ?></div>
                    <div style="font-size: 0.8rem; color: #666;">
                        <?php 
                        if ($total_expenses > 0) {
                            echo "From expense entries";
                        } else {
                            echo "No expense data";
                        }
                        ?>
                    </div>
                </div>
                <div class="summary-card summary-balance">
                    <div>Net Balance</div>
                    <div class="summary-amount <?php echo $net_balance >= 0 ? 'positive' : 'negative'; ?>">
                        ₱<?php echo number_format($net_balance, 2); ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #666;">
                        <?php echo $net_balance >= 0 ? 'Surplus' : 'Deficit'; ?>
                    </div>
                </div>
            </div>

            <?php if ($report_type === 'monthly'): ?>
                <!-- Monthly Report -->
                <div class="chart-container">
                    <h3>Monthly Financial Overview - <?php echo $year; ?></h3>
                    <?php if (!empty($all_months)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Income</th>
                                        <th>Total Expenses</th>
                                        <th>Hours Worked</th>
                                        <th>Net Balance</th>
                                        <th>Savings Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_months as $month_item): 
                                        $salary = $salary_data[$month_item]['total_income'] ?? 0;
                                        $expense = $expense_data[$month_item]['total_expenses'] ?? 0;
                                        $hours = $salary_data[$month_item]['total_hours'] ?? 0;
                                        $balance = $salary - $expense;
                                        $savings_rate = $salary > 0 ? ($balance / $salary) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($month_item . '-01')); ?></td>
                                        <td>₱<?php echo number_format($salary, 2); ?></td>
                                        <td>₱<?php echo number_format($expense, 2); ?></td>
                                        <td><?php echo number_format($hours, 1); ?> hrs</td>
                                        <td class="<?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                                            ₱<?php echo number_format($balance, 2); ?>
                                        </td>
                                        <td class="<?php echo $savings_rate >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo number_format($savings_rate, 1); ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <p>No financial data available for <?php echo $year; ?></p>
                            <p>Add salary and expense entries to see reports</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($report_type === 'custom'): ?>
                <!-- Custom Date Range Report -->
                <div class="chart-container">
                    <h3>Custom Date Range Report</h3>
                    <p><strong>Period:</strong> <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></p>
                    
                    <?php if ($total_income > 0 || $total_expenses > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Metric</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Total Income</td>
                                        <td>₱<?php echo number_format($total_income, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Total Expenses</td>
                                        <td>₱<?php echo number_format($total_expenses, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Net Balance</td>
                                        <td class="<?php echo $net_balance >= 0 ? 'positive' : 'negative'; ?>">
                                            ₱<?php echo number_format($net_balance, 2); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Hours Worked</td>
                                        <td><?php echo number_format($salary_data['total_hours'] ?? 0, 1); ?> hrs</td>
                                    </tr>
                                    <tr>
                                        <td>Number of Expenses</td>
                                        <td><?php echo $expense_data['expense_count'] ?? 0; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Expense Categories Breakdown -->
                        <?php if (!empty($category_data)): ?>
                        <div class="chart-container">
                            <h3>Expense Categories Breakdown</h3>
                            <div class="category-list">
                                <?php 
                                $max_category = max(array_column($category_data, 'total'));
                                foreach ($category_data as $category): 
                                    $percentage = ($category['total'] / $max_category) * 100;
                                ?>
                                <div style="margin-bottom: 15px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span><?php echo $category['category']; ?></span>
                                        <span>₱<?php echo number_format($category['total'], 2); ?></span>
                                    </div>
                                    <div class="category-progress">
                                        <div class="category-bar" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <p>No financial data available for the selected period</p>
                            <p>Add salary and expense entries to see reports</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <script src="js/reports.js"></script>
        <script src="js/register-sw.js"></script>
    </body>
</html>