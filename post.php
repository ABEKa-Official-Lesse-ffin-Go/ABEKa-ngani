<?php
// Set the page title and include the header
$title = 'Announcement';
require 'includes/header.php';

// Retrieve the announcement using its ID
$a = q(
    'SELECT * FROM announcements WHERE id=?',
    [(int)($_GET['id'] ?? 0)]
)->fetch();
?>

<!-- Announcement content -->
<article class="card glass">

    <?php if ($a): ?>

        <!-- Display announcement title -->
        <h1><?=e($a['title'])?></h1>

        <!-- Display announcement posting date -->
        <p class="muted"><?=e($a['posted'])?></p>

        <!-- Display announcement body while preserving line breaks -->
        <p><?=nl2br(e($a['body']))?></p>

    <?php else: ?>

        <!-- Display message if announcement does not exist -->
        <p>Post not found.</p>

    <?php endif; ?>

</article>

<?php
// Include the footer
require 'includes/footer.php';
