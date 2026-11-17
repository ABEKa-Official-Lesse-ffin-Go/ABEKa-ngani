<?php
// Include database configuration and check if the user is an employee
require 'includes/config.php';
$u = need('employee');

// Get the selected job ID
$id = (int)($_GET['id'] ?? 0);

// Set the waiting period after a "Not selected" decision
$WAIT = 30; // days to wait after "Not selected"

// Generate CSRF token if one does not exist
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

// Retrieve job details and employer company name
$j = q(
    'SELECT j.*,p.company
    FROM jobs j
    LEFT JOIN employer_profiles p ON p.user_id=j.employer_id
    WHERE j.id=?',
    [$id]
)->fetch() ?: die('Job not found');

// Function to retrieve the employee's application for this job
$get = function () use ($id, $u) {
    return q(
        'SELECT * FROM applications WHERE job_id=? AND employee_id=?',
        [$id, $u['id']]
    )->fetch();
};

// Function to calculate the reapplication waiting period
$wait = function ($a) use ($WAIT) {
    if (!$a || $a['status'] !== 'Not Selected') return null;

    $t = strtotime(($a['status_changed'] ?? null) ?: $a['created']) + $WAIT * 86400;

    return $t > time() ? $t : null;
};

// Check if the employee already has an active application at the same company (different job).
$sameCompanyApp = q(
    "SELECT a.id, j.title
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE a.employee_id = ?
     AND j.employer_id = ?
     AND a.job_id != ?
     AND a.status IN ('For Review', 'For Interview', 'Interview Passed', 'Offer Accepted')
     LIMIT 1",
    [$u['id'], $j['employer_id'], $id]
)->fetch();

// Check 30-day company-wide cooldown after rejection ("Not selected") at this company.
$companyRejectRow = q(
    "SELECT COALESCE(a.status_changed, a.created) AS decided_at, j.title
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE a.employee_id = ?
     AND j.employer_id = ?
     AND a.status = 'Not Selected'
     ORDER BY COALESCE(a.status_changed, a.created) DESC
     LIMIT 1",
    [$u['id'], $j['employer_id']]
)->fetch();
$companyRejectUntil = null;
if ($companyRejectRow) {
    $tRej = strtotime($companyRejectRow['decided_at']) + $WAIT * 86400;
    if ($tRej > time()) {
        $companyRejectUntil = $tRej;
    }
}

// Check 6-month company-wide cooldown after being hired by this company.
$companyHiredRow = q(
    "SELECT COALESCE(a.status_changed, a.created) AS hired_at, j.title
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE a.employee_id = ?
     AND j.employer_id = ?
     AND a.status IN ('Hired', 'Offer Accepted', 'Expired')
     ORDER BY COALESCE(a.status_changed, a.created) DESC
     LIMIT 1",
    [$u['id'], $j['employer_id']]
)->fetch();
$companyHiredUntil = null;
if ($companyHiredRow) {
    $tHired = strtotime('+6 months', strtotime($companyHiredRow['hired_at']));
    if ($tHired > time()) {
        $companyHiredUntil = $tHired;
    }
}

// Get the employee's current application and initialize the message
$app = $get();
$msg = '';

// Process submitted application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify the CSRF token to prevent unauthorized requests
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''))
        die('Invalid request.');

    // Handle application withdrawal
    if (($_POST['action'] ?? '') === 'withdraw') {

        // Allow withdrawal only if the application is not hired or rejected
        if ($app && !in_array($app['status'], ['Hired', 'Not Selected'], true)) {

            // Delete the application from the database
            q('DELETE FROM applications WHERE id=?', [$app['id']]);

            // Notify the employer about the withdrawal
            notify(
                $j['employer_id'],
                "{$u['name']} withdrew the application for \"{$j['title']}\".",
                'applicants.php?job=' . $id
            );

            // Notify the employee about the withdrawal
            notify(
                $u['id'],
                "You withdrew your application for \"{$j['title']}\". You can apply again anytime.",
                'inbox.php'
            );

            // Reset application information and display confirmation
            $app = null;
            $msg = 'Application withdrawn.';
            audit_log('Application Withdrawn', 'Withdrew from ' . $j['title']);
        }

    // Check 6-month cooldown after being hired by this company
    } elseif ($companyHiredUntil) {

        $msg = 'You were previously hired by this company. You can reapply to the same company after ' . date('F j, Y', $companyHiredUntil) . ' (6 months).';

    // Check 30-day cooldown after rejection for this job or this company
    } elseif (($until = $wait($app)) || $companyRejectUntil) {

        $blockUntil = max((int)($until ?? 0), (int)($companyRejectUntil ?? 0));
        $msg = 'You can reapply to this job/company after ' . date('F j, Y', $blockUntil) . ' (30 days after rejection).';

    // Block applying to another vacancy at the same company while an active application exists
    } elseif ($sameCompanyApp && (!$app || $app['status'] === 'Not Selected')) {
        $msg = 'You already have an active application at this company ("' . $sameCompanyApp['title'] . '"). You can apply for the same job role across different companies, but not multiple vacancies at the same company at the same time.';

    // Allow new applications or reapplications after rejection
    } elseif (!$app || $app['status'] === 'Not Selected') {

        // Insert a new application if none exists
        if (!$app)
            q(
                "INSERT INTO applications(job_id,employee_id,status) VALUES(?,?,'For Review')",
                [$id, $u['id']]
            );

        // Reset an existing rejected application
        else
            q(
                "UPDATE applications SET status='For Review',created=NOW(),status_changed=NULL,interview_at=NULL,interview_link=NULL,offer_file=NULL,offer_original_name=NULL,offer_sent_at=NULL,offer_accepted_at=NULL,hidden=0 WHERE id=?",
                [$app['id']]
            );

        // Prepare application confirmation message
        $m = "Your application for \"{$j['title']}\" was submitted. Expect contact within 1–2 business days.";

        // Notify the employee about the submitted application
        notify($u['id'], $m, 'inbox.php');

        // Notify the employer about the new application
        notify(
            $j['employer_id'],
            "{$u['name']} applied for \"{$j['title']}\".",
            'applicants.php?job=' . $id
        );

        // Retrieve the employee's email address
        $em = q(
            'SELECT email FROM users WHERE id=?',
            [$u['id']]
        )->fetch()['email'];

        // Send application confirmation email
        abeka_send_mail($em, 'ABEKa: application received', $m);

        // Refresh application information and display confirmation
        $app = $get();
        $msg = 'Application sent. A confirmation was added to your inbox.';
        audit_log('Application Submitted', 'Applied to ' . $j['title']);
    }
}

