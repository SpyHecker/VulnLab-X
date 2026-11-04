<?php
/**
 * Visitor preferences endpoint.
 *
 * Used by the theme switcher in the layout: a form posts the new theme here and
 * the browser is sent back to the page it came from.
 */
require_once dirname(__DIR__) . '/config.php';

// VULNERABILITY [DESER-1]: reading the preferences unserialises cookie data.
// See includes/classes.php for the sink and solution.md for a working payload.
$prefs = UserPreferences::load();

$theme = (string) ($_POST['theme'] ?? $_GET['theme'] ?? '');

if ($theme !== '') {
    // No allow-list: whatever string is sent is stored and later printed inside
    // the body class attribute.
    $prefs->theme = $theme;
    $prefs->save();
}

if (isset($_POST['redirect']) || isset($_GET['redirect'])) {
    redirect((string) ($_POST['redirect'] ?? $_GET['redirect']));
}

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'theme' => $prefs->theme,
    'language' => $prefs->language,
    'recent' => $prefs->recent,
], JSON_PRETTY_PRINT);
