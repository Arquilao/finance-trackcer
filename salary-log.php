<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// *** MODIFIED: Get selected month from URL, or default to current month ***
$selected_month = $_GET['month'] ?? date('Y-m');


// Handle exchange rate delete
if (isset($_GET['delete_rate'])) {
    $delete_rate_id = intval($_GET['delete_rate']);
    $delete_stmt = $conn->prepare("DELETE FROM exchange_rates WHERE id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $delete_rate_id, $_SESSION['user_id']);
    
    if ($delete_stmt->execute()) {
        header('Location: salary-log.php?success=5&month=' . $selected_month); // Keep month
        exit();
    }
}

// Handle exchange rate edit form
if (isset($_GET['edit_rate'])) {
    $edit_rate_id = intval($_GET['edit_rate']);
    $edit_rate_stmt = $conn->prepare("SELECT * FROM exchange_rates WHERE id = ? AND user_id = ?");
    $edit_rate_stmt->bind_param("ii", $edit_rate_id, $_SESSION['user_id']);
    $edit_rate_stmt->execute();
    $edit_rate_result = $edit_rate_stmt->get_result();
    $edit_rate = $edit_rate_result->fetch_assoc();
}

// Handle exchange rate settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_exchange_rate'])) {
    $start_date = $_POST['rate_start_date'];
    $end_date = $_POST['rate_end_date'];
    $new_exchange_rate = floatval($_POST['exchange_rate']);
    
    // Check if rate already exists for this date range
    $check_stmt = $conn->prepare("SELECT id FROM exchange_rates WHERE user_id = ? AND start_date = ? AND end_date = ?");
    $check_stmt->bind_param("iss", $_SESSION['user_id'], $start_date, $end_date);
    $check_stmt->execute();
    $existing_rate = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing_rate) {
        // Update existing rate
        $update_stmt = $conn->prepare("UPDATE exchange_rates SET exchange_rate = ? WHERE id = ?");
        $update_stmt->bind_param("di", $new_exchange_rate, $existing_rate['id']);
        $update_stmt->execute();
    } else {
        // Insert new rate
        $insert_stmt = $conn->prepare("INSERT INTO exchange_rates (user_id, start_date, end_date, exchange_rate) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("issd", $_SESSION['user_id'], $start_date, $end_date, $new_exchange_rate);
        $insert_stmt->execute();
    }
    
    // AUTO-RECALCULATE ALL EXISTING ENTRIES
    $entries_stmt = $conn->prepare("SELECT id, description, usd_rate, currency, total_amount FROM salary_entries WHERE user_id = ?");
    $entries_stmt->bind_param("i", $_SESSION['user_id']);
    $entries_stmt->execute();
    $all_entries = $entries_stmt->get_result();
    
    $updated_count = 0;
    while ($entry = $all_entries->fetch_assoc()) {
        if (strpos($entry['description'], 'Teaching:') !== false) {
            
            // --- NEW: Handle both old and new description formats ---
            $regular_students = 0;
            $trial_students = 0;
            $trial_absent = 0;
            $trial_conversions = 0;

            // Try new format first: Teaching: X regular, Y trial, Z absent, A conversions
            if (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) absent, (\d+) conversions/', $entry['description'], $matches_new)) {
                $regular_students = intval($matches_new[1]);
                $trial_students = intval($matches_new[2]);
                $trial_absent = intval($matches_new[3]);
                $trial_conversions = intval($matches_new[4]);
            } 
            // Fallback to old format: Teaching: X regular, Y trial, A conversions
            elseif (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) conversions/', $entry['description'], $matches_old)) {
                $regular_students = intval($matches_old[1]);
                $trial_students = intval($matches_old[2]);
                $trial_absent = 0; // Old entries have 0 absent
                $trial_conversions = intval($matches_old[3]);
            }
            // --- End of new logic ---

            $regular_income = $regular_students * 2;
            $trial_income = $trial_students * 1;
            $trial_absent_income = $trial_absent * 0.80; // NEW
            $conversion_bonus = $trial_conversions * 3;
            
            $total_usd = $regular_income + $trial_income + $trial_absent_income + $conversion_bonus; // UPDATED
            $total_php = $total_usd * $new_exchange_rate;
            
            // Update the entry with new rate and amount
            $update_entry_stmt = $conn->prepare("UPDATE salary_entries SET total_amount = ?, usd_rate = ? WHERE id = ?");
            $update_entry_stmt->bind_param("ddi", $total_php, $new_exchange_rate, $entry['id']);
            $update_entry_stmt->execute();
            $updated_count++;

        } else {
            // Handle gift entries
            if (strpos($entry['description'], 'Gift:') !== false) {
                // For USD gifts, recalculate PHP amount
                if ($entry['currency'] === 'USD') {
                    // Check for zero usd_rate to avoid division by zero
                    $original_usd_rate = $entry['usd_rate'] != 0 ? $entry['usd_rate'] : $default_exchange_rate; 
                    $gift_amount_usd = $entry['total_amount'] / $original_usd_rate; // Get original USD amount
                    $total_php = $gift_amount_usd * $new_exchange_rate; // Recalculate with new rate
                    
                    $update_entry_stmt = $conn->prepare("UPDATE salary_entries SET total_amount = ?, usd_rate = ? WHERE id = ?");
                    $update_entry_stmt->bind_param("ddi", $total_php, $new_exchange_rate, $entry['id']);
                    $update_entry_stmt->execute();
                    $updated_count++;
                }
                // PHP gifts remain the same (no conversion needed)
            }
        }
    }
    
    header('Location: salary-log.php?success=4&updated=' . $updated_count . '&month=' . $selected_month); // Keep month
    exit();
}

