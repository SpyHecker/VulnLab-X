<?php
/**
 * Bulk product import from XML.
 */
require_once dirname(__DIR__) . '/config.php';

require_seller();

$me = current_user();
$result = null;
$error = '';
$rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xml = (string) ($_POST['xml'] ?? '');

    if (!empty($_FILES['file']['name'])) {
        $upload = $_FILES['file'];
        // VULNERABILITY [UPLOAD-1]: the file is stored under the name the client
        // sent, without checking the extension, the MIME type or the content,
        // in a directory that Apache executes. Uploading shell.php is enough.
        $target = UPLOAD_DIR . '/' . basename($upload['name']);

        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0777, true);
        }

        if (@copy($upload['tmp_name'], $target)) {
            $result = 'Uploaded ' . basename($upload['name']) . ' to /uploads/' . basename($upload['name']);
        } else {
            $error = 'The upload could not be stored in ' . UPLOAD_DIR . '.';
        }

        if ($xml === '') {
            $xml = (string) @file_get_contents($upload['tmp_name']);
        }
    }

    if ($xml === '') {
        $error = 'Provide an XML document by pasting it or uploading a file.';
    } elseif ($error === '') {
        // VULNERABILITY [XXE-1]: the parser is told to substitute entities and
        // the external entity loader is explicitly enabled, so a document type
        // definition can read local files (or reach internal services).
        libxml_set_external_entity_loader(static function ($public, $system) {
            return @fopen($system, 'r');
        });

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD);

        if ($document === false) {
            $messages = [];
            foreach (libxml_get_errors() as $libxmlError) {
                $messages[] = trim($libxmlError->message);
            }
            $error = 'Invalid XML: ' . implode(' ', $messages);
        } else {
            $insert = $db->prepare(
                'INSERT INTO products (name, description, price, image, category, stock, seller_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $imported = 0;

            foreach ($document->product as $product) {
                $name = trim((string) $product->name);

                if ($name === '') {
                    continue;
                }

                $insert->execute([
                    $name,
                    (string) $product->description,
                    (float) $product->price,
                    (string) $product->image,
                    (string) ($product->category ?: 'misc'),
                    (int) $product->stock,
                    (int) ($me['id'] ?? 0),
                ]);

                // The imported name is shown back to the seller, which is how the
                // XXE payload displays the file it read.
                $rows[] = $name;
                $imported++;
            }

            log_activity('bulk_import', 'Imported ' . $imported . ' product(s) from XML');
            $result = $imported . ' product(s) imported.';
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

$pageTitle = 'Bulk import';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Bulk import</h1>
    <p class="muted">Upload a product list as XML</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($result !== null): ?>
    <div class="alert alert--success"><?= h($result) ?></div>
<?php endif; ?>

<?php if ($rows): ?>
    <div class="card">
        <h2>Imported names</h2>
        <ul>
            <?php foreach ($rows as $row): ?>
                <?php // VULNERABILITY [XSS-2]: imported values are echoed without escaping. ?>
                <li><?= $row ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row" style="align-items: flex-start; gap: 1.5rem;">
    <div class="card" style="flex: 1 1 420px;">
        <h2>Paste XML</h2>

        <form method="post" action="/seller/bulk_import.php">
            <div class="form-group">
                <label for="xml">XML document</label>
                <textarea id="xml" name="xml" class="monospace" style="min-height: 220px;"><?= h($_POST['xml'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="file">…or upload a file</label>
                <input id="file" type="file" name="file">
            </div>

            <button type="submit">Import products</button>
        </form>
    </div>

    <div class="card" style="flex: 1 1 320px;">
        <h2>Expected format</h2>
<pre class="output">&lt;products&gt;
  &lt;product&gt;
    &lt;name&gt;Desk lamp&lt;/name&gt;
    &lt;description&gt;Warm LED desk lamp&lt;/description&gt;
    &lt;price&gt;39.90&lt;/price&gt;
    &lt;image&gt;lamp.svg&lt;/image&gt;
    &lt;category&gt;home&lt;/category&gt;
    &lt;stock&gt;15&lt;/stock&gt;
  &lt;/product&gt;
&lt;/products&gt;</pre>
        <p class="form-hint">
            Imported products are owned by your seller account. Uploaded files are kept in
            <span class="monospace">/uploads/</span>.
        </p>
    </div>
</div>

<p><a class="button secondary" href="/seller/">Back to the seller dashboard</a></p>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
