<?php
// Include database configuration and check if the user is an employee
require 'includes/config.php';
need('employee');

// Get the selected location filter
$loc = $_GET['loc'] ?? '';

// Set the page title and include the header
$title = 'Job Hiring';
require 'includes/header.php';

// Retrieve all available LGU locations
$lg = q('SELECT name FROM lgus ORDER BY name')->fetchAll();

// Retrieve job listings, optionally filtered by location
$j = q(
    'SELECT j.id,j.title,j.description,j.industry,j.location,j.salary_offer,p.company
    FROM jobs j
    LEFT JOIN employer_profiles p ON p.user_id=j.employer_id'
    . ($loc ? ' WHERE j.location=?' : '') .
    ' ORDER BY j.created DESC',
    $loc ? [$loc] : []
)->fetchAll();

// Retrieve job IDs that the employee has already applied for
$done = array_column(
    q(
        'SELECT job_id FROM applications WHERE employee_id=?',
        [user()['id']]
    )->fetchAll(),
    'job_id'
);
?>

<!-- Job hiring page heading -->
<h1>Job Hiring</h1>

<!-- Search input for filtering job listings -->
<input id="jobSearch" placeholder="Search for job role / industry category / company">

<div class="layout">

    <!-- Location filter sidebar -->
    <aside class="card glass side">

        <b>Location</b>

        <!-- Display all locations option -->
        <a class="<?=$loc === '' ? 'on' : ''?>" href="jobs.php">
            All locations
        </a>

        <!-- Display available LGU locations -->
        <?php foreach ($lg as $l): ?>
            <a
                class="<?=$l['name'] === $loc ? 'on' : ''?>"
                href="?loc=<?=urlencode($l['name'])?>"
            >
                <?=e($l['name'])?>
            </a>
        <?php endforeach; ?>

    </aside>

    <!-- Job listings table -->
    <div class="card glass">

        <table id="jobs">

            <thead>
                <tr>
                    <th>Job title</th>
                    <th>Company</th>
                    <th>Job description</th>
                    <th>Industry</th>
                    <th>Location</th>
                    <th>Est. Salary</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

                <!-- Display available job listings -->
                <?php foreach ($j as $r): ?>
                    <tr>
                        <td><?=e($r['title'])?></td>

                        <td><?=e($r['company'] ?: '—')?></td>

                        <td><?=e(mb_strimwidth($r['description'], 0, 80, '…'))?></td>

                        <td><?=e($r['industry'])?></td>

                        <td><?=e($r['location'])?></td>

                        <td><?=e($r['salary_offer'] ?: 'Negotiable')?></td>

                        <td>
                            <!-- View job details button -->
                            <a
                                class="btn sm"
                                title="View job"
                                href="job.php?id=<?=$r['id']?>"
                            >
                                <?=ico('eye')?>
                            </a>

                            <!-- Show application status if already applied -->
                            <?=in_array($r['id'], $done) ? ' <span class="muted">Applied</span>' : ''?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Display message if no jobs are available -->
                <?php if (!$j): ?>
                    <tr>
                        <td colspan="7" class="muted">
                            No vacancies<?=$loc ? ' in ' . e($loc) : ''?> yet.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php
// Include the footer
require 'includes/footer.php';
