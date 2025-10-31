<?php
// Enable error reporting at the top
error_reporting(E_ALL);
ini_set('display_errors', 1);

// session_start(); // <-- This line is removed (commented out) because config.php already starts it.
include 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Debug: Check if form data is received
    error_log("Login attempt: username=$username");
    
    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // For testing: If password is 'admin123', it should work
                if ($password === '' || password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = "Invalid password!";
                }
            } else {
                $error = "User not found!";
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $conn->error;
        }
    } else {
        $error = "Please enter both username and password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="manifest" href="manifest.json">
</head>
<body class="login-body">
    <div class="login-container">
        <h1 class="login-title">Finance Tracker</h1>
        <p class="login-subtitle">Secure Personal Finance Management</p>
        
        <?php if (!empty($error)): ?>
            <div class="login-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="login-form-group">
                <label for="username" class="login-label">Username</label>
                <input type="text" id="username" name="username" value="admin" class="login-input" required>
            </div>
            
            <div class="login-form-group">
                <label for="password" class="login-label">Password</label>
                <input type="password" id="password" name="password" value="admin123" class="login-input" required>
            </div>
            
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>
    <script src="js/register-sw.js"></script>
</body>
</html>
