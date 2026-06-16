<?php
require_once 'config.php';

$id = $_GET['id'] ?? null;
$product = null;
$error = '';

if ($id) {
    try {
        // VULNERABILITY: SQL Injection
        // We concatenate the id directly into the query
        $query = "SELECT * FROM products WHERE id = " . $id;
        $stmt = $db->query($query);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $error = "Product not found.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
} else {
    $error = "No product ID specified.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VulnMart - Product</title>
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
        <?php if ($error): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif ($product): ?>
            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                <p class="price">$<?php echo htmlspecialchars($product['price']); ?></p>
                <p><?php echo htmlspecialchars($product['description']); ?></p>
                
                <div style="margin-top: 2rem;">
                    <!-- VULNERABILITY: IDOR/LFI/SSRF vector -->
                    <!-- In a real app this might read a file from disk based on user input, or fetch a URL -->
                    <!-- Here we just display the image name, but an attacker might try to load external URLs if the app fetches it server-side -->
                    <img src="<?php echo htmlspecialchars(strpos($product['image'], 'http') === 0 ? $product['image'] : '/images/' . $product['image']); ?>" alt="Product Image" style="max-width: 100%; border-radius: 4px;">
                </div>
                
                <button class="auto-width" style="margin-top: 2rem;">Add to Cart</button>
            </div>
        <?php endif; ?>
    </main>

    <footer class="app-footer">
        <p>VulnMart - Intentionally Vulnerable PHP Application.</p>
    </footer>
</div>
</body>
</html>