// Handle exchange rate update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_exchange_rate'])) {
    // (This logic is identical to save_exchange_rate, so we apply the same updates)
    $rate_id = intval($_POST['rate_id']);
    $start_date = $_POST['rate_start_date'];
    $end_date = $_POST['rate_end_date'];
    $new_exchange_rate = floatval($_POST['exchange_rate']);
    
    // Update the rate
    $update_stmt = $conn->prepare("UPDATE exchange_rates SET start_date = ?, end_date = ?, exchange_rate = ? WHERE id = ? AND user_id = ?");
    $update_stmt->bind_param("ssdii", $start_date, $end_date, $new_exchange_rate, $rate_id, $_SESSION['user_id']);
    
    if ($update_stmt->execute()) {
        // AUTO-RECALCULATE ALL EXISTING ENTRIES (same as before)
        $entries_stmt = $conn->prepare("SELECT id, description, usd_rate, currency, total_amount FROM salary_entries WHERE user_id = ?");
        $entries_stmt->bind_param("i", $_SESSION['user_id']);
        $entries_stmt->execute();
        $all_entries = $entries_stmt->get_result();
        
        $updated_count = 0;
        while ($entry = $all_entries->fetch_assoc()) {
            if (strpos($entry['description'], 'Teaching:') !== false) {
                
                // --- NEW: Handle both old and new description formats ---
                $regular_students = 0;
                $trial_students = 0;
                $trial_absent = 0;
                $trial_conversions = 0;

                if (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) absent, (\d+) conversions/', $entry['description'], $matches_new)) {
                    $regular_students = intval($matches_new[1]);
                    $trial_students = intval($matches_new[2]);
                    $trial_absent = intval($matches_new[3]);
                    $trial_conversions = intval($matches_new[4]);
                } 
                elseif (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) conversions/', $entry['description'], $matches_old)) {
                    $regular_students = intval($matches_old[1]);
                    $trial_students = intval($matches_old[2]);
                    $trial_absent = 0;
                    $trial_conversions = intval($matches_old[3]);
                }
                // --- End of new logic ---

                $regular_income = $regular_students * 2;
                $trial_income = $trial_students * 1;
                $trial_absent_income = $trial_absent * 0.80; // NEW
                $conversion_bonus = $trial_conversions * 3;
                
                $total_usd = $regular_income + $trial_income + $trial_absent_income + $conversion_bonus; // UPDATED
                $total_php = $total_usd * $new_exchange_rate;
                
                $update_entry_stmt = $conn->prepare("UPDATE salary_entries SET total_amount = ?, usd_rate = ? WHERE id = ?");
                $update_entry_stmt->bind_param("ddi", $total_php, $new_exchange_rate, $entry['id']);
                $update_entry_stmt->execute();
                $updated_count++;
                
            } else {
                if (strpos($entry['description'], 'Gift:') !== false) {
                    if ($entry['currency'] === 'USD') {
                        $original_usd_rate = $entry['usd_rate'] != 0 ? $entry['usd_rate'] : $default_exchange_rate;
                        $gift_amount_usd = $entry['total_amount'] / $original_usd_rate;
                        $total_php = $gift_amount_usd * $new_exchange_rate;
                        
                        $update_entry_stmt = $conn->prepare("UPDATE salary_entries SET total_amount = ?, usd_rate = ? WHERE id = ?");
                        $update_entry_stmt->bind_param("ddi", $total_php, $new_exchange_rate, $entry['id']);
                        $update_entry_stmt->execute();
                        $updated_count++;
                    }
                }
            }
        }
        
        header('Location: salary-log.php?success=6&updated=' . $updated_count . '&month=' . $selected_month); // Keep month
        exit();
    }
}

