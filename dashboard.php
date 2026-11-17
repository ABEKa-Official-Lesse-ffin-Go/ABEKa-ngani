<?php
// Unified Role-Based Dashboard for Employee, Employer, and Admin accounts.
require_once __DIR__ . '/includes/config.php';

$u = need();
expire_stale_hired_offers();

$title = 'Dashboard';
$uid = (int)$u['id'];
$role = $u['role'];

// Load role-specific KPI metrics and recent personal activity logs.
$kpis = [];
$quickLinks = [];

if ($role === 'employee') {
    $totalApps = (int)q('SELECT COUNT(*) FROM applications WHERE employee_id = ?', [$uid])->fetchColumn();
    $hiredCount = (int)q("SELECT COUNT(*) FROM applications WHERE employee_id = ? AND status IN ('Hired','Offer Accepted')", [$uid])->fetchColumn();
    $docCount = (int)q('SELECT COUNT(*) FROM employee_documents WHERE user_id = ?', [$uid])->fetchColumn();
    $docTotal = count(employee_doc_types());
    $unreadCount = (int)q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid])->fetchColumn();

    $kpis = [
        ['label' => 'Job Applications', 'value' => $totalApps, 'note' => 'Submitted & tracked'],
        ['label' => 'Hired / Offers', 'value' => $hiredCount, 'note' => '14-day offer window'],
        ['label' => 'Required Documents', 'value' => $docCount . ' / ' . $docTotal, 'note' => 'Clearances & IDs'],
        ['label' => 'Unread Alerts', 'value' => $unreadCount, 'note' => 'Inbox notifications'],
    ];
    $quickLinks = [
        ['label' => 'Browse Jobs Hiring', 'href' => 'jobs.php', 'cls' => 'btn'],
        ['label' => 'My Profile & Documents', 'href' => 'profile.php', 'cls' => 'btn blue'],
        ['label' => 'Inbox & Offers', 'href' => 'inbox.php', 'cls' => 'btn blue'],
        ['label' => 'E-Learnings & CSE', 'href' => 'elearning.php', 'cls' => 'btn blue'],
    ];
} elseif ($role === 'employer') {
    $vacancies = (int)q('SELECT COUNT(*) FROM jobs WHERE employer_id = ?', [$uid])->fetchColumn();
    $applicants = (int)q('SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.employer_id = ?', [$uid])->fetchColumn();
    $hired = (int)q("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.employer_id = ? AND a.status IN ('Hired','Offer Accepted')", [$uid])->fetchColumn();
    $unreadCount = (int)q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid])->fetchColumn();

    $kpis = [
        ['label' => 'Posted Vacancies', 'value' => $vacancies, 'note' => 'Active job listings'],
        ['label' => 'Total Applicants', 'value' => $applicants, 'note' => 'Across all vacancies'],
        ['label' => 'Hired Applicants', 'value' => $hired, 'note' => 'Hired & accepted'],
        ['label' => 'Unread Alerts', 'value' => $unreadCount, 'note' => 'New applications & replies'],
    ];
    $quickLinks = [
        ['label' => 'Post / Manage Vacancies', 'href' => 'employer.php', 'cls' => 'btn'],
        ['label' => 'Review Applicants', 'href' => 'applicants.php', 'cls' => 'btn blue'],
    ];
} else {
    $usersCount = (int)q('SELECT COUNT(*) FROM users')->fetchColumn();
    $jobsCount = (int)q('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $appsCount = (int)q('SELECT COUNT(*) FROM applications')->fetchColumn();
    $hotlinesCount = 0;
    try {
        $hotlinesCount = (int)q('SELECT COUNT(*) FROM emergency_hotlines')->fetchColumn();
    } catch (Throwable $e) {
        $hotlinesCount = 0;
    }

    $kpis = [
        ['label' => 'Registered Users', 'value' => $usersCount, 'note' => 'Employees, Employers & Admins'],
        ['label' => 'Job Vacancies', 'value' => $jobsCount, 'note' => 'Total posted jobs'],
        ['label' => 'Applications', 'value' => $appsCount, 'note' => 'Total submissions'],
        ['label' => 'Emergency Hotlines', 'value' => $hotlinesCount, 'note' => 'Pampanga directory'],
    ];
    $quickLinks = [
        ['label' => 'Audit Trail / Log History', 'href' => 'audit_trail.php', 'cls' => 'btn'],
        ['label' => 'Edit Site Content', 'href' => 'content_admin.php', 'cls' => 'btn blue'],
        ['label' => 'Manage Fuel Prices', 'href' => 'fuel_admin.php', 'cls' => 'btn blue'],
    ];
}

// Fetch recent or all audit log entries (all logs for Admin, own logs for Employee/Employer).
$showAllLogs = isset($_GET['all_logs']) && $_GET['all_logs'] === '1';
$recentLogs = [];
try {
    if ($role === 'admin') {
        $limit = $showAllLogs ? 300 : 12;
        $recentLogs = q("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT {$limit}")->fetchAll();
    } else {
        $limit = $showAllLogs ? 300 : 10;
        $recentLogs = q("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}", [$uid])->fetchAll();
    }
} catch (Throwable $e) {
    $recentLogs = [];
}

require __DIR__ . '/includes/header.php';
?>

<!-- Dashboard Header -->
<section class="card glass">
    <p class="elearn-kicker">ABEKa <?=e(strtoupper($role))?> DASHBOARD</p>
    <h1>Welcome, <?=e($u['name'])?></h1>
    <p class="muted">Quick overview of your account activity, metrics, and shortcuts.</p>

    <div class="dash-actions">
        <?php foreach ($quickLinks as $ql): ?>
            <a class="<?=e($ql['cls'])?>" href="<?=e($ql['href'])?>"><?=e($ql['label'])?></a>
        <?php endforeach; ?>
    </div>
</section>

<!-- KPI Summary Cards -->
<section class="dash-kpi-grid">
    <?php foreach ($kpis as $k): ?>
        <div class="card glass dash-kpi">
            <span class="muted"><?=e($k['label'])?></span>
            <strong class="dash-kpi-val"><?=e($k['value'])?></strong>
            <small class="muted"><?=e($k['note'])?></small>
        </div>
    <?php endforeach; ?>
</section>

<!-- Recent Activity / Audit Log Preview (All Users: Minimize & View All) -->
<section class="card glass">
    <div class="dash-head-row">
        <h2><?=$role === 'admin' ? 'Recent System Audit Trail' : 'My Recent Activity Log'?></h2>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($showAllLogs): ?>
                <a class="btn sm blue" href="dashboard.php">Show Recent</a>
            <?php else: ?>
                <a class="btn sm blue" href="dashboard.php?all_logs=1">View All</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <a class="btn sm blue" href="audit_trail.php">Full Audit Trail &#8599;</a>
            <?php endif; ?>
            <button type="button" class="btn sm" id="abeka-toggle-activity-btn" title="Minimize" aria-label="Minimize" onclick="abekaToggleActivityLog()">-</button>
        </div>
    </div>

    <div id="abeka-activity-log-body">
    <?php if (!$recentLogs): ?>
        <p class="muted">No activity recorded yet.</p>
    <?php else: ?>
        <div class="alert-admin-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <?php if ($role === 'admin'): ?>
                            <th>User</th>
                            <th>Role</th>
                        <?php endif; ?>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?=e($log['created_at'])?></td>
                            <?php if ($role === 'admin'): ?>
                                <td><?=e($log['user_name'] ?: 'Guest')?></td>
                                <td><?=e($log['user_role'] ?: '—')?></td>
                            <?php endif; ?>
                            <td><b><?=e($log['action'])?></b></td>
                            <td><?=e($log['details'] ?: '—')?></td>
                            <td><small class="muted"><?=e($log['ip_address'] ?: '—')?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</section>

<script>
// Toggle minimize/expand for the Recent Activity Log panel
function abekaToggleActivityLog() {
    const body = document.getElementById('abeka-activity-log-body');
    const btn = document.getElementById('abeka-toggle-activity-btn');
    if (!body || !btn) return;
    const isHidden = body.style.display === 'none';
    body.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? '-' : '+';
    btn.title = isHidden ? 'Minimize' : 'Expand';
    btn.setAttribute('aria-label', btn.title);
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
