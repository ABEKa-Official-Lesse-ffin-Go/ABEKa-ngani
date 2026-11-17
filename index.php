<?php
// Public Home Page: Pampanga news, announcements, LGUs, fuel prices, and tourist spots.

$title = 'Home';

require 'includes/header.php';
require_once 'includes/news.php';
require_once 'includes/market.php';

// Get the three most viewed LGUs.
$top = q('SELECT * FROM lgus ORDER BY views DESC, name ASC LIMIT 3')->fetchAll();

// Refresh news for Pampanga and the top three LGUs.
refresh_news('pampanga', null, 'Pampanga');

foreach ($top as $t) {
    refresh_news($t['slug'], $t['id'], '"' . $t['name'] . '" Pampanga');
}

// Get all LGUs, market data, announcements, and news.
$all = q('SELECT * FROM lgus ORDER BY name')->fetchAll();

$fx = forex_rates();
$fuel = fuel_prices();
$fuelNews = fuel_headlines();

$ann = q('SELECT id,title,url FROM announcements ORDER BY posted DESC LIMIT 8')->fetchAll();
$news = q('SELECT title,url FROM news ORDER BY published DESC LIMIT 8')->fetchAll();

// Generate an LGU tile.
function tile($l) {
    return '<a class="thumb" href="place.php?s=' . e($l['slug']) . '">
        <div class="img" style="background-image:url(assets/lgu/' . e($l['slug']) . '.jpg),linear-gradient(#bfe4fa,#8fb43a)"></div>
        <b>' . e($l['name']) . '</b>
    </a>';
}
?>

<!-- Most viewed places -->
<section class="card glass">
    <h2>Most viewed places</h2>

    <div class="grid3">
        <?php foreach ($top as $l) echo tile($l); ?>
    </div>

    <p style="text-align:center">
        <b>More cities</b><br>
        <a href="#" id="seeAll" class="btn sm blue">See all</a>
    </p>

    <div id="allLgus" class="grid-all hidden">
        <?php foreach ($all as $l) echo tile($l); ?>
    </div>
</section>

