<?php
/**
 * Fetch a product image from a remote URL.
 */
require_once dirname(__DIR__) . '/config.php';

require_seller();

$url = trim((string) ($_POST['url'] ?? $_GET['url'] ?? ''));
$content = null;
$error = '';
$looksLikeImage = false;

if ($url !== '') {
    /*
     * VULNERABILITY [SSRF-1]: the server fetches whatever URL the seller sends.
     * That includes internal services (http://127.0.0.1/, http://localhost/admin/,
     * cloud metadata endpoints) which the browser could never reach.
     *
     * VULNERABILITY [LFI-1]: the same call accepts the file:// wrapper and plain
     * paths, so data/vulnmart.db, /etc/passwd or the flag backup file can be
     * read straight off the disk.
     */
    $context = stream_context_create([
        'http' => ['timeout' => 5, 'ignore_errors' => true],
    ]);

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        $error = 'Could not fetch ' . $url . '.';
        $content = null;
    } else {
        $looksLikeImage = (bool) preg_match('#\.(png|jpe?g|gif|webp|svg)(\?|$)#i', $url);
        $content = substr($content, 0, 20000);

        log_activity('image_fetch', 'Fetched ' . $url);
    }
}

$pageTitle = 'Image fetcher';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Image fetcher</h1>
    <p class="muted">Import a product picture from another site</p>
</div>

<div class="card">
    <form method="post" action="/seller/image_proxy.php">
        <div class="form-group">
            <label for="url">Image URL</label>
            <input id="url" type="text" name="url" value="<?= h($url) ?>" placeholder="https://example.com/images/lamp.png">
            <p class="form-hint">
                For example, try <span class="monospace">http://127.0.0.1/internal/secret.php</span> or
                <span class="monospace">file:///etc/hostname</span>.
            </p>
        </div>

        <button type="submit">Fetch image</button>
    </form>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($content !== null): ?>
    <div class="card">
        <h2>Result</h2>

        <?php if ($looksLikeImage): ?>
            <p><img src="<?= h($url) ?>" alt="Fetched image" style="max-width: 100%; border-radius: var(--radius);"></p>
        <?php endif; ?>

        <pre class="output"><?= h($content) ?></pre>
    </div>
<?php endif; ?>

<p><a class="button secondary" href="/seller/">Back to the seller dashboard</a></p>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
