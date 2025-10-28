<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="manifest" href="manifest.json">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <h1>Personal Tracker</h1>
            <p class="subtitle">Secure Personal Finance Management</p>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script src="js/register-sw.js"></script>
</body>
</html>