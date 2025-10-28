<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// --- NEW: Date Range Logic ---
// Get date range from URL, or default to the current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
// Create a query string to append to all redirect URLs
$date_query_string = "&start_date=" . htmlspecialchars($start_date) . "&end_date=" . htmlspecialchars($end_date);


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Add new expense
    if (isset($_POST['add_expense'])) {
        $date = $_POST['date'];
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        
        $stmt = $conn->prepare("INSERT INTO expense_entries (user_id, date, amount, description, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $_SESSION['user_id'], $date, $amount, $description, $category);
        
        if ($stmt->execute()) {
            // MODIFIED: Redirect with date range
            header('Location: expense-log.php?success=1' . $date_query_string);
            exit();
        } else {
            $error = "Error saving expense: " . $conn->error;
        }
    }
    
    // Update expense
    if (isset($_POST['update_expense'])) {
        $expense_id = intval($_POST['expense_id']);
        $date = $_POST['date'];
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        
        $stmt = $conn->prepare("UPDATE expense_entries SET date = ?, amount = ?, description = ?, category = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sdssii", $date, $amount, $description, $category, $expense_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // MODIFIED: Redirect with date range
            header('Location: expense-log.php?success=2' . $date_query_string);
            exit();
        } else {
            $error = "Error updating expense: " . $conn->error;
        }
    }
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM expense_entries WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        // MODIFIED: Redirect with date range
        header('Location: expense-log.php?success=3' . $date_query_string);
        exit();
    } else {
        $error = "Error deleting expense: " . $conn->error;
    }
}

// Get expense for editing
$edit_expense = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM expense_entries WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $edit_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_expense = $result->fetch_assoc();
}

