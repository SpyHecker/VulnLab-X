# VulnMart 🛒💥

**VulnMart** is an intentionally vulnerable e-commerce application you can run locally to practise
web security. It is a real (small) PHP + SQLite shop — catalogue, accounts, cart, checkout, orders,
reviews, a seller area and an admin panel — where every layer contains deliberately broken code that
maps to the OWASP Top 10.

> ⚠️ **Educational use only.** The application is deliberately insecure: plain text passwords,
> SQL injection, remote code execution through file writes and uploads, missing authorisation checks.
> Never deploy it on a public host, and only test systems you own or have written permission to test.

---

## Quick start

### Docker (recommended)

```bash
docker compose up --build -d      # or: make up
```

Open <http://localhost:8080>. The database is created and seeded automatically on the first request;
open <http://localhost:8080/init_db.php> to inspect it, re-seed it or reset the whole lab.

### Local PHP

Requires PHP 8.2+ with `pdo_sqlite` (and `libxml`/`simplexml`, which ship with PHP):

```bash
php -S 127.0.0.1:8080 -t src      # or: make serve
```

The SQLite database is written to `data/vulnmart.db` (override with `DB_PATH`, or `DATA_DIR` to move
the whole folder).

### Demo accounts

| Username | Password              | Role     |
| -------- | --------------------- | -------- |
| `admin`  | `supersecretadmin123` | admin    |
| `alice`  | `alice123`            | customer |
| `bob`    | `bob123`              | customer |
| `seller` | `seller123`           | seller   |

The same table is printed on `/init_db.php`.

---

## What the shop can do

| Area | Implemented |
| ---- | ----------- |
| Catalogue | 8 seeded products, category filter, search, product detail pages |
| Reviews | Star ratings, average score, review form on the product page |
| Accounts | Registration, login, "remember me", profile with biography, logout |
| Cart | Session cart, quantity updates, discount coupons |
| Checkout | Shipping address, order creation, stock updates, order confirmation |
| Orders | Order history, order detail, cancellation |
| Seller | Dashboard, product editor, XML bulk import, remote image fetcher |
| Admin | Dashboard statistics, user management, product management, activity log |
| API | JSON cart endpoint, preferences endpoint |
| Tooling | `make` targets, smoke test suite, CI workflow |

---

## Vulnerabilities

Every intentional flaw is tagged in the source with a `VULNERABILITY [ID]` comment and documented
with an exploit walkthrough and a fix in **[solution.md](solution.md)**.

| ID | Vulnerability | Where | Flag |
| -- | ------------- | ----- | ---- |
| SQLI-1 | SQL injection in the product search | `src/index.php` | |
| SQLI-2 | SQL injection in the product id | `src/product.php` | |
| SQLI-3 | SQL injection in the login form | `src/login.php` | |
| SQLI-4 | SQL injection in registration | `src/register.php` | |
| SQLI-5 | SQL injection in the category filter | `src/index.php` | |
| SQLI-6 | SQL injection in review submission | `src/product.php` | |
| SQLI-7 | SQL injection in the admin user search | `src/admin/users.php` | |
| SQLI-8 | SQL injection in the admin log filter | `src/admin/logs.php` | |
| XSS-1 | Reflected XSS in the search summary | `src/index.php` | |
| XSS-2 | Stored XSS in reviews / imported product names | `src/product.php`, `src/seller/bulk_import.php` | |
| XSS-3 | Stored XSS in profiles and order notes | `src/profile.php`, `src/order.php` | |
| XSS-4 | Stored XSS through the logged `User-Agent` | `src/admin/logs.php` | |
| XSS-5 | Stored XSS in flash messages | `src/includes/header.php` | |
| IDOR-1 | Any order is readable by any signed in user | `src/order.php`, `src/checkout.php` | `FLAG{1d0r_0rd3r_d3t41l}` |
| IDOR-2 | Any profile is readable | `src/profile.php` | `FLAG{1d0r_pr0f1l3_r34d}` |
| IDOR-3 | Any profile / product is writable | `src/profile.php`, `src/seller/product_edit.php` | |
| IDOR-4 | Any order can be cancelled | `src/order.php` | |
| IDOR-5 | Admin role changes are not constrained | `src/admin/users.php` | |
| AUTH-1 | Plain text password storage, no password policy | `src/includes/schema.php` | |
| AUTH-2 | Forgeable `remember_me` cookie (`base64(id:username)`) | `src/includes/functions.php` | |
| AUTH-3 | No session rotation, cookie readable by JavaScript | `src/config.php`, `src/login.php` | |
| AUTH-4 | Open redirect through the `?next=` parameter | `src/login.php` | |
| AUTH-5 | Mass assignment (`role=admin`, `seller_id=…`) | `src/register.php`, `src/seller/product_edit.php` | `FLAG{4dm1n_p4n3l_4cc3ss}` |
| AUTH-6 | Logout keeps the remember-me cookie | `src/logout.php` | |
| AUTH-7 | Password change without the current password | `src/profile.php` | |
| AUTH-8 | Authorisation trusts the `role` cookie | `src/includes/functions.php` | |
| AUTH-9 | No login rate limiting or lockout | `src/login.php` | |
| CSRF-1 | Profile updates without a token | `src/profile.php` | |
| CSRF-2 | Cart actions without a token | `src/cart.php` | |
| CSRF-3 | Order cancellation without a token | `src/order.php` | |
| CSRF-4 | Admin actions without a token | `src/admin/*.php` | |
| SSRF-1 | Server side request forgery in the image fetcher | `src/seller/image_proxy.php` | `FLAG{ssrf_1nt3rn4l_r34ch}` |
| SSRF-2 | Remote product image URLs are rendered as-is | `src/includes/functions.php` | |
| LFI-1 | Local file read through `file://` | `src/seller/image_proxy.php` | `FLAG{l0c4l_f1l3_1nclus10n}` |
| XXE-1 | XML external entities in the bulk importer | `src/seller/bulk_import.php` | `FLAG{l0c4l_f1l3_1nclus10n}` |
| UPLOAD-1 | Unrestricted upload into an executable folder | `src/seller/bulk_import.php` | |
| DESER-1 | `unserialize()` of the `prefs` cookie (write-file gadget) | `src/api/prefs.php`, `src/includes/classes.php` | |
| LOGIC-1 | The cart and order prices come from the browser | `src/cart.php`, `src/api/cart.php`, `src/checkout.php` | |
| LOGIC-2 | Negative quantities are accepted | `src/cart.php` | |
| LOGIC-3 | Coupons stack without a limit | `src/cart.php`, `src/includes/functions.php` | |
| LOGIC-4 | Negative totals are paid out as store credit | `src/checkout.php` | `FLAG{l0g1c_fl4w_n3g4t1v3}` |
| LOGIC-5 | Stock is updated with a blind `UPDATE` (oversell / race) | `src/checkout.php` | |
| MISC-1 | Verbose errors and stack traces in the browser | `src/config.php` | |
| MISC-2 | The installer/reset page is public and unauthenticated | `src/init_db.php` | |
| MISC-3 | `phpinfo()` exposed at `/info.php` | `src/info.php` | |

