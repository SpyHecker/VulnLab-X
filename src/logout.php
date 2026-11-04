<?php
/**
 * Sign out.
 */
require_once __DIR__ . '/config.php';

$username = current_user()['username'] ?? 'guest';

log_activity('logout', 'Signed out ' . $username);

// VULNERABILITY [AUTH-6]: the "remember me" cookie is never cleared, so the
// next request silently signs the visitor back in again. The missing call is:
//   setcookie('remember_me', '', time() - 3600, '/');
$_SESSION = [];
session_destroy();
session_id('');
session_start();

flash('You have been signed out.', 'info');
redirect('/');
