# VulnMart Solutions Guide 🛡️

This document outlines the vulnerabilities intentionally introduced into the VulnMart PHP application and provides the appropriate mitigation strategies for each.

---

## 1. SQL Injection (SQLi)
**Location:** `src/login.php` & `src/index.php` (Search) & `src/product.php`

### The Vulnerability
In several places across the application, user input is concatenated directly into SQL queries without sanitization or parameterization. For example, in `login.php`:
```php
$query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
```
This allows an attacker to input a payload like `admin' OR '1'='1` in the username field. The resulting query becomes `SELECT * FROM users WHERE username = 'admin' OR '1'='1' AND password = ''`, which evaluates to true and bypasses authentication.

### The Solution: Prepared Statements
To fix this, you must separate the SQL query structure from the data using **Prepared Statements (PDO)**.

**Patched Code (`login.php`):**
```php
// Prepare the SQL statement with placeholders
$stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND password = :password");

// Execute the statement by binding the parameters
$stmt->execute([
    ':username' => $username,
    ':password' => $password
]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```
*(Note: Passwords should also be hashed, as detailed in the Broken Authentication section below).*

---

## 2. Reflected Cross-Site Scripting (XSS)
**Location:** `src/index.php` (Search Query Display)

### The Vulnerability
When a user searches for a product, the search term is retrieved from the `$_GET['q']` parameter and echoed directly onto the page without escaping HTML characters:
```php
<p>Showing results for: <?php echo $search; ?></p>
```
If an attacker searches for `<script>alert(1)</script>`, the browser will execute the JavaScript.

### The Solution: Output Encoding
All user-supplied data must be properly encoded before being rendered in the HTML context. In PHP, this is done using `htmlspecialchars()`.

**Patched Code (`index.php`):**
```php
<p>Showing results for: <?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?></p>
```

---

## 3. Broken Authentication (Insecure Password Storage)
**Location:** `src/init_db.php` & `src/login.php`

### The Vulnerability
The application stores passwords in plaintext inside the database (`supersecretadmin123`). If the database is compromised, attackers immediately gain access to all user accounts. Furthermore, the login logic performs a direct string comparison.

### The Solution: Password Hashing
Passwords should be hashed using a strong, salted cryptographic algorithm like `bcrypt` before being stored.

**Patched Code (`init_db.php` - Seeding):**
```php
$hashedPassword = password_hash('supersecretadmin123', PASSWORD_DEFAULT);
$db->exec("INSERT INTO users (username, password, email, role) VALUES ('admin', '$hashedPassword', 'admin@vulnmart.local', 'admin')");
```

**Patched Code (`login.php` - Verification):**
```php
// Fetch user by username ONLY
$stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify the password hash
if ($user && password_verify($password, $user['password'])) {
    // Login successful
}
```

---

## 4. SSRF / IDOR Vector (Insecure Asset Loading)
**Location:** `src/product.php`

### The Vulnerability
The application assumes that product images could either be local file paths or external HTTP URLs, taking the image value directly from the database and putting it in an `<img>` tag. While currently limited to client-side injection, if this parameter were used in a server-side fetch (e.g., `file_get_contents($product['image'])`), it would result in a **Server-Side Request Forgery (SSRF)**. If it were used to fetch local files, it could lead to **Local File Inclusion (LFI)**.

### The Solution: Whitelisting & Validation
Avoid accepting arbitrary URLs or file paths. If you must accept URLs, validate that they point to trusted domains. For local files, ensure the path does not contain directory traversal characters (`../`) and map identifiers to files safely.

**Patched Concept:**
```php
// Only allow specific trusted domains if URLs are allowed
$allowedDomain = "https://trusted-cdn.com/";
if (strpos($product['image'], $allowedDomain) === 0) {
    $imageUrl = $product['image'];
} else {
    // Fallback to strict local path mapping
    $imageUrl = '/images/' . basename($product['image']); 
}
```

---

## Conclusion
By implementing **Prepared Statements**, strict **Output Encoding**, **Cryptographic Hashing**, and **Input Validation**, the vast majority of these classic web vulnerabilities can be completely eliminated.