// Get current exchange rate for today
$current_date = date('Y-m-d');
$exchange_rate_stmt = $conn->prepare("SELECT exchange_rate FROM exchange_rates WHERE user_id = ? AND ? BETWEEN start_date AND end_date ORDER BY created_at DESC LIMIT 1");
$exchange_rate_stmt->bind_param("is", $_SESSION['user_id'], $current_date);
$exchange_rate_stmt->execute();
$exchange_rate_result = $exchange_rate_stmt->get_result();
$current_exchange_rate = $exchange_rate_result->fetch_assoc();

$default_exchange_rate = $current_exchange_rate['exchange_rate'] ?? 56.00;

// Get all exchange rate periods
$all_rates_stmt = $conn->prepare("SELECT * FROM exchange_rates WHERE user_id = ? ORDER BY start_date DESC");
$all_rates_stmt->bind_param("i", $_SESSION['user_id']);
$all_rates_stmt->execute();
$all_rates = $all_rates_stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_salary'])) {
        $date = $_POST['date'];
        $income_type = $_POST['income_type'];
        $exchange_rate = floatval($_POST['exchange_rate']);
        
        $entry_month = date('Y-m', strtotime($date));
        
        $total_php = 0;
        $total_usd = 0;
        $description = "";
        $currency = 'USD';
        
        if ($income_type === 'teaching') {
            $regular_students = intval($_POST['regular_students']);
            $trial_students = intval($_POST['trial_students']);
            $trial_absent = intval($_POST['trial_absent']); // NEW
            $trial_conversions = intval($_POST['trial_conversions']);
            
            $regular_income = $regular_students * 2;
            $trial_income = $trial_students * 1;
            $trial_absent_income = $trial_absent * 0.80; // NEW
            $conversion_bonus = $trial_conversions * 3;
            
            $total_usd = $regular_income + $trial_income + $trial_absent_income + $conversion_bonus; // UPDATED
            
            // UPDATED Description
            $description = "Teaching: {$regular_students} regular, {$trial_students} trial, {$trial_absent} absent, {$trial_conversions} conversions";
            $currency = 'USD';
            
        } elseif ($income_type === 'gift') {
            // (Gift logic unchanged)
            $gift_amount = floatval($_POST['gift_amount']);
            $gift_currency = $_POST['gift_currency'];
            $gift_description = trim($_POST['gift_description']);
            
            if ($gift_currency === 'USD') {
                $total_usd = $gift_amount;
            } else {
                $total_php = $gift_amount;
                $total_usd = $exchange_rate > 0 ? $gift_amount / $exchange_rate : 0;
            }
            
            $description = "Gift: " . ($gift_description ?: "Monetary gift from student/parent");
            $currency = $gift_currency;
        }

        // Calculate total_php for all types
        if ($currency === 'USD') {
             $total_php = $total_usd * $exchange_rate;
        }
        
        $hours_worked = 0;
        $hourly_rate = 0;
        
        if ($income_type === 'teaching') {
            // UPDATED: Absent students also count as 0.5 hours
            $hours_worked = ($regular_students + $trial_students + $trial_absent) * 0.5; 
            $hourly_rate = $hours_worked > 0 ? $total_php / $hours_worked : 0;
        }
        
        $stmt = $conn->prepare("INSERT INTO salary_entries (user_id, date, hours_worked, hourly_rate, usd_rate, total_amount, currency, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("isddddss", 
                $_SESSION['user_id'], 
                $date, 
                $hours_worked, 
                $hourly_rate, 
                $exchange_rate, 
                $total_php, 
                $currency, 
                $description
            );
            
            if ($stmt->execute()) {
                header('Location: salary-log.php?success=1&month=' . $entry_month);
                exit();
            } else {
                $error = "Error saving entry: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
    
    // Handle update
    if (isset($_POST['update_salary'])) {
        $entry_id = intval($_POST['entry_id']);
        $date = $_POST['date'];
        $income_type = $_POST['income_type'];
        $exchange_rate = floatval($_POST['exchange_rate']);
        
        $entry_month = date('Y-m', strtotime($date));
        
        $total_php = 0;
        $total_usd = 0;
        $description = "";
        $currency = 'USD';
        
        if ($income_type === 'teaching') {
            $regular_students = intval($_POST['regular_students']);
            $trial_students = intval($_POST['trial_students']);
            $trial_absent = intval($_POST['trial_absent']); // NEW
            $trial_conversions = intval($_POST['trial_conversions']);
            
            $regular_income = $regular_students * 2;
            $trial_income = $trial_students * 1;
            $trial_absent_income = $trial_absent * 0.80; // NEW
            $conversion_bonus = $trial_conversions * 3;
            
            $total_usd = $regular_income + $trial_income + $trial_absent_income + $conversion_bonus; // UPDATED
            
            // UPDATED Description
            $description = "Teaching: {$regular_students} regular, {$trial_students} trial, {$trial_absent} absent, {$trial_conversions} conversions";
            $currency = 'USD';
            
        } elseif ($income_type === 'gift') {
            // (Gift logic unchanged)
            $gift_amount = floatval($_POST['gift_amount']);
            $gift_currency = $_POST['gift_currency'];
            $gift_description = trim($_POST['gift_description']);
            
            if ($gift_currency === 'USD') {
                $total_usd = $gift_amount;
            } else {
                $total_php = $gift_amount;
                $total_usd = $exchange_rate > 0 ? $gift_amount / $exchange_rate : 0;
            }
            
            $description = "Gift: " . ($gift_description ?: "Monetary gift from student/parent");
            $currency = $gift_currency;
        }
        
        // Calculate total_php for all types
        if ($currency === 'USD') {
             $total_php = $total_usd * $exchange_rate;
        }
        
        $hours_worked = 0;
        $hourly_rate = 0;
        
        if ($income_type === 'teaching') {
            // UPDATED: Absent students also count as 0.5 hours
            $hours_worked = ($regular_students + $trial_students + $trial_absent) * 0.5;
            $hourly_rate = $hours_worked > 0 ? $total_php / $hours_worked : 0;
        }
        
        $stmt = $conn->prepare("UPDATE salary_entries SET date = ?, hours_worked = ?, hourly_rate = ?, usd_rate = ?, total_amount = ?, currency = ?, description = ? WHERE id = ? AND user_id = ?");
        
        if ($stmt) {
            $stmt->bind_param("sddddssii", 
                $date, 
                $hours_worked, 
                $hourly_rate, 
                $exchange_rate, 
                $total_php, 
                $currency, 
                $description,
                $entry_id,
                $_SESSION['user_id']
            );
            
            if ($stmt->execute()) {
                header('Location: salary-log.php?success=2&month=' . $entry_month);
                exit();
            } else {
                $error = "Error updating entry: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $entry_month = $_GET['month'] ?? date('Y-m');
    
    $stmt = $conn->prepare("DELETE FROM salary_entries WHERE id = ? AND user_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            header('Location: salary-log.php?success=3&month=' . $entry_month);
            exit();
        }
    }
}

// Handle cancel edit
if (isset($_GET['cancel_edit'])) {
    header('Location: salary-log.php?month=' . $selected_month);
    exit();
}

// Get salary for editing
$edit_salary = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM salary_entries WHERE id = ? AND user_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("ii", $edit_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_salary = $result->fetch_assoc();
        
        if ($edit_salary) {
            $selected_month = date('Y-m', strtotime($edit_salary['date']));
            
            if (strpos($edit_salary['description'], 'Teaching:') !== false) {
                $edit_salary['income_type'] = 'teaching';
                
                // --- NEW: Handle both old and new description formats ---
                // Try new format first
                if (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) absent, (\d+) conversions/', $edit_salary['description'], $matches_new)) {
                    $edit_salary['regular_students'] = $matches_new[1];
                    $edit_salary['trial_students'] = $matches_new[2];
                    $edit_salary['trial_absent'] = $matches_new[3];
                    $edit_salary['trial_conversions'] = $matches_new[4];
                } 
                // Fallback to old format
                elseif (preg_match('/Teaching: (\d+) regular, (\d+) trial, (\d+) conversions/', $edit_salary['description'], $matches_old)) {
                    $edit_salary['regular_students'] = $matches_old[1];
                    $edit_salary['trial_students'] = $matches_old[2];
                    $edit_salary['trial_absent'] = 0; // Default old entries to 0
                    $edit_salary['trial_conversions'] = $matches_old[3];
                }
                // --- End of new logic ---

            } else {
                $edit_salary['income_type'] = 'gift';
                $edit_salary['gift_description'] = str_replace('Gift: ', '', $edit_salary['description']);
                if ($edit_salary['currency'] === 'USD') {
                    $original_usd_rate = $edit_salary['usd_rate'] != 0 ? $edit_salary['usd_rate'] : $default_exchange_rate;
                    $edit_salary['gift_amount'] = $edit_salary['total_amount'] / $original_usd_rate;
                } else {
                    $edit_salary['gift_amount'] = $edit_salary['total_amount'];
                }
            }
        }
    }
}

