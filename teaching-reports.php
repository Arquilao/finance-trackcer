<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get report parameters
$report_type = $_GET['report_type'] ?? 'weekly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Initialize teaching stats
$teaching_stats = [
    'regular_students' => 0,
    'trial_students' => 0,
    'trial_absent' => 0, // NEW: Added this stat
    'conversions' => 0,
    'teaching_income_php' => 0,
    'teaching_income_usd' => 0,
    'gift_income_php' => 0,
    'gift_income_usd' => 0,
    'total_income' => 0,
    'total_hours' => 0,
    'period_label' => ''
];

// Default exchange rate for fallback
$default_exchange_rate = 56.0;

// Generate teaching report based on type
if ($report_type === 'weekly') {
    
    // Safer way to calculate the week (Mon-Sun)
    $today = strtotime('today');
    $day_of_week = date('N', $today); // 1 for Monday, 7 for Sunday
    $start_date = date('Y-m-d', strtotime('-' . ($day_of_week - 1) . ' days', $today));
    $end_date = date('Y-m-d', strtotime('+' . (7 - $day_of_week) . ' days', $today));

    $teaching_stats['period_label'] = date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
    
    $query = $conn->prepare("
        SELECT description, total_amount, usd_rate, currency, hours_worked
        FROM salary_entries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $query->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
    
} elseif ($report_type === 'monthly') {
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    $teaching_stats['period_label'] = date('F Y', strtotime($month));
    
    $query = $conn->prepare("
        SELECT description, total_amount, usd_rate, currency, hours_worked
        FROM salary_entries 
        WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $query->bind_param("is", $_SESSION['user_id'], $month);
    
} elseif ($report_type === 'custom') {
    $teaching_stats['period_label'] = date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date));
    
    $query = $conn->prepare("
        SELECT description, total_amount, usd_rate, currency, hours_worked
        FROM salary_entries 
        WHERE user_id = ? AND date BETWEEN ? AND ?
    ");
    $query->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
}

if (isset($query) && $query !== false) {
    $query->execute();
    $result = $query->get_result();

    // Calculate teaching statistics
    while ($row = $result->fetch_assoc()) {
        $usd_rate = ($row['usd_rate'] ?? $default_exchange_rate);
        if ($usd_rate == 0) $usd_rate = $default_exchange_rate;

        if (strpos($row['description'], 'Teaching:') !== false) {
            
            // --- NEW: Handle both old and new description formats ---
            // Try new format first: Teaching: X regular, Y trial, Z absent, A conversions
            if (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) absent, (\d+) conversions/', $row['description'], $matches_new)) {
                $teaching_stats['regular_students'] += intval($matches_new[1]);
                $teaching_stats['trial_students'] += intval($matches_new[2]);
                $teaching_stats['trial_absent'] += intval($matches_new[3]); // This adds the absent stat
                $teaching_stats['conversions'] += intval($matches_new[4]);
            } 
            // Fallback to old format: Teaching: X regular, Y trial, A conversions
            elseif (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) conversions/', $row['description'], $matches_old)) {
                $teaching_stats['regular_students'] += intval($matches_old[1]);
                $teaching_stats['trial_students'] += intval($matches_old[2]);
                // $teaching_stats['trial_absent'] remains 0
                $teaching_stats['conversions'] += intval($matches_old[3]);
            }
            // --- End of new logic ---

            $teaching_stats['teaching_income_php'] += $row['total_amount'];
            $teaching_stats['teaching_income_usd'] += $row['total_amount'] / $usd_rate;
            $teaching_stats['total_hours'] += $row['hours_worked'];

        } else {
            // Gift entry logic (remains the same)
            $currency = $row['currency'] ?? 'PHP';
            
            if ($currency === 'USD') {
                $teaching_stats['gift_income_php'] += $row['total_amount'];
                $teaching_stats['gift_income_usd'] += $row['total_amount'] / $usd_rate;
            } else {
                $teaching_stats['gift_income_php'] += $row['total_amount'];
                $teaching_stats['gift_income_usd'] += $row['total_amount'] / $usd_rate;
            }
        }
        $teaching_stats['total_income'] += $row['total_amount'];
    }
} else {
    $error = "Error preparing the database query.";
}