<!-- Fuel updates and forex exchange rates -->
<div class="grid3" style="grid-template-columns:1fr 1fr">

    <!-- Fuel updates -->
    <section class="card glass fold" data-fold="fuel">
        <div class="foldhead">
            <h2>Fuel Updates</h2>
            <button type="button" class="btn sm foldbtn" aria-expanded="true" title="Minimize">−</button>
        </div>

        <div class="foldbody">
            <?php
            // Separate forecast products, regular pump prices, and brand prices.
            $fc = array_intersect_key($fuel, fuel_forecast_products());

            $pr = array_filter(
                array_diff_key($fuel, fuel_forecast_products()),
                function ($k) {
                    return strpos($k, ':') === false;
                },
                ARRAY_FILTER_USE_KEY
            );

            $br = array_filter(
                $fuel,
                function ($k) {
                    return strpos($k, ':') !== false;
                },
                ARRAY_FILTER_USE_KEY
            );
            ?>

            <!-- Next week's fuel forecast -->
            <?php if ($fc): ?>
                <h3>
                    Next week's forecast
                    <span class="muted">(₱ per liter)</span>
                </h3>

                <div class="list">
                    <?php foreach (fuel_forecast_products() as $k => $lb): ?>
                        <?php if (isset($fc[$k])): ?>
                            <?php $v = (float) $fc[$k]['price']; ?>

                            <a style="display:flex;justify-content:space-between;pointer-events:none">
                                <span><?= e($lb) ?></span>

                                <b style="color:<?= $v > 0 ? '#b93a26' : ($v < 0 ? '#2b7a3d' : 'inherit') ?>">
                                    <?= $v > 0 ? '▲ +' : ($v < 0 ? '▼ −' : '') ?>₱<?= number_format(abs($v), 2) ?>
                                </b>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <p class="muted">
                    Posted <?= e(date('M j, Y', strtotime(max(array_column($fc, 'updated'))))) ?>.
                    Actual changes vary by company.
                </p>
            <?php endif; ?>

            <!-- Average pump prices -->
            <?php if ($pr): ?>
                <h3>Pump prices in Pampanga</h3>

                <div class="list">
                    <?php foreach (fuel_products() as $k => $lb): ?>
                        <?php if (isset($pr[$k])): ?>
                            <a style="display:flex;justify-content:space-between;pointer-events:none">
                                <span>Average <?= e($lb) ?></span>

                                <b>
                                    ₱<?= number_format($pr[$k]['price'], 2) ?><?= $k === 'lpg' ? '/tank' : '/L' ?>
                                </b>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <p class="muted">
                    Reference averages as of <?= e(date('M j, Y', strtotime(max(array_column($pr, 'updated'))))) ?>.
                    Actual prices vary by station and brand.
                </p>
            <?php endif; ?>

            <!-- Fuel-related news -->
            <?php if ($fuelNews): ?>
                <div class="list">
                    <?php foreach ($fuelNews as $n): ?>
                        <a href="<?= e($n[1]) ?>" target="_blank" rel="noopener">
                            <?= e($n[0]) ?>
                            <span class="muted"><?= e(date('M j', strtotime($n[2]))) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Admin fuel management link -->
            <?php if ((user()['role'] ?? '') === 'admin'): ?>
                <p>
                    <a class="btn sm" href="fuel_admin.php">Update prices &amp; forecast</a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Forex exchange rates -->
    <section class="card glass fold" data-fold="forex">
        <div class="foldhead">
            <h2>Forex exchange rates</h2>
            <button type="button" class="btn sm foldbtn" aria-expanded="true" title="Minimize">−</button>
        </div>

        <div class="foldbody">
            <?php if ($fx && $fx['rates']): ?>
                <div class="list">
                    <?php foreach ($fx['rates'] as $k => $v): ?>
                        <a style="display:flex;justify-content:space-between;pointer-events:none">
                            <span>
                                1 <?= e($k) ?>
                                <span class="muted"><?= e($fx['codes'][$k] ?? '') ?></span>
                            </span>

                            <b>₱<?= number_format($v, $v < 1 ? 4 : 2) ?></b>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p class="muted">
                    Updated <?= e(date('M j, g:i A', strtotime($fx['updated']))) ?> ·
                    Rates by
                    <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">ExchangeRate-API</a>
                </p>

            <?php else: ?>
                <p class="muted">
                    Exchange rates are unavailable right now. Please check back soon.
                </p>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php
// Get North Luzon fuel data and prepare brand information.
$northFuel = north_luzon_fuel_data();

$brandRows = $northFuel['brands'] ?? [];
$brandNames = fuel_brands();

$brandProducts = [
    'ron91' => 'Gasoline 91',
    'ron95' => 'Premium 95',
    'diesel' => 'Diesel',
    'kerosene' => 'Kerosene'
];

$brandColors = [
    'caltex' => ['#e4002b', '#fff'],
    'shell' => ['#fbce07', '#c8102e'],
    'petron' => ['#0b4ea2', '#fff']
];

// Default average fuel prices.
$averageFuel = [
    'ron91' => 88.79,
    'ron95' => 90.30,
    'diesel' => 93.53,
    'kerosene' => 116.14,
    'lpg' => 1238.96
];

$averageLabels = [
    'ron91' => 'Gasoline 91',
    'ron95' => 'Gasoline 95',
    'diesel' => 'Diesel',
    'kerosene' => 'Kerosene',
    'lpg' => 'LPG 11kg'
];
?>

