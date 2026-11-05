<?php
/**
 * Diagnostic page.
 *
 * VULNERABILITY [MISC-3]: phpinfo() is left in the document root. It discloses
 * the PHP build, loaded extensions, environment variables (including DB_PATH),
 * the document root and every server path an attacker needs for follow-up
 * attacks.
 */
phpinfo();