Flags are static strings in this repository — they exist to prove an exploit worked, not to be secret.

---

## Project layout

```
.
├── data/                     SQLite database (git-ignored) + the flag backup file
├── docker/entrypoint.sh      Fixes ownership of the mounted data/ and uploads/ folders
├── docs/CODE_REVIEW.md       Review of the original code and the branch story
├── solution.md               Exploit walkthrough + fix for every vulnerability
├── tools/smoke_test.sh       End-to-end smoke suite (also asserts the lab is still vulnerable)
├── src/
│   ├── config.php            Bootstrap: session, error policy, paths, PDO
│   ├── includes/             Helpers, schema + seed data, classes, layout
│   ├── assets/               Stylesheet and SVG product artwork
│   ├── admin/                Dashboard, users, products, activity log
│   ├── seller/               Seller dashboard, product editor, XML import, image fetcher
│   ├── api/                  JSON cart and preferences endpoints
│   ├── internal/             Loopback-only metrics endpoint (SSRF target)
│   ├── uploads/              Seller uploads (git-ignored except .gitkeep)
│   └── *.php                 Store front, auth, cart, checkout, orders, profile
├── Makefile                  make up / down / logs / reset / serve / lint / test / clean
└── docs/ci-workflow.yml      GitHub Actions pipeline (copy it into .github/workflows/)
```

---

## Working with the lab

```bash
make help            # list every task
make up              # docker compose up --build -d + wait for the app
make reset           # drop and re-seed the database of the running stack
make serve           # local PHP server on 127.0.0.1:8080
make lint            # php -l on every file
make test            # smoke suite (starts its own server on :8099)
make clean           # remove the database, the cache and seller uploads
BASE_URL=http://localhost:8080 make test   # test an already running instance
```

A reset is also one click away at <http://localhost:8080/init_db.php> → *Reset the whole lab*.

### Continuous integration

`docs/ci-workflow.yml` runs `make lint` and `make test` on every push. Copy it into place to enable
it on GitHub:

```bash
mkdir -p .github/workflows && cp docs/ci-workflow.yml .github/workflows/ci.yml
```

### Running the lab

1. Start the app and open the store front.
2. Pick an exercise from the table above (or from [solution.md](solution.md)).
3. Read the `VULNERABILITY [ID]` comments in the source once you found a flaw — they explain the
   intended mistake.
4. Reset the database when you are done.

`solution.md › Fixing the code` shows the secure version of each snippet, which is the part worth
practising if you are studying for a defensive certification.

---

## Contributing

Keep the lab working: every new page should come with seeded data, a login-free way to test it where
possible, a `VULNERABILITY [ID]` comment, a row in the table above and a smoke test entry. Run
`make lint && make test` before opening a pull request.

---

*VulnMart is a training target. The authors are not responsible for misuse.*
