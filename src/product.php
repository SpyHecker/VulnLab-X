<?php
/**
 * Product detail page with the customer review list.
 */
require_once __DIR__ . '/config.php';

$id = $_GET['id'] ?? '';
$product = null;
$reviews = [];
$error = '';
$reviewError = '';

$author = current_user()['username'] ?? '';

if ($id === '') {
    $error = 'No product id was supplied.';
} else {
    try {
        // VULNERABILITY [SQLI-2]: the id coming from the query string is
        // concatenated into the query without casting or binding it.
        $product = $db->query('SELECT * FROM products WHERE id = ' . $id)->fetch();

        if (!$product) {
            $error = 'Product not found.';
        }
    } catch (PDOException $e) {
        // VULNERABILITY [MISC-1]: verbose database errors.
        $error = 'Query failed: ' . $e->getMessage();
    }
}

if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim((string) ($_POST['author'] ?? $author));
    $rating = (int) ($_POST['rating'] ?? 5);
    $comment = (string) ($_POST['comment'] ?? '');
    $userId = current_user()['id'] ?? 'NULL';

    if ($author === '' || $comment === '') {
        $reviewError = 'A name and a comment are required.';
    } else {
        try {
            // VULNERABILITY [SQLI-6]: the review fields are concatenated into
            // the insert statement.
            $db->exec(
                "INSERT INTO reviews (product_id, user_id, author, rating, comment)
                 VALUES (" . (int) $product['id'] . ", $userId, '$author', $rating, '$comment')"
            );

            log_activity('review_created', 'Reviewed ' . $product['name']);
            flash('Thanks for the review!', 'success');
            redirect('/product.php?id=' . (int) $product['id'] . '#reviews');
        } catch (PDOException $e) {
            $reviewError = 'Could not save the review: ' . $e->getMessage();
        }
    }
}

if ($product) {
    try {
        $reviews = $db->query(
            'SELECT * FROM reviews WHERE product_id = ' . (int) $product['id'] . ' ORDER BY id DESC'
        )->fetchAll();
    } catch (PDOException $e) {
        $reviewError = 'Could not load the reviews: ' . $e->getMessage();
    }
}

$averageRating = 0.0;

if ($reviews) {
    $averageRating = array_sum(array_column($reviews, 'rating')) / count($reviews);
}

$pageTitle = $product['name'] ?? 'Product';
require __DIR__ . '/includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
    <p><a class="button secondary" href="/">Back to the shop</a></p>
<?php else: ?>
    <p class="muted"><a href="/">← Back to the shop</a></p>

    <article class="product-detail">
        <div>
            <?php
            // VULNERABILITY [SSRF-2]: remote image URLs are rendered as-is. If
            // the application ever fetched the image server side, the same
            // field would be a server side request forgery primitive.
            ?>
            <img class="product-detail__image"
                 src="<?= h(product_image_url($product['image'])) ?>"
                 alt="<?= h($product['name']) ?>">
        </div>

        <div class="stack">
            <div>
                <h1><?= h($product['name']) ?></h1>
                <p class="price"><?= money($product['price']) ?></p>
            </div>

            <p><?= h($product['description']) ?></p>

            <div class="product-detail__meta">
                <span class="badge"><?= h($product['category']) ?></span>
                <span><?= (int) $product['stock'] ?> in stock</span>
                <span><?= count($reviews) ?> review(s)</span>
                <?php if ($reviews): ?>
                    <span><span class="review__stars"><?= str_repeat('★', (int) round($averageRating)) ?></span> <?= number_format($averageRating, 1) ?>/5</span>
                <?php endif; ?>
                <span>Product #<?= (int) $product['id'] ?></span>
            </div>
        </div>
    </article>

    <section class="card" id="reviews" style="margin-top: 2rem;">
        <div class="page-head">
            <h2>Customer reviews</h2>
            <span class="muted"><?= count($reviews) ?> total</span>
        </div>

        <?php if ($reviewError !== ''): ?>
            <div class="alert alert--error"><?= h($reviewError) ?></div>
        <?php endif; ?>

        <?php if (!$reviews): ?>
            <p class="muted">No reviews yet. Be the first to write one.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <article class="review">
                    <div class="review__head">
                        <?php // VULNERABILITY [XSS-2]: the author name and the comment are stored and rendered without escaping. ?>
                        <span class="review__author"><?= $review['author'] ?></span>
                        <span class="review__stars"><?= str_repeat('★', max(0, min(5, (int) $review['rating']))) ?></span>
                        <span class="review__date"><?= h($review['created_at']) ?></span>
                    </div>
                    <div class="review__body"><?= $review['comment'] ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top: 2rem;">Write a review</h3>
        <form method="post" action="/product.php?id=<?= (int) $product['id'] ?>#reviews">
            <div class="row" style="align-items: flex-start;">
                <div class="form-group" style="flex: 1 1 220px;">
                    <label for="author">Your name</label>
                    <input id="author" type="text" name="author" value="<?= h($author) ?>" required>
                </div>
                <div class="form-group" style="flex: 1 1 140px;">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating">
                        <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <option value="<?= $stars ?>"><?= $stars ?> star<?= $stars > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="comment">Comment</label>
                <textarea id="comment" name="comment" required></textarea>
            </div>

            <button type="submit" class="auto-width">Post review</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