// Get current week dates and expenses (This is always good to have)
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$week_entries_query = $conn->prepare("SELECT * FROM expense_entries WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
$week_entries_query->bind_param("iss", $_SESSION['user_id'], $current_week_start, $current_week_end);
$week_entries_query->execute();
$week_entries = $week_entries_query->get_result();

// Calculate weekly totals
$weekly_totals_query = $conn->prepare("SELECT SUM(amount) as total_expenses, COUNT(*) as expenses_count FROM expense_entries WHERE user_id = ? AND date BETWEEN ? AND ?");
$weekly_totals_query->bind_param("iss", $_SESSION['user_id'], $current_week_start, $current_week_end);
$weekly_totals_query->execute();
$weekly_totals = $weekly_totals_query->get_result()->fetch_assoc();

// --- MODIFIED: Get expenses for the SELECTED DATE RANGE ---
$range_entries_query = $conn->prepare("SELECT * FROM expense_entries WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
$range_entries_query->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
$range_entries_query->execute();
$range_entries = $range_entries_query->get_result();

$range_totals_query = $conn->prepare("SELECT SUM(amount) as total_expenses, COUNT(*) as expenses_count FROM expense_entries WHERE user_id = ? AND date BETWEEN ? AND ?");
$range_totals_query->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
$range_totals_query->execute();
$range_totals = $range_totals_query->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/expense-tracker.css">
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
                <h1>Expense Tracker</h1>
                <button class="btn-primary" onclick="toggleExpenseForm()">+ Add Expense</button>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Edit Expense Form (shown when editing) -->
            <?php if ($edit_expense): ?>
            <div class="edit-form">
                <h3>✏️ Edit Expense</h3>
                <!-- MODIFIED: Form action includes date range -->
                <form method="POST" class="expense-form" action="expense-log.php?<?php echo htmlspecialchars(ltrim($date_query_string, '&')); ?>">
                    <input type="hidden" name="expense_id" value="<?php echo $edit_expense['id']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_date">Date</label>
                            <input type="date" id="edit_date" name="date" value="<?php echo $edit_expense['date']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_amount">Amount (₱)</label>
                            <input type="number" id="edit_amount" name="amount" step="0.01" min="0" value="<?php echo $edit_expense['amount']; ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category" required>
                                <option value="Food" <?php echo $edit_expense['category'] == 'Food' ? 'selected' : ''; ?>>Food & Dining</option>
                                <option value="Transportation" <?php echo $edit_expense['category'] == 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                                <option value="Housing" <?php echo $edit_expense['category'] == 'Housing' ? 'selected' : ''; ?>>Housing & Rent</option>
                                <option value="Entertainment" <?php echo $edit_expense['category'] == 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
                                <option value="Healthcare" <?php echo $edit_expense['category'] == 'Healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                                <option value="Utilities" <?php echo $edit_expense['category'] == 'Utilities' ? 'selected' : ''; ?>>Utilities</option>
                                <option value="Shopping" <?php echo $edit_expense['category'] == 'Shopping' ? 'selected' : ''; ?>>Shopping</option>
                                <option value="Other" <?php echo $edit_expense['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" rows="3" required><?php echo htmlspecialchars($edit_expense['description']); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <!-- MODIFIED: Cancel link includes date range -->
                        <a href="expense-log.php?<?php echo htmlspecialchars(ltrim($date_query_string, '&')); ?>" class="btn-secondary">Cancel</a>
                        <button type="submit" name="update_expense" class="btn-primary">Update Expense</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Add Expense Form -->
            <div id="expenseForm" class="form-container" style="display: <?php echo $edit_expense ? 'none' : 'none'; ?>;">
                <h3>Add New Expense</h3>
                <!-- MODIFIED: Form action includes date range -->
                <form method="POST" class="expense-form" action="expense-log.php?<?php echo htmlspecialchars(ltrim($date_query_string, '&')); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount (₱)</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Food">Food & Dining</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Housing">Housing & Rent</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value_="Healthcare">Healthcare</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Shopping">Shopping</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="What did you spend on?" required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="toggleExpenseForm()">Cancel</button>
                        <button type="submit" name="add_expense" class="btn-primary">Save Expense</button>
                    </div>
                </form>
            </div>

            <!-- This Week's Expenses Table -->
            <div class="table-container">
                <h2 class="section-title">This Week's Expenses</h2>
                <?php if ($week_entries->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $week_total_expenses = 0;
                                while ($entry = $week_entries->fetch_assoc()): 
                                    $week_total_expenses += $entry['amount'];
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['category']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                    <td><strong>₱<?php echo number_format($entry['amount'], 2); ?></strong></td>
                                    <td class="action-buttons">
                                        <!-- MODIFIED: Links include date range -->
                                        <a href="expense-log.php?edit_id=<?php echo $entry['id']; ?><?php echo $date_query_string; ?>" class="btn-edit">Edit</a>
                                        <a href="expense-log.php?delete_id=<?php echo $entry['id']; ?><?php echo $date_query_string; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this expense?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No expenses for this week yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- --- NEW: Custom Date Range Filter --- -->
            <div class="table-container">
                <form method="GET" action="expense-log.php" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 8px;">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 8px;">
                    </div>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 8px 20px; font-size: 0.9rem; margin-bottom: 0;">Filter Expenses</button>
                </form>

                <!-- MODIFIED: Title reflects the date range -->
                <h2 class="section-title">
                    Expenses from <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>
                </h2>

                <?php if ($range_entries->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // $range_total_expenses = 0; // Already calculated in $range_totals
                                $range_entries->data_seek(0); // Reset pointer
                                while ($entry = $range_entries->fetch_assoc()): 
                                    // $range_total_expenses += $entry['amount'];
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['category']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                    <td><strong>₱<?php echo number_format($entry['amount'], 2); ?></strong></td>
                                    <td class="action-buttons">
                                        <!-- MODIFIED: Links include date range -->
                                        <a href="expense-log.php?edit_id=<?php echo $entry['id']; ?><?php echo $date_query_string; ?>" class="btn-edit">Edit</a>
                                        <a href="expense-log.php?delete_id=<?php echo $entry['id']; ?><?php echo $date_query_string; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this expense?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <!-- MODIFIED: Total row -->
                                <tr style="font-weight: bold; background: #f8f9fa;">
                                    <td colspan="3">TOTAL FOR RANGE</td>
                                    <td><strong>₱<?php echo number_format($range_totals['total_expenses'] ?? 0, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No expenses found for the selected date range.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script src="js/expense-tracker.js"></script>
        <script src="js/register-sw.js"></script>
    </body>
</html>