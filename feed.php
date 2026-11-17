<?php
// RSS/Atom feed fetcher and parser for local Pampanga news and advisories.

$title = 'Feed';

require 'includes/header.php';
require_once 'includes/news.php';

// Refresh the latest news and announcements.
refresh_news('pampanga', null, 'Pampanga');
refresh_announcements('capitol', null, capitol_query());

// Check whether the user selected the news feed.
$isNews = ($_GET['type'] ?? '') === 'news';

// Get the latest 10 news articles or announcements.
$rows = $isNews
    ? q('SELECT title, url, published FROM news ORDER BY published DESC LIMIT 10')->fetchAll()
    : q('SELECT id, title, posted, url, source FROM announcements ORDER BY posted DESC LIMIT 10')->fetchAll();
?>

<!-- News and announcements feed -->
<section class="card glass">
    <h1><?= $isNews ? 'News' : 'Announcements' ?></h1>

    <div class="list">
        <?php
        // Display each item depending on the selected feed type.
        foreach ($rows as $r) {

            // Display news articles with their publication dates.
            if ($isNews) {
                echo '<a target="_blank" rel="noopener" href="' . e($r['url']) . '">'
                    . e($r['title']) . ' <span class="muted">'
                    . e($r['published']) . '</span></a>';

            // Display announcements with their source and posted date.
            } else {
                echo '<a href="' . e(ann_href($r)) . '"' . ann_attr($r) . '>'
                    . e($r['title']) . ' <span class="muted">'
                    . e(($r['source'] ? $r['source'] . ' · ' : '') . $r['posted'])
                    . '</span></a>';
            }
        }

        // Show a message when there are no available items.
        if (!$rows) {
            echo '<p class="muted">Nothing here yet. New items appear automatically; please check back in a few minutes.</p>';
        }
        ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
