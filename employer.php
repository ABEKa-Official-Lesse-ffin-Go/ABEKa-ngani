<?php

require 'includes/config.php';

// Make sure the user is logged in as an employer.
$u = need('employer');

$ok = '';

// Generate a CSRF token if one does not exist.
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

// Handle vacancy creation, updating, and deletion.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate the CSRF token.
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid request. Go back and try again.');
    }

    $p = $_POST;
    $id = (int) ($p['id'] ?? 0);

    // Check whether the vacancy belongs to the current employer.
    $mine = $id && q(
        'SELECT 1 FROM jobs WHERE id = ? AND employer_id = ?',
        [$id, $u['id']]
    )->fetch();

    // Delete an existing vacancy and its applications.
    if (($p['action'] ?? '') === 'delete') {

        if ($mine) {
            q('DELETE FROM applications WHERE job_id = ?', [$id]);
            q('DELETE FROM jobs WHERE id = ?', [$id]);

            $ok = 'Vacancy deleted.';
            audit_log('Vacancy Deleted', 'Deleted vacancy ID ' . $id);
        }

    // Update an existing vacancy.
    } elseif ($mine) {

        q(
            'UPDATE jobs
             SET title = ?, description = ?, qualifications = ?, location = ?, industry = ?, salary_offer = ?
             WHERE id = ? AND employer_id = ?',
            [
                $p['title'],
                $p['description'],
                $p['qualifications'],
                $p['location'],
                $p['industry'],
                trim((string)($p['salary_offer'] ?? '')) ?: null,
                $id,
                $u['id']
            ]
        );

        $ok = 'Vacancy updated.';
        audit_log('Vacancy Updated', 'Updated vacancy: ' . trim((string)$p['title']));

    // Create a new vacancy.
    } else {

        q(
            'INSERT INTO jobs(employer_id, title, description, qualifications, location, industry, salary_offer)
             VALUES(?,?,?,?,?,?,?)',
            [
                $u['id'],
                $p['title'],
                $p['description'],
                $p['qualifications'],
                $p['location'],
                $p['industry'],
                trim((string)($p['salary_offer'] ?? '')) ?: null
            ]
        );

        $ok = 'Vacancy posted.';
        audit_log('Vacancy Posted', 'Posted vacancy: ' . trim((string)$p['title']));
    }
}

// Get the vacancy details when editing.
$edit = (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit'])
)
    ? q(
        'SELECT * FROM jobs WHERE id = ? AND employer_id = ?',
        [(int) $_GET['edit'], $u['id']]
    )->fetch()
    : null;

// Set default values for the vacancy form.
$f = $edit ?: [
    'id' => '',
    'title' => '',
    'description' => '',
    'qualifications' => '',
    'location' => '',
    'industry' => '',
    'salary_offer' => ''
];

$title = 'Employer';

require 'includes/header.php';

// Get all available LGU locations.
$lg = q('SELECT name FROM lgus ORDER BY name')->fetchAll();

// Get the employer's vacancies and their applicant counts.
$mine = q(
    'SELECT j.id, j.title, j.location, j.salary_offer,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) n
     FROM jobs j
     WHERE employer_id = ?
     ORDER BY created DESC',
    [$u['id']]
)->fetchAll();

// Get the latest 20 notifications.
$al = q(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created DESC LIMIT 20',
    [$u['id']]
)->fetchAll();

// Mark all notifications as read.
q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$u['id']]);
?>

<!-- Employer dashboard layout -->
<div class="layout" style="grid-template-columns:1fr 1fr">

    <!-- Post or edit a vacancy -->
    <form method="post" action="employer.php" class="card glass">

        <h1><?= $edit ? 'Edit vacancy' : 'Post a vacancy' ?></h1>

        <?php if ($ok) echo '<p>' . e($ok) . '</p>'; ?>

        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="id" value="<?= e($f['id']) ?>">

        <label>
            Job title
            <input name="title" value="<?= e($f['title']) ?>" required>
        </label>

        <label>
            Job description
            <textarea name="description" rows="4" required><?= e($f['description']) ?></textarea>
        </label>

        <label>
            Qualifications
            <textarea name="qualifications" rows="3" required><?= e($f['qualifications']) ?></textarea>
        </label>

        <label>
            Location
            <select name="location">
                <?php foreach ($lg as $l): ?>
                    <option<?= $l['name'] === $f['location'] ? ' selected' : '' ?>>
                        <?= e($l['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Industry category
            <input name="industry" value="<?= e($f['industry']) ?>" required>
        </label>

        <label>
            Estimated Salary Offer
            <input name="salary_offer" maxlength="80" value="<?= e($f['salary_offer'] ?? '') ?>" placeholder="e.g. PHP 18,000 - 22,000 / month">
        </label>

        <button class="btn">
            <?= $edit ? 'Save changes' : 'Post vacancy' ?>
        </button>

        <?php if ($edit) echo ' <a class="btn sm danger" href="employer.php">Cancel</a>'; ?>

    </form>

    <!-- Employer notifications and vacancy list -->
    <div>

        <!-- Application alerts -->
        <section class="card glass" id="alerts">
            <h2>Application alerts</h2>

            <p>
                <a class="btn sm blue" href="applicants.php">View all applicants</a>
            </p>

            <?php foreach ($al as $n): ?>
                <a
                    class="card"
                    style="display:block;color:inherit;text-decoration:none"
                    href="<?= e($n['link'] ?: 'applicants.php') ?>"
                >
                    <?= e($n['message']) ?>

                    <div class="muted">
                        <?= e($n['created']) ?>
                    </div>
                </a>
            <?php endforeach; ?>

            <?php if (!$al) echo '<p class="muted">No applications yet.</p>'; ?>
        </section>

        <!-- Employer's posted vacancies -->
        <section class="card glass">
            <h2>My vacancies</h2>

            <table>
                <tr>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Est. Salary</th>
                    <th>Applicants</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($mine as $m): ?>
                    <tr>
                        <td><?= e($m['title']) ?></td>
                        <td><?= e($m['location']) ?></td>
                        <td><?= e($m['salary_offer'] ?: '—') ?></td>

                        <td>
                            <a href="applicants.php?job=<?= $m['id'] ?>">
                                <?= $m['n'] ?>
                            </a>
                        </td>

                        <td>
                            <div class="row">

                                <!-- View applicants -->
                                <a class="btn sm" href="applicants.php?job=<?= $m['id'] ?>">
                                    Applicants
                                </a>

                                <!-- Edit vacancy -->
                                <a class="btn sm blue" href="employer.php?edit=<?= $m['id'] ?>">
                                    Edit
                                </a>

                                <!-- Delete vacancy -->
                                <form
                                    method="post"
                                    action="employer.php"
                                    onsubmit="return confirm('Delete this vacancy and its <?= $m['n'] ?> application(s)? This cannot be undone.')"
                                >
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="action" value="delete">

                                    <button class="btn sm danger">Delete</button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$mine): ?>
                    <tr>
                        <td colspan="5" class="muted">
                            No vacancies yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </section>

    </div>
</div>

<?php require 'includes/footer.php'; ?>
