<?php
// Admin Fuel Price Manager: update Caltex, Shell, and Petron fuel prices.

require_once 'includes/config.php';
require_once 'includes/market.php';

// Make sure only an admin can access this page.
$u = need('admin');

// Generate a CSRF token if one does not exist.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Handle submitted fuel prices and forecasts.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate the CSRF token.
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid request.');
    }

    // Save average fuel prices.
    foreach (fuel_products() as $k => $_) {
        $v = trim($_POST[$k] ?? '');

        if ($v === '') {
            q('DELETE FROM fuel_prices WHERE product = ?', [$k]);
        } elseif (
            is_numeric($v) &&
            $v > 0 &&
            $v < (substr($k, -3) === 'lpg' ? 10000 : 1000)
        ) {
            q(
                'INSERT INTO fuel_prices(product, price, updated)
                 VALUES(?,?,NOW())
                 ON DUPLICATE KEY UPDATE price=VALUES(price), updated=NOW()',
                [$k, round((float) $v, 2)]
            );
        }
    }

    // Fill only empty average rows with the latest published national averages.
    // Values are based on November 3, 2026.
    if (($_POST['seed'] ?? '') === '1') {

        foreach ([
            'ron95' => 90.95,
            'ron91' => 89.31,
            'diesel' => 94.44,
            'kerosene' => 120.74,
            'lpg' => 1398.03
        ] as $k => $v) {
            q(
                'INSERT IGNORE INTO fuel_prices(product, price, updated)
                 VALUES(?,?,?)',
                [$k, $v, '2026-11-03 06:00:00']
            );
        }

        header('Location: fuel_admin.php?saved=1');
        exit;
    }

    // Save fuel prices for each brand.
    foreach (fuel_brands() as $b => $_) {
        foreach (fuel_brand_products() as $p => $__ ) {
            $k = $b . ':' . $p;
            $v = trim($_POST['p'][$k] ?? '');

            if ($v === '') {
                q('DELETE FROM fuel_prices WHERE product = ?', [$k]);
            } elseif (
                is_numeric($v) &&
                $v > 0 &&
                $v < ($p === 'lpg' ? 10000 : 1000)
            ) {
                q(
                    'INSERT INTO fuel_prices(product, price, updated)
                     VALUES(?,?,NOW())
                     ON DUPLICATE KEY UPDATE price=VALUES(price), updated=NOW()',
                    [$k, round((float) $v, 2)]
                );
            }
        }
    }

    // Save next week's fuel price forecasts.
    foreach (fuel_forecast_products() as $k => $_) {
        $v = trim($_POST[$k] ?? '');

        if ($v === '') {
            q('DELETE FROM fuel_prices WHERE product = ?', [$k]);
        } elseif (is_numeric($v) && abs($v) < 50) {
            q(
                'INSERT INTO fuel_prices(product, price, updated)
                 VALUES(?,?,NOW())
                 ON DUPLICATE KEY UPDATE price=VALUES(price), updated=NOW()',
                [$k, round((float) $v, 2)]
            );
        }
    }

    // Redirect after successfully saving the changes.
    header('Location: fuel_admin.php?saved=1');
    exit;
}

// Get the current fuel prices.
$cur = fuel_prices();

$title = 'Fuel Updates';

require 'includes/header.php';
?>

<!-- Fuel price management form -->
<form method="post" class="card glass">

    <h1>Fuel Updates</h1>

    <?php if (isset($_GET['saved'])) echo '<p>Saved.</p>'; ?>

    <!-- Brand-specific fuel prices -->
    <h2>Prices by brand in Pampanga</h2>

    <p class="muted">
        ₱ per liter at stations in Pampanga (LPG: ₱ per 11 kg tank).
        Leave a box blank if you don't have that price.
    </p>

    <div style="overflow-x:auto">
        <table style="width:100%;min-width:560px;border-collapse:collapse">
            <tr>
                <th style="text-align:left">Brand</th>

                <?php foreach (fuel_brand_products() as $pl): ?>
                    <th style="text-align:left">
                        <?= e($pl) ?>
                    </th>
                <?php endforeach; ?>
            </tr>

            <?php foreach (fuel_brands() as $b => $bl): ?>
                <tr>
                    <td>
                        <b><?= e($bl) ?></b>
                    </td>

                    <?php foreach (fuel_brand_products() as $p => $pl): ?>
                        <?php $k = $b . ':' . $p; ?>

                        <td>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="p[<?= $k ?>]"
                                value="<?= e($cur[$k]['price'] ?? '') ?>"
                                style="margin:.2rem 0;width:88px"
                            >
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Average / reference fuel prices -->
    <h2>Average / reference price (optional)</h2>

    <p class="muted">
        Enter the current pump prices in ₱ per liter (use the DOE weekly price monitoring or a local station).
        Shown on the home page as "Average (reference)".
        Leave a field blank to hide that fuel.
    </p>

    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

    <div class="grid3">
        <?php foreach (fuel_products() as $k => $lb): ?>
            <label>
                <?= e($lb) ?>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="<?= $k ?>"
                    value="<?= e($cur[$k]['price'] ?? '') ?>"
                >
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Next week's fuel price forecast -->
    <h2>Next week's forecast</h2>

    <p class="muted">
        Expected change in ₱ per liter, usually announced by the DOE and oil companies every Monday for Tuesday.
        Use a minus sign for a rollback (example: -1.50).
        Leave blank to hide.
    </p>

    <div class="grid3">
        <?php foreach (fuel_forecast_products() as $k => $lb): ?>
            <label>
                <?= e($lb) ?>

                <input
                    type="number"
                    step="0.01"
                    min="-50"
                    max="50"
                    name="<?= $k ?>"
                    value="<?= e($cur[$k]['price'] ?? '') ?>"
                >
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Form actions -->
    <p>
        <button class="btn">Save</button>
        <a class="btn blue" href="index.php">Back to home</a>
    </p>

</form>

<!-- Quick fill national average prices -->
<form method="post" class="card glass">

    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="seed" value="1">

    <h2>Quick fill</h2>

    <p class="muted">
        Fills any empty average boxes with the national averages published for Nov 3, 2026
        (Diesel ₱94.44, Gasoline 91 ₱89.31, Premium 95 ₱90.95,
        Kerosene ₱120.74, LPG 11kg ₱1,398.03).
        Boxes that already have a value are left alone.
    </p>

    <button class="btn blue">Fill empty averages</button>

</form>

<?php require 'includes/footer.php'; ?>
