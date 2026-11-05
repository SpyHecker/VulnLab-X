# Code review and branch story

This document explains what was found in the repository when the work started, what was wrong with
it, and how the branch fixes and completes the project. It is the short version of `git log`; each
commit is listed in the [commit map](#commit-map) with the reason it exists.

---

## 1. Starting point

The repository contained a single commit (`Readme file updated.`) with twelve files:

```
Dockerfile              docker-compose.yml   package.json
Readme.md               solution.md
data/vulnmart.db        (a 20 KB SQLite database, committed)
src/config.php          src/index.php        src/init_db.php
src/login.php           src/product.php      src/style.css
```

Roughly 650 lines, four PHP pages, one stylesheet. The README described a full shop — roles,
catalogue, reviews, cart, checkout, orders, an admin dashboard, ten vulnerability families — and the
code implemented a search page, a product page, a login form and an installer.

## 2. Findings

### 2.1 The project did not start

| # | Finding | Evidence | Resolution |
| - | ------- | -------- | ---------- |
| 1 | `docker-compose.yml` declared both `image: php:8.2-apache` and a `build:` section, and the Dockerfile never copied the code — the container was only usable because of the bind mount | `docker-compose.yml`, `Dockerfile` | `2ab52b8` build: make the Docker stack boot and keep its database writable |
| 2 | The `chown` of `/var/www/data` in the image is hidden by `./data:/var/www/data`, so SQLite failed with *unable to open database file* on every write | `Dockerfile`, `docker-compose.yml` | Same commit: `docker/entrypoint.sh` re-applies ownership at start-up |
| 3 | The composition file used the obsolete top-level `version` key and had no healthcheck or restart policy | `docker-compose.yml` | Same commit |
| 4 | `config.php` hard-coded `/var/www/data/vulnmart.db`, so the documented local workflow (`cd src && php -S localhost:8080`) could never open a database | `src/config.php` | `ee8d66f` refactor: bootstrap, layout, database schema |
| 5 | `product.php` referenced `/images/<file>.jpg`, a folder that was never shipped — every product image was a 404 | `src/product.php` | Same commit: `src/assets/img/*.svg` and `product_image_url()` |
| 6 | `style.css` lived next to the pages and header/footer markup was copy-pasted into all four files | `src/*.php` | Same commit: `src/includes/{header,footer}.php` + `src/assets/css/style.css` |

### 2.2 The repository was dirty by design

| # | Finding | Resolution |
| - | ------- | ---------- |
| 7 | `data/vulnmart.db` was committed. Every run mutates it, so the working tree was permanently dirty and every clone shipped somebody's test data | `88f63de` chore: stop tracking generated files |
| 8 | No `.gitignore` at all (databases, uploads, editor folders, logs) | Same commit |
| 9 | No `.dockerignore`: the build context carried `.git`, the database and the documentation | Same commit |
| 10 | `package.json` described a Node project (`main: index.js`, a `test` script that always fails) that never existed | Same commit — replaced later by the `Makefile` in `93b079d` |

### 2.3 Security bugs that were intended

The SQL injection, reflected XSS and plain-text password in `login.php`, `index.php` and
`init_db.php` were deliberate (they are described in `solution.md`), so they were kept — but they
were re-implemented on top of the new layout, tagged with ids and documented consistently.

### 2.4 Security bugs that were **not** intended, and how they were handled

| # | Finding | Why it mattered | Resolution |
| - | ------- | --------------- | ---------- |
| 11 | `init_db.php` only ran `CREATE TABLE IF NOT EXISTS` and could not reset the lab | Students had to delete the file by hand between exercises | `ee8d66f`: `install_database($db, $reset)` with a "Reset the whole lab" action |
| 12 | The database was seeded at the HTTP layer with no transaction and no idempotency beyond ad-hoc `COUNT(*)` checks | Re-running the installer could duplicate data | Same commit: `schema.php` owns schema + seed and is safe to re-run |
| 13 | `login.php` had no way to log out („sessions never end") and `config.php` gave no clue when the database file was missing | Broken UX, opaque failures | `ad85870` (logout) and `ee8d66f` (installer page) |
| 14 | Error handling was inconsistent: some pages echoed the PDO message, others swallowed it | Debugging was guesswork | Kept verbose on purpose (MISC-1) but centralised in `config.php` and tagged |

### 2.5 Documentation drift

| # | Finding | Resolution |
| - | ------- | ---------- |
| 15 | The README promised cart, checkout, orders, reviews, seller and admin features that did not exist | Implemented in `7258224` … `21648a8`; status is now a table in the README |
| 16 | `solution.md` described the same four flaws in prose with no reproducible steps, and no ids connecting it to the code | Rewritten with ids, `curl` exploits, impact and patched code |
| 17 | Nothing verified that the lab still works, or that a refactor had not accidentally fixed an exercise | `93b079d`: smoke suite + a ready-to-install CI pipeline |

---

## 3. What the branch adds

```
auth        registration, logout, remember-me, profiles, weak-role cookies
catalogue   categories, product pages with reviews and ratings
cart        session cart, quantity edits, coupons
checkout    orders, order_items, stock updates, confirmation with flag
orders      history, detail, cancellation
admin       dashboard (cached), user & product management, activity log
seller      dashboard, product editor, XML bulk import, image fetcher
api         JSON cart, preferences (unserialize sink), internal endpoint, phpinfo
tooling     Makefile, smoke suite, GitHub Actions pipeline template
docs        README, solution guide, this review
```

The deliberately vulnerable behaviour is preserved and, where it existed before, extended — the lab
now covers all ten vulnerability families the README promised: SQLi, XSS, IDOR / broken access
control, broken authentication, CSRF, SSRF, XXE, insecure deserialization, business logic flaws and
misconfiguration.

### Design decisions

* **One bootstrap.** `src/config.php` is the only entry point: session policy, error policy, paths,
  PDO. Pages start with `require config.php`, then a guard (`require_login()`, `require_admin()`,
  `require_seller()`), then the shared layout. Redirects therefore always happen before output.
* **Schema and seed in one place.** `includes/schema.php` is used by the automatic first-run install,
  by `/init_db.php` and by `make reset`.
* **Vulnerability ids.** Every intentional flaw carries a `VULNERABILITY [ID]` comment; the id is used
  in the README table, in `solution.md` and in the smoke test. Adding a flaw means adding four
  things, which keeps the lab honest.
* **Tests that protect the bugs.** `tools/smoke_test.sh` asserts the shop works *and* that SQLi, XSS,
  IDOR, the forged cookies, the client-side price and the exposure endpoints still behave as
  documented. A "fix" that silently hardens an exercise fails CI.
* **No build step, no dependencies.** PHP 8.2 + `pdo_sqlite` + vanilla CSS. The whole lab starts with
  `docker compose up` or `php -S`.

---

## Commit map

| Commit | Subject | Why |
| ------ | ------- | --- |
| `88f63de` | chore: stop tracking generated files and drop the stray Node manifest | The repository was permanently dirty; `package.json` was a leftover from a different project |
| `2ab52b8` | build: make the Docker stack boot and keep its database writable | Broken image, broken permissions on the mounted data folder, obsolete compose keys |
| `ee8d66f` | refactor: introduce a bootstrap, shared layout and a real database schema | Removes the four copies of the layout, fixes the database path for local runs and the missing images, adds the installer/reset page and the full schema |
| `ad85870` | feat(auth): registration, profiles, logout and "remember me" | Accounts were a promised feature and the entry point for the authentication exercises |
| `7258224` | feat(catalog): category filter, product pages and customer reviews | Completes the catalogue and adds stored XSS |
| `2cd565c` | feat(cart): session cart, discount coupons and a mock checkout | Makes the shop shoppable; implements the business logic flaws |
| `83b51c6` | feat(orders): order history, order detail and cancellation | Orders were written but unreachable; adds the IDOR exercises |
| `1b7b1b9` | feat(admin): dashboard, user management, product management and activity log | The promised dashboard, plus the access control and log-based XSS exercises |
| `0201d43` | feat(seller): seller dashboard, product editor, XML import and image fetcher | The seller role, XXE, unrestricted upload, SSRF and LFI |
| `21648a8` | feat(api): JSON cart endpoints, preference cookies and a phpinfo page | Insecure deserialization and exposed diagnostics |
| `93b079d` | test: add a smoke suite, a Makefile and a CI pipeline template | Nothing verified the lab; `make` gives a single entry point for the daily tasks. `docs/ci-workflow.yml` is copied into `.github/workflows/` to enable it (the branch was prepared by an app token that may not create workflow files) |
| *(this commit)* | docs: rewrite the README, the solution guide and add this review | Documentation had drifted from the code by an entire application |

Each commit keeps the application runnable: no commit removes a feature added earlier, and the smoke
suite passes at the tip of the branch.

---

## How to review this branch

1. `make up` — confirm the stack boots from a clean checkout.
2. `make test` — 22 checks: the shop works and the intended weaknesses are present.
3. Read `Readme.md` for the map of the lab, `solution.md` for the exploits and the fixes.
4. `git log --oneline --reverse` — the history is ordered so that infrastructure comes first, then
   features in the order a shop would grow, then tooling and documentation.

## Ideas for a follow-up

* More lab content: password reset flow (token in the URL, predictable), file download endpoint with
  path traversal, a GraphQL or REST API with mass assignment, an SSRF-filter bypass exercise.
* A container healthpage that shows the seeded accounts.
* Playwright tests for the JavaScript-free flows, so UI regressions are caught too.
* Install the CI pipeline under `.github/workflows/` so the checks run on every pull request.
