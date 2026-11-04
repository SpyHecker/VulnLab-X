<?php
/**
 * Shared page header.
 *
 * Pages may set $pageTitle before including this file.
 */

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Shop';
$flashMessage = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> &middot; <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="app-header">
    <div class="app-header__inner">
        <a class="brand" href="/"><span class="brand__mark">🛒</span> <?= APP_NAME ?></a>

        <nav class="nav">
            <a href="/">Shop</a>
            <a href="/cart.php">Cart<?php if (cart_count() > 0): ?><span class="nav__badge"><?= cart_count() ?></span><?php endif; ?></a>
            <?php if ($currentUser): ?>
                <a href="/orders.php">Orders</a>
                <a href="/profile.php"><?= h($currentUser['username']) ?></a>
                <?php if (is_seller()): ?>
                    <a href="/seller/">Seller</a>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <a href="/admin/">Admin</a>
                <?php endif; ?>
                <a class="nav__cta" href="/logout.php">Log out</a>
            <?php else: ?>
                <a href="/login.php">Log in</a>
                <a class="nav__cta" href="/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-content">
<?php if ($flashMessage): ?>
    <?php // VULNERABILITY [XSS-5]: flash messages are stored and printed without escaping. ?>
    <div class="alert alert--<?= h($flashMessage['type']) ?>"><?= $flashMessage['message'] ?></div>
<?php endif; ?>
