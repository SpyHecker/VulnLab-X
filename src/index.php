<?php
require_once 'config.php';

$search = isset($_GET['q']) ? $_GET['q'] : '';
$products = [];

try {
    if ($search) {
        // VULNERABILITY: SQL Injection
        // We concatenate the search string directly into the SQL query
        $query = "SELECT * FROM products WHERE name LIKE '%" . $search . "%' OR description LIKE '%" . $search . "%'";
        $stmt = $db->query($query);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VulnMart - Home</title>
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
        <h2>Products</h2>
        
        <form class="search-bar" method="GET" action="index.php">
            <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>

        <?php if ($search): ?>
            <!-- VULNERABILITY: Reflected XSS -->
            <!-- The search parameter is echoed directly to the page without htmlspecialchars -->
            <p>Showing results for: <?php echo $search; ?></p>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <p style="color:red;">Database Error: <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <div class="product-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <h3><a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                        <p class="price">$<?php echo htmlspecialchars($product['price']); ?></p>
                        <p><?php echo htmlspecialchars($product['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No products found.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="app-footer">
        <p>VulnMart - Intentionally Vulnerable PHP Application.</p>
    </footer>
</div>
</body>
</html>
