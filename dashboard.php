<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get current month stats
$currentMonth = date('Y-m');

// Initialize variables with default values
$totalSalary = 0;
$totalExpenses = 0;
$balance = 0;

// Get salary data
$salaryQuery = $conn->prepare("SELECT SUM(total_amount) as total_salary FROM salary_entries WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
if ($salaryQuery) {
    $salaryQuery->bind_param("is", $_SESSION['user_id'], $currentMonth);
    if ($salaryQuery->execute()) {
        $salaryResult = $salaryQuery->get_result();
        if ($salaryResult) {
            $salaryData = $salaryResult->fetch_assoc();
            $totalSalary = $salaryData['total_salary'] ?? 0;
        }
    }
    $salaryQuery->close();
}

// Get expense data
$expenseQuery = $conn->prepare("SELECT SUM(amount) as total_expenses FROM expense_entries WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
if ($expenseQuery) {
    $expenseQuery->bind_param("is", $_SESSION['user_id'], $currentMonth);
    if ($expenseQuery->execute()) {
        $expenseResult = $expenseQuery->get_result();
        if ($expenseResult) {
            $expenseData = $expenseResult->fetch_assoc();
            $totalExpenses = $expenseData['total_expenses'] ?? 0;
        }
    }
    $expenseQuery->close();
}

$balance = $totalSalary - $totalExpenses;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finance Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h2><a href="dashboard.php" class="nav-brand">Finance Tracker</a></h2>
            <div class="nav-links">
                <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <div class="date-display" id="currentDateTime"></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card income">
                <h3>Monthly Income</h3>
                <div class="stat-amount">₱<?php echo number_format($totalSalary, 2); ?></div>
                <div class="stat-label">Total earnings this month</div>
            </div>
            <div class="stat-card expense">
                <h3>Monthly Expenses</h3>
                <div class="stat-amount">₱<?php echo number_format($totalExpenses, 2); ?></div>
                <div class="stat-label">Total spending this month</div>
            </div>
            <div class="stat-card balance">
                <h3>Balance</h3>
                <div class="stat-amount <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                    ₱<?php echo number_format($balance, 2); ?>
                </div>
                <div class="stat-label">
                    <?php echo $balance >= 0 ? 'You\'re saving money!' : 'Spending exceeds income'; ?>
                </div>
            </div>
        </div>

        <div class="navigation-grid">
            <a href="salary-log.php" class="nav-card">
                <div class="nav-icon">💰</div>
                <h3>Income Tracker</h3>
                <p>Track teaching income & monetary gifts</p>
            </a>
            
            <a href="expense-log.php" class="nav-card">
                <div class="nav-icon">💳</div>
                <h3>Expense Tracker</h3>
                <p>Monitor daily and monthly expenses</p>
            </a>
            
            <a href="teaching-reports.php" class="nav-card">
                <div class="nav-icon">🎓</div>
                <h3>Teaching Reports</h3>
                <p>Student stats & income performance</p>
            </a>
            
            <a href="reports.php" class="nav-card">
                <div class="nav-icon">📊</div>
                <h3>Financial Reports</h3>
                <p>Income vs expense analysis</p>
            </a>
        </div>
    </div>

    <footer class="dashboard-footer">
        <a href="https://ruizarquilao08personal.on.drv.tw/www.mywebsite.com/" target="_blank" rel="noopener noreferrer" class="developer-link">
            About the Developer
        </a>
        <p class="copyright">
            Personal Finance Tracker System &copy; 2025 Riv Pia S
        </p>
    </footer>

    <script src="js/dashboard.js"></script>
    <script src="js/register-sw.js"></script>
</body>
</html>