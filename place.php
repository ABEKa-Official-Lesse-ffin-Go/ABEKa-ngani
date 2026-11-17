<?php
// Include database configuration
require 'includes/config.php';

// Retrieve the LGU information using its slug
$l = q(
    'SELECT * FROM lgus WHERE slug=?',
    [$_GET['s'] ?? '']
)->fetch() ?: die('Place not found');

// Increase the LGU page view count
q('UPDATE lgus SET views=views+1 WHERE id=?', [$l['id']]);

// Include news functions and refresh local news and announcements
require_once 'includes/news.php';

refresh_news($l['slug'], $l['id'], '"' . $l['name'] . '" Pampanga');
refresh_announcements($l['slug'], $l['id'], lgu_ann_query($l['name']));

// Retrieve the latest three news articles
$news = q(
    'SELECT * FROM news WHERE lgu_id=? ORDER BY published DESC LIMIT 3',
    [$l['id']]
)->fetchAll();

// Retrieve the latest three local announcements
$ann = q(
    'SELECT * FROM announcements WHERE lgu_id=? ORDER BY posted DESC LIMIT 3',
    [$l['id']]
)->fetchAll();

// Set the page title and include the header
$title = $l['name'];
require 'includes/header.php';

// Load tourist spots for the selected Pampanga city or municipality.
require_once 'includes/tourist-spot.php';
$placeTouristSpots = $touristSpots[$l['slug']] ?? [];
?>

<!-- Display LGU name -->
<h1><?=e($l['name'])?></h1>

<!-- Main content layout -->
<div class="grid3" style="grid-template-columns:1fr 1fr">

    <!-- Latest news section -->
    <section class="card glass">

        <h2>Latest from the Inquirer</h2>

        <div class="list">

            <!-- Display retrieved news articles -->
            <?php foreach ($news as $n): ?>
                <a href="<?=e($n['url'])?>" target="_blank" rel="noopener">
                    <?=e($n['title'])?>
                </a>
            <?php endforeach; ?>

            <!-- Display message if there are no news articles -->
            <?php if (!$news) echo '<p class="muted">No stories yet.</p>'; ?>

        </div>

    </section>

    <!-- Local announcements section -->
    <section class="card glass">

        <h2>Local announcements</h2>

        <div class="list">

            <!-- Display retrieved local announcements -->
            <?php foreach ($ann as $a): ?>
                <a href="<?=e(ann_href($a))?>"<?=ann_attr($a)?>>
                    <?=e($a['title'])?>
                </a>
            <?php endforeach; ?>

        </div>

    </section>

</div>

<!-- Tourist Spots Recommendation -->
<?php if (!empty($placeTouristSpots)): ?>
<section class="card glass tourist-spots-section">
    <h2>Tourist Spots Recommendation</h2>
    <div class="grid3 tourist-spots-grid">
        <?php foreach ($placeTouristSpots as $spot): ?>
            <a
                href="<?= e($spot['map']) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="tourist-spot-link"
            >
                <div class="thumb">
                    <div
                        class="img"
                        style="background-image: url('<?= e(str_replace("'", "\\'", $spot['image'])) ?>')"
                    ></div>
                    <b><?= e($spot['name']) ?></b>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
// Include the footer
require 'includes/footer.php';