// Get current week dates and entries
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$week_entries_query = $conn->prepare("SELECT * FROM salary_entries WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
if ($week_entries_query) {
    $week_entries_query->bind_param("iss", $_SESSION['user_id'], $current_week_start, $current_week_end);
    $week_entries_query->execute();
    $week_entries = $week_entries_query->get_result();
} else {
    $week_entries = null;
}

// Calculate weekly totals
$weekly_totals_query = $conn->prepare("SELECT SUM(total_amount) as total_income, COUNT(*) as entries_count FROM salary_entries WHERE user_id = ? AND date BETWEEN ? AND ?");
if ($weekly_totals_query) {
    $weekly_totals_query->bind_param("iss", $_SESSION['user_id'], $current_week_start, $current_week_end);
    $weekly_totals_query->execute();
    $weekly_totals_result = $weekly_totals_query->get_result();
    $weekly_totals = $weekly_totals_result ? $weekly_totals_result->fetch_assoc() : [];
} else {
    $weekly_totals = [];
}

// Get entries and totals for the SELECTED month
$month_entries_query = $conn->prepare("SELECT * FROM salary_entries WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? ORDER BY date DESC");
if ($month_entries_query) {
    $month_entries_query->bind_param("is", $_SESSION['user_id'], $selected_month);
    $month_entries_query->execute();
    $month_entries = $month_entries_query->get_result();
} else {
    $month_entries = null;
}

$monthly_totals_query = $conn->prepare("SELECT SUM(total_amount) as total_income, COUNT(*) as entries_count FROM salary_entries WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
if ($monthly_totals_query) {
    $monthly_totals_query->bind_param("is", $_SESSION['user_id'], $selected_month);
    $monthly_totals_query->execute();
    $monthly_totals_result = $monthly_totals_query->get_result();
    $monthly_totals = $monthly_totals_result ? $monthly_totals_result->fetch_assoc() : [];
} else {
    $monthly_totals = [];
}

// Get yearly entries and totals
$current_year = date('Y');
$yearly_totals_query = $conn->prepare("SELECT SUM(total_amount) as total_income FROM salary_entries WHERE user_id = ? AND DATE_FORMAT(date, '%Y') = ?");
if ($yearly_totals_query) {
    $yearly_totals_query->bind_param("is", $_SESSION['user_id'], $current_year);
    $yearly_totals_query->execute();
    $yearly_totals_result = $yearly_totals_query->get_result();
    $yearly_totals = $yearly_totals_result ? $yearly_totals_result->fetch_assoc() : [];
} else {
    $yearly_totals = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Income Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/salary-tracker.css">
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
                <h1>Teaching Income Tracker</h1>
                <?php if (!isset($_GET['edit_id'])): ?>
                    <button class="btn-primary" onclick="toggleSalaryForm()">+ Add Income Entry</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">
                    <?php 
                    switch($_GET['success']) {
                        case 1: echo "Income entry added successfully!"; break;
                        case 2: echo "Income entry updated successfully!"; break;
                        case 3: echo "Income entry deleted successfully!"; break;
                        case 4: 
                            $updated_count = $_GET['updated'] ?? 0;
                            echo "Exchange rate saved successfully! ";
                            echo "Updated {$updated_count} existing entries with new rate.";
                            break;
                        case 5: echo "Exchange rate deleted successfully!"; break;
                        case 6: 
                            $updated_count = $_GET['updated'] ?? 0;
                            echo "Exchange rate updated successfully! ";
                            echo "Updated {$updated_count} existing entries with new rate.";
                            break;
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="stat-card">
                    <div style="font-size: 0.9rem; color: #666;">This Week's Income</div>
                    <div class="stat-value">₱<?php echo number_format($weekly_totals['total_income'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 0.9rem; color: #666;">Month's Income</div>
                    <div class="stat-value">₱<?php echo number_format($monthly_totals['total_income'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 0.9rem; color: #666;">This Year's Income</div>
                    <div class="stat-value">₱<?php echo number_format($yearly_totals['total_income'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- Exchange Rate Management -->
<div class="exchange-rate-section">
    <h3>💱 Manage Exchange Rates</h3>
    <div class="rate-info">
        <strong>How it works:</strong><br>
        • Set exchange rates for specific date ranges<br>
        • When saving, ALL existing entries will be automatically recalculated<br>
        • Weekly and monthly totals will update immediately<br>
        • No need to manually update entries!
    </div>
    
    <!-- Add/Edit Exchange Rate Form -->
    <form method="POST" class="salary-form" action="salary-log.php?month=<?php echo $selected_month; ?>">
        <?php if (isset($edit_rate)): ?>
            <input type="hidden" name="rate_id" value="<?php echo $edit_rate['id']; ?>">
            <input type="hidden" name="update_exchange_rate" value="1">
            <h4>Edit Exchange Rate</h4>
            <a href="salary-log.php?month=<?php echo $selected_month; ?>" class="btn-secondary" style="margin-bottom: 15px;">← Cancel Edit</a>
        <?php else: ?>
            <input type="hidden" name="save_exchange_rate" value="1">
            <h4>Add New Exchange Rate</h4>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="rate_start_date">Start Date</label>
                <input type="date" id="rate_start_date" name="rate_start_date" 
                       value="<?php echo isset($edit_rate) ? $edit_rate['start_date'] : date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="rate_end_date">End Date</label>
                <input type="date" id="rate_end_date" name="rate_end_date" 
                       value="<?php echo isset($edit_rate) ? $edit_rate['end_date'] : date('Y-m-d', strtotime('+30 days')); ?>" required>
            </div>
            <div class="form-group">
                <label for="exchange_rate">Exchange Rate (1 USD = ? PHP)</label>
                <input type="number" id="exchange_rate" name="exchange_rate" step="0.0001" 
                       value="<?php echo isset($edit_rate) ? $edit_rate['exchange_rate'] : $default_exchange_rate; ?>" required>
            </div>
        </div>
        <div class="form-actions">
            <?php if (isset($edit_rate)): ?>
                <button type="submit" class="btn-primary">Update Exchange Rate</button>
            <?php else: ?>
                <button type="submit" class="btn-primary">Save Exchange Rate</button>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Current Exchange Rates -->
    <?php if ($all_rates->num_rows > 0): ?>
    <div style="margin-top: 20px;">
        <h4>Current Exchange Rate Periods</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Exchange Rate</th>
                        <th>Date Range</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($rate = $all_rates->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($rate['start_date'])); ?> to <?php echo date('M j, Y', strtotime($rate['end_date'])); ?></td>
                        <td><strong>1 USD = <?php echo number_format($rate['exchange_rate'], 4); ?> PHP</strong></td>
                        <td>
                            <?php 
                            $today = date('Y-m-d');
                            if ($today >= $rate['start_date'] && $today <= $rate['end_date']) {
                                echo '<span style="color: #28a745;">● Active</span>';
                            } elseif ($today < $rate['start_date']) {
                                echo '<span style="color: #ffc107;">● Future</span>';
                            } else {
                                echo '<span style="color: #6c757d;">● Expired</span>';
                            }
                            ?>
                        </td>
                        <td class="action-buttons">
                            <a href="salary-log.php?edit_rate=<?php echo $rate['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-edit">Edit</a>
                            <a href="salary-log.php?delete_rate=<?php echo $rate['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-delete" 
                               onclick="return confirm('Delete this exchange rate? This will NOT affect existing entries.')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

            <!-- Income Entry Form -->
            <div id="salaryForm" class="form-container" style="<?php echo (isset($_GET['edit_id']) || isset($_GET['add'])) ? 'display: block;' : 'display: none;'; ?>">
                <h3><?php echo isset($_GET['edit_id']) ? 'Edit Income Entry' : 'Add Income Entry'; ?></h3>
                
                <?php if (isset($_GET['edit_id'])): ?>
                    <div class="edit-mode-banner">
                        <a href="salary-log.php?cancel_edit=1&month=<?php echo $selected_month; ?>" class="btn-cancel" style="margin-left: 10px;">Cancel Edit</a>
                    </div>
                <?php endif; ?>
                
                <!-- Income Type Selection -->
                <div class="income-type-tabs">
                    <div class="income-tab <?php echo ((!isset($edit_salary['income_type']) && !isset($_GET['edit_id'])) || (isset($edit_salary['income_type']) && $edit_salary['income_type'] === 'teaching')) ? 'active' : ''; ?>" onclick="selectIncomeType('teaching')">
                        🎓 Teaching Income
                    </div>
                    <div class="income-tab <?php echo (isset($edit_salary['income_type']) && $edit_salary['income_type'] === 'gift') ? 'active' : ''; ?>" onclick="selectIncomeType('gift')">
                        💝 Monetary Gift
                    </div>
                </div>

                <form method="POST" class="salary-form" id="salaryFormElement">
                    <?php if (isset($_GET['edit_id'])): ?>
                        <input type="hidden" name="entry_id" value="<?php echo $edit_salary['id']; ?>">
                        <input type="hidden" name="update_salary" value="1">
                    <?php else: ?>
                        <input type="hidden" name="add_salary" value="1">
                    <?php endif; ?>
                    
                    <input type="hidden" name="income_type" id="income_type" value="<?php echo isset($edit_salary['income_type']) ? $edit_salary['income_type'] : 'teaching'; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" value="<?php echo isset($edit_salary['date']) ? $edit_salary['date'] : date('Y-m-d'); ?>" required onchange="updateExchangeRateForDate(this.value)">
                        </div>
                        <div class="form-group">
                            <label for="exchange_rate">Exchange Rate (1 USD = ? PHP)</label>
                            <input type="number" id="exchange_rate" name="exchange_rate" step="0.0001" 
                                value="<?php echo isset($edit_salary['usd_rate']) ? $edit_salary['usd_rate'] : $default_exchange_rate; ?>" 
                                required oninput="calculateIncome()">
                            <small>Auto-selected based on date. Change if needed.</small>
                        </div>
                    </div>

                    <!-- Teaching Income Form -->
                    <div id="teachingForm" class="income-form <?php echo ((!isset($edit_salary['income_type']) && !isset($_GET['edit_id'])) || (isset($edit_salary['income_type']) && $edit_salary['income_type'] === 'teaching')) ? 'active' : ''; ?>">
                        
                        <!-- UPDATED Rate Info -->
                        <div class="rate-info">
                            <strong>Teaching Rates:</strong><br>
                            • Regular Student: $2 per 30-min class<br>
                            • Trial Student: $1 per 30-min class<br>
                            • Trial Class Absent: $0.80 per 30-min class<br>
                            • Conversion Bonus: +$3 when trial student enrolls
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="regular_students">Regular Students</label>
                                <input type="number" id="regular_students" name="regular_students" min="0" value="<?php echo isset($edit_salary['regular_students']) ? $edit_salary['regular_students'] : '0'; ?>" oninput="calculateIncome()">
                                <small>Number of regular students taught</small>
                            </div>
                            <div class="form-group">
                                <label for="trial_students">Trial Students</label>
                                <input type="number" id="trial_students" name="trial_students" min="0" value="<?php echo isset($edit_salary['trial_students']) ? $edit_salary['trial_students'] : '0'; ?>" oninput="calculateIncome()">
                                <small>Number of trial students taught</small>
                            </div>
                        </div>
                        <div class="form-row">
                             <!-- NEW Input Field -->
                            <div class="form-group">
                                <label for="trial_absent">Trial Class Absent</label>
                                <input type="number" id="trial_absent" name="trial_absent" min="0" value="<?php echo isset($edit_salary['trial_absent']) ? $edit_salary['trial_absent'] : '0'; ?>" oninput="calculateIncome()">
                                <small>Number of absent trial students</small>
                            </div>
                            <div class="form-group">
                                <label for="trial_conversions">Trial Conversions</label>
                                <input type="number" id="trial_conversions" name="trial_conversions" min="0" value="<?php echo isset($edit_salary['trial_conversions']) ? $edit_salary['trial_conversions'] : '0'; ?>" oninput="calculateIncome()">
                                <small>Number of trial students who enrolled</small>
                            </div>
                        </div>

                        <div class="calculation-preview" id="teachingCalculation">
                            <!-- This will be filled by salary-tracker.js -->
                        </div>
                    </div>

                    <!-- Gift Income Form -->
                    <div id="giftForm" class="income-form <?php echo (isset($edit_salary['income_type']) && $edit_salary['income_type'] === 'gift') ? 'active' : ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="gift_amount">Gift Amount</label>
                                <input type="number" id="gift_amount" name="gift_amount" step="0.01" min="0" value="<?php echo isset($edit_salary['gift_amount']) ? $edit_salary['gift_amount'] : '0'; ?>" oninput="calculateIncome()">
                            </div>
                            <div class="form-group">
                                <label for="gift_currency">Currency</label>
                                <select id="gift_currency" name="gift_currency" oninput="calculateIncome()">
                                    <option value="PHP" <?php echo (isset($edit_salary['currency']) && $edit_salary['currency'] === 'PHP') ? 'selected' : ''; ?>>Philippine Peso (₱)</option>
                                    <option value="USD" <?php echo (isset($edit_salary['currency']) && $edit_salary['currency'] === 'USD') ? 'selected' : ''; ?>>US Dollar ($)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="gift_description">Gift Description (Optional)</label>
                            <input type="text" id="gift_description" name="gift_description" placeholder="e.g., Gift from student Maria's parents" value="<?php echo isset($edit_salary['gift_description']) ? htmlspecialchars($edit_salary['gift_description']) : ''; ?>">
                        </div>

                        <div class="calculation-preview" id="giftCalculation">
                            <!-- This will be filled by salary-tracker.js -->
                        </div>
                    </div>

                    <div class="form-actions">
                        <?php if (isset($_GET['edit_id'])): ?>
                            <a href="salary-log.php?cancel_edit=1&month=<?php echo $selected_month; ?>" class="btn-secondary">Cancel</a>
                            <button type="submit" class="btn-primary">Update Income Entry</button>
                        <?php else: ?>
                            <button type="button" class="btn-secondary" onclick="toggleSalaryForm()">Cancel</button>
                            <button type="submit" class="btn-primary">Save Income Entry</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- This Week's Entries -->
            <div class="table-container">
                <h2 class="section-title">This Week's Income</h2>
                <?php if ($week_entries && $week_entries->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount (PHP)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $week_total_income = 0;
                                while ($entry = $week_entries->fetch_assoc()): 
                                    $week_total_income += $entry['total_amount'];
                                    
                                    $description = $entry['description'] ?? 'No description';
                                    $type = (strpos($description, 'Teaching:') !== false) ? '🎓 Teaching' : '💝 Gift';
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['date'])); ?></td>
                                    <td><?php echo $type; ?></td>
                                    <td><?php echo htmlspecialchars($description); ?></td>
                                    <td><strong class="peso-amount">₱<?php echo number_format($entry['total_amount'], 2); ?></strong></td>
                                    <td class="action-buttons">
                                        <a href="salary-log.php?edit_id=<?php echo $entry['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-edit">Edit</a>
                                        <a href="salary-log.php?delete_id=<?php echo $entry['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-delete" onclick="return confirm('Delete this income entry?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="week-total-row">
                                    <td colspan="3"><strong>WEEKLY TOTAL</strong></td>
                                    <td><strong class="peso-amount">₱<?php echo number_format($week_total_income, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No income entries for this week yet.</p>
                        <p>Click "Add Income Entry" to start tracking!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Monthly Entries -->
            <div class="table-container">
                
                <form method="GET" action="salary-log.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <label for="month" style="font-weight: 600; margin-bottom: 0;">Change Month:</label>
                    <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>" class="form-group" style="padding: 8px; margin-bottom: 0; flex: 1;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 8px 15px; font-size: 0.9rem;">Go</button>
                </form>
                
                <h2 class="section-title">All Income - <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h2>
                
                <?php if ($month_entries && $month_entries->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount (PHP)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $month_total_income = 0; 
                                $month_entries->data_seek(0); 
                                
                                while ($entry = $month_entries->fetch_assoc()): 
                                    $month_total_income += $entry['total_amount'];
                                    
                                    $description = $entry['description'] ?? 'No description';
                                    $type = (strpos($description, 'Teaching:') !== false) ? '🎓 Teaching' : '💝 Gift';
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($entry['date'])); ?></td>
                                    <td><?php echo $type; ?></td>
                                    <td><?php echo htmlspecialchars($description); ?></td>
                                    <td><strong class="peso-amount">₱<?php echo number_format($entry['total_amount'], 2); ?></strong></td>
                                    <td class="action-buttons">
                                        <a href="salary-log.php?edit_id=<?php echo $entry['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-edit">Edit</a>
                                        <a href="salary-log.php?delete_id=<?php echo $entry['id']; ?>&month=<?php echo $selected_month; ?>" class="btn-delete" onclick="return confirm('Delete this income entry?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <tr class="week-total-row"> 
                                    <td colspan="3"><strong>MONTHLY TOTAL</strong></td>
                                    <td><strong class="peso-amount">₱<?php echo number_format($monthly_totals['total_income'] ?? 0, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No income entries for this month yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <script src="js/salary-tracker.js"></script>
        <script src="js/register-sw.js"></script>
    </body>
</html>