// Calculate remaining waiting period
$until = $wait($app);

// Set the page title and include the header
$title = $j['title'];
require 'includes/header.php';
?>

<!-- Job details section -->
<article class="card glass">

    <!-- Display job title and company name -->
    <h1><?=e($j['title'])?></h1>
    <p class="muted"><?=e($j['company'] ?? '')?></p>

    <!-- Display job description -->
    <h2>Description</h2>
    <p><?=nl2br(e($j['description']))?></p>

    <!-- Display job qualifications -->
    <h2>Qualifications</h2>
    <p><?=nl2br(e($j['qualifications']))?></p>

    <!-- Display job location and industry -->
    <p>
        <b>Location:</b> <?=e($j['location'])?>
        &nbsp;
        <b>Industry:</b> <?=e($j['industry'])?>
        &nbsp;
        <b>Estimated Salary Offer:</b> <?=e($j['salary_offer'] ?: 'Negotiable / Not specified')?>
    </p>

    <!-- Display action confirmation or error message -->
    <?php if ($msg) echo '<p><b>' . e($msg) . '</b></p>'; ?>

    <!-- Application action form -->
    <form method="post">

        <!-- Include CSRF token -->
        <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">

        <!-- Display available application actions based on status -->
        <?php if (!$app && $companyHiredUntil): ?>

            <!-- 6-month cooldown after being hired by this company -->
            <p class="alert-admin-error">
                You were previously hired by this company. You can reapply to this company after <b><?=date('F j, Y', $companyHiredUntil)?></b> (6 months).
            </p>
            <button class="btn" disabled>Apply now</button>

        <?php elseif (!$app && $companyRejectUntil): ?>

            <!-- 30-day cooldown after rejection by this company -->
            <p class="alert-admin-error">
                You were recently not selected by this company. You can reapply to this company after <b><?=date('F j, Y', $companyRejectUntil)?></b> (30 days).
            </p>
            <button class="btn" disabled>Apply now</button>

        <?php elseif (!$app && $sameCompanyApp): ?>

            <!-- Block duplicate active application at the same company -->
            <p class="alert-admin-error">
                You already have an active application with this company (<b><?=e($sameCompanyApp['title'])?></b>). You may apply for the same job role at different companies, but not multiple vacancies at the same company at the same time.
            </p>
            <button class="btn" disabled>Apply now</button>

        <?php elseif (!$app): ?>

            <!-- Allow employee to submit a new application -->
            <button class="btn" name="action" value="apply">
                Apply now
            </button>

        <?php elseif ($until): ?>

            <!-- Display remaining waiting period -->
            <p>
                Status: Not Selected. You can reapply after
                <b><?=date('F j, Y', $until)?></b>
                (<?=$WAIT?> days after the decision).
            </p>

            <button class="btn" disabled>Reapply</button>

        <?php elseif ($app['status'] === 'Not Selected'): ?>

            <!-- Allow employee to reapply after the waiting period -->
            <p>
                Status: Not Selected. The waiting period is over, so you may apply again.
            </p>

            <button class="btn" name="action" value="apply">
                Reapply
            </button>

        <?php elseif ($app['status'] === 'Hired'): ?>

            <!-- Display hired status -->
            <p>Status: <b>Hired</b>. Congratulations!</p>

        <?php else: ?>

            <!-- Display current application status and withdrawal option -->
            <p>Status: <b><?=e($app['status'])?></b></p>

            <button
                class="btn danger"
                name="action"
                value="withdraw"
                onclick="return confirm('Withdraw your application for this job?')"
            >
                Withdraw application
            </button>

        <?php endif; ?>

        <!-- Navigation links -->
        &nbsp;
        <a class="btn sm blue" href="jobs.php">Browse more jobs</a>
        &nbsp;
        <a class="btn sm blue" href="inbox.php">My applications</a>

    </form>

</article>

<?php
// Include the footer
require 'includes/footer.php';
