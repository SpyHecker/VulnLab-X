<?php
/**
 * Internal-only endpoint.
 *
 * The reverse proxy in front of VulnMart is supposed to keep /internal/ away
 * from the internet; the script double checks that the request really comes
 * from the loopback interface.
 */

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden: this endpoint is only reachable from the loopback interface.\n";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "VulnMart internal metrics\n";
echo "=========================\n\n";
echo "FLAG{ssrf_1nt3rn4l_r34ch}\n\n";
echo "Orders processed : 1284\n";
echo "Error rate       : 0.02%\n";
echo "Database         : /var/www/data/vulnmart.db\n";