<!-- Pump prices by brand -->
<section class="card glass fold" data-fold="brands">
    <div class="foldhead">
        <h2>Pump prices by brand in Pampanga</h2>
        <button type="button" class="btn sm foldbtn" aria-expanded="true" title="Minimize">−</button>
    </div>

    <div class="foldbody">
        <p class="muted">
            North Luzon reference prices · Only Caltex, Shell and Petron · Prices may vary by station.
        </p>

        <!-- Average fuel prices -->
        <div class="grid3" style="grid-template-columns:repeat(auto-fit,minmax(105px,1fr));gap:.45rem;margin:.65rem 0">
            <?php foreach ($averageLabels as $k => $label): ?>
                <?php $v = $northFuel['averages'][$k] ?? $averageFuel[$k]; ?>

                <div style="background:rgba(255,255,255,.35);border:1px solid var(--edge);border-radius:10px;padding:.55rem;text-align:center">
                    <small class="muted"><?= e($label) ?></small>

                    <div style="font-weight:800">
                        ₱<?= number_format($v, 2) ?>
                    </div>

                    <small class="muted">
                        <?= $k === 'lpg' ? 'per 11 kg tank' : 'per liter' ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Brand-specific fuel prices -->
        <?php if ($brandRows): ?>
            <p class="muted" style="text-align:right;margin:.2rem 0">
                Brand prices per liter · Effective
                <?= e($northFuel['effective_date'] ? date('M j, Y', strtotime($northFuel['effective_date'])) : 'date unavailable') ?>
            </p>

            <div style="overflow-x:auto">
                <table style="width:100%;min-width:450px;border-collapse:collapse">
                    <tr style="font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;opacity:.75">
                        <th style="text-align:left;padding:.5rem .4rem">Brand</th>

                        <?php foreach ($brandProducts as $label): ?>
                            <th style="text-align:right;padding:.5rem .4rem">
                                <?= e($label) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach (['caltex', 'shell', 'petron'] as $b): ?>
                        <?php
                        if (!isset($brandRows[$b])) continue;

                        $c = $brandColors[$b];
                        ?>

                        <tr style="border-top:1px solid var(--edge)">
                            <td style="padding:.6rem .4rem;white-space:nowrap">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:<?= $c[0] ?>;color:<?= $c[1] ?>;font-size:.7rem;font-weight:700;margin-right:.5rem;vertical-align:middle">
                                    <?= e(strtoupper(substr($b, 0, 2))) ?>
                                </span>

                                <b><?= e($brandNames[$b]) ?></b>
                            </td>

                            <?php foreach ($brandProducts as $key => $label): ?>
                                <?php $price = $brandRows[$b][$key] ?? null; ?>

                                <td style="text-align:right;padding:.6rem .4rem;font-weight:700">
                                    <?= $price !== null ? '₱' . number_format($price, 2) : '<span class="muted">No data</span>' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php else: ?>
            <p class="muted">
                Brand prices are temporarily unavailable. Please use the source link below.
            </p>
        <?php endif; ?>

        <p class="muted" style="margin-top:.6rem">
            Source:
            <a href="https://www.fuelprice.ph/fuel-price/north-luzon" target="_blank" rel="noopener">
                FuelPrice.ph – North Luzon
            </a>
            · Auto-refreshes daily when the source is reachable. Fuel adjustments usually take effect weekly, so prices may not change every day.
        </p>
    </div>
</section>

<!-- Announcements and news -->
<div class="grid3" style="grid-template-columns:1fr 1fr">

    <!-- Announcements -->
    <section class="card glass fold" data-fold="announcements">
        <div class="foldhead">
            <h2>Announcements</h2>
            <button type="button" class="btn sm foldbtn" aria-expanded="true" title="Minimize">−</button>
        </div>

        <div class="foldbody">
            <div class="list">
                <?php foreach ($ann as $a): ?>
                    <a href="<?= e(ann_href($a)) ?>"<?= ann_attr($a) ?>>
                        <?= e($a['title']) ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!$ann) echo '<p class="muted">No announcements yet.</p>'; ?>
            </div>

            <a class="btn sm blue" href="feed.php?type=announcements">All announcements</a>
        </div>
    </section>

    <!-- News -->
    <section class="card glass fold" data-fold="news">
        <div class="foldhead">
            <h2>News</h2>
            <button type="button" class="btn sm foldbtn" aria-expanded="true" title="Minimize">−</button>
        </div>

        <div class="foldbody">
            <div class="list">
                <?php foreach ($news as $n): ?>
                    <a href="<?= e($n['url']) ?>" target="_blank" rel="noopener">
                        <?= e($n['title']) ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!$news) echo '<p class="muted">No headlines yet. Check back soon.</p>'; ?>
            </div>

            <a class="btn sm blue" href="feed.php?type=news">All news</a>
        </div>
    </section>

</div>

<?php require 'includes/footer.php'; ?>
