<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // VULNERABILITY: SQL Injection
        // We concatenate user input directly into the query
        $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
        $stmt = $db->query($query);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // VULNERABILITY: Broken Auth (No proper session management, just trusting the cookie later or storing basic info)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: /");
            exit;
        } else {
            $error = "Invalid credentials";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VulnMart - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-container">
    <header class="app-header">
        <div class="logo">
            <a href="/">🛒 VulnMart</a>
        </div>
        <nav>
            <a href="/">Home</a>
            <a href="login.php">Login</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="form-container">
            <h2>Login</h2>
            <?php if ($error): ?>
                <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password">
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </main>

    <footer class="app-footer">
        <p>VulnMart - Intentionally Vulnerable PHP Application.</p>
    </footer>
</div>
</body>
</html>