// Calculate conversion rate
$conversion_rate = $teaching_stats['trial_students'] > 0 ? 
    ($teaching_stats['conversions'] / $teaching_stats['trial_students']) * 100 : 0;

// Calculate hourly rate
$hourly_rate = $teaching_stats['total_hours'] > 0 ? 
    $teaching_stats['teaching_income_php'] / $teaching_stats['total_hours'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Reports</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/teaching-reports.css">
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
                <h1>Teaching Performance Reports</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Report Filters -->
<div class="report-filters">
    <form method="GET" class="filter-form">
        <div class="form-row" style="display: flex; align-items: end; gap: 15px; flex-wrap: wrap;">
            <!-- Report Period -->
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="report_type">Report Period</label>
                <select id="report_type" name="report_type" onchange="toggleDateFields()" style="width: 100%;">
                    <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>This Week</option>
                    <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <!-- Month Field -->
            <div class="form-group" id="month_field" style="flex: 1; min-width: 200px; <?php echo $report_type !== 'monthly' ? 'display: none;' : ''; ?>">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?php echo $month; ?>" style="width: 100%;">
            </div>
            
            <!-- Custom Date Fields -->
            <div id="custom_date_fields" style="display: flex; gap: 15px; align-items: end; flex: 2; min-width: 300px; <?php echo $report_type !== 'custom' ? 'display: none;' : ''; ?>">
                <div class="form-group" style="flex: 1; min-width: 150px;">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" style="width: 100%;">
                </div>
                <div class="form-group" style="flex: 1; min-width: 150px;">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" style="width: 100%;">
                </div>
            </div>
            
            <!-- Generate Button -->
            <div class="form-group" style="flex-shrink: 0;">
                <button type="submit" class="btn-primary">Generate Report</button>
            </div>
        </div>
        
        <!-- Print and Export -->
        <div class="export-buttons">
            <!-- Added type="button" to prevent form submission -->
            <button type="button" onclick="printReport()" class="btn-secondary">🖨️ Print Report</button>
            <button type="button" onclick="exportPDF()" class="btn-secondary">📄 Export PDF</button>
        </div>
    </form>
</div>

            <!-- Teaching Report Summary -->
            <div class="teaching-report-section">
                <h2 class="report-title">
                    <?php 
                    echo $report_type === 'weekly' ? '📊 Weekly Teaching Report' : 
                        ($report_type === 'monthly' ? '📈 Monthly Teaching Report' : '📋 Custom Teaching Report');
                    ?>
                </h2>
                <p><strong>Period:</strong> <?php echo $teaching_stats['period_label']; ?></p>

                <!-- Student Statistics -->
                <h3>Student Statistics</h3>
                <div class="student-stats">
                    <div class="student-stat-card">
                        <div>Regular Students</div>
                        <div class="stat-value"><?php echo $teaching_stats['regular_students']; ?></div>
                        <div class="conversion-rate">Sessions</div>
                    </div>
                    <div class="student-stat-card">
                        <div>Trial Students</div>
                        <div class="stat-value"><?php echo $teaching_stats['trial_students']; ?></div>
                        <div class="conversion-rate">Sessions</div>
                    </div>
                    <!-- NEW Card -->
                    <div class="student-stat-card">
                        <div>Trial Absent</div>
                        <div class="stat-value"><?php echo $teaching_stats['trial_absent']; ?></div>
                        <div class="conversion-rate">Sessions</div>
                    </div>
                    <div class="student-stat-card">
                        <div>Enrolled Students</div>
                        <div class="stat-value"><?php echo $teaching_stats['conversions']; ?></div>
                        <div class="conversion-rate">Conversions</div>
                    </div>
                    <div class="student-stat-card">
                        <div>Total Sessions</div>
                        <!-- UPDATED Total -->
                        <div class="stat-value"><?php echo $teaching_stats['regular_students'] + $teaching_stats['trial_students'] + $teaching_stats['trial_absent']; ?></div>
                        <div class="conversion-rate">All Classes</div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="performance-metrics">
                    <div class="metric-card">
                        <div>Conversion Rate</div>
                        <div class="stat-value"><?php echo number_format($conversion_rate, 1); ?>%</div>
                        <div class="conversion-rate">Trial to Enrollment</div>
                    </div>
                    <div class="metric-card">
                        <div>Hours Worked</div>
                        <div class="stat-value"><?php echo number_format($teaching_stats['total_hours'], 1); ?></div>
                        <div class="conversion-rate">Teaching Hours</div>
                    </div>
                    <div class="metric-card">
                        <div>Hourly Rate</div>
                        <div class="stat-value">₱<?php echo number_format($hourly_rate, 2); ?></div>
                        <div class="conversion-rate">Average per Hour</div>
                    </div>
                </div>

                <!-- Income Breakdown -->
                <h3>Income Breakdown</h3>
                <div class="income-breakdown">
                    <div class="income-card teaching-income">
                        <div>Teaching Income</div>
                        <div class="stat-value">₱<?php echo number_format($teaching_stats['teaching_income_php'], 2); ?></div>
                        <div class="conversion-rate">$<?php echo number_format($teaching_stats['teaching_income_usd'], 2); ?> USD</div>
                    </div>
                    <div class="income-card gift-income">
                        <div>Monetary Gifts</div>
                        <div class="stat-value">₱<?php echo number_format($teaching_stats['gift_income_php'], 2); ?></div>
                        <div class="conversion-rate">$<?php echo number_format($teaching_stats['gift_income_usd'], 2); ?> USD</div>
                    </div>
                    <div class="income-card total-income">
                        <div>Total Income</div>
                        <div class="stat-value">₱<?php echo number_format($teaching_stats['total_income'], 2); ?></div>
                        <div class="conversion-rate">
                            <?php echo $report_type === 'weekly' ? 'This Week' : ($report_type === 'monthly' ? 'This Month' : 'Selected Period'); ?>
                        </div>
                    </div>
                </div>

                <!-- Detailed Income Statement -->
                <h3>Income Statement</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Income Source</th>
                                <th>Amount (PHP)</th>
                                <th>Amount (USD)</th>
                                <th>Performance Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Teaching Income</strong></td>
                                <td>₱<?php echo number_format($teaching_stats['teaching_income_php'], 2); ?></td>
                                <td>$<?php echo number_format($teaching_stats['teaching_income_usd'], 2); ?></td>
                                <td>
                                    <!-- UPDATED Details -->
                                    <?php echo $teaching_stats['regular_students']; ?> regular sessions<br>
                                    <?php echo $teaching_stats['trial_students']; ?> trial sessions<br>
                                    <?php echo $teaching_stats['trial_absent']; ?> trial absent sessions<br>
                                    <?php echo $teaching_stats['conversions']; ?> conversions<br>
                                    <?php echo number_format($teaching_stats['total_hours'], 1); ?> teaching hours
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Monetary Gifts</strong></td>
                                <td>₱<?php echo number_format($teaching_stats['gift_income_php'], 2); ?></td>
                                <td>$<?php echo number_format($teaching_stats['gift_income_usd'], 2); ?></td>
                                <td>Additional income from student/parent gifts</td>
                            </tr>
                            <tr style="font-weight: bold; background: #f8f9fa;">
                                <td>TOTAL INCOME</td>
                                <td>₱<?php echo number_format($teaching_stats['total_income'], 2); ?></td>
                                <td>$<?php echo number_format($teaching_stats['teaching_income_usd'] + $teaching_stats['gift_income_usd'], 2); ?></td>
                                <td>
                                    Conversion Rate: <?php echo number_format($conversion_rate, 1); ?>%<br>
                                    Hourly Rate: ₱<?php echo number_format($hourly_rate, 2); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script src="js/teaching-reports.js"></script>
        <script src="js/register-sw.js"></script>
    </body>
</html>