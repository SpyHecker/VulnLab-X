<?php
require_once 'config.php';

echo "Initializing database...<br>";

try {
    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        email TEXT,
        role TEXT DEFAULT 'customer'
    )");

    // Create products table
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        description TEXT,
        price REAL,
        image TEXT
    )");

    // Check if products exist, if not seed
    $stmt = $db->query("SELECT count(*) as count FROM products");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        $db->exec("INSERT INTO products (name, description, price, image) VALUES 
            ('Laptop', 'High performance laptop', 999.99, 'laptop.jpg'),
            ('Smartphone', 'Latest model smartphone', 699.99, 'phone.jpg'),
            ('Headphones', 'Noise-cancelling headphones', 199.99, 'headphones.jpg')
        ");
        echo "Products seeded.<br>";
    } else {
        echo "Products already seeded.<br>";
    }

    // Check if admin user exists, if not seed
    $stmt = $db->query("SELECT count(*) as count FROM users WHERE username = 'admin'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        // Plaintext password for vulnerability
        $db->exec("INSERT INTO users (username, password, email, role) VALUES 
            ('admin', 'supersecretadmin123', 'admin@vulnmart.local', 'admin')
        ");
        echo "Admin user created.<br>";
    } else {
        echo "Admin user already exists.<br>";
    }

    echo "Database initialization complete! <a href='/'>Go to homepage</a>";
} catch (PDOException $e) {
    echo "Error initializing database: " . $e->getMessage();
}
?>
