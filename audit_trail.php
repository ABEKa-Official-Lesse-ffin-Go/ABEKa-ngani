<?php
// Admin-only Audit Trail / Log History viewer with search, role filtering, and CSV export.
require_once __DIR__ . '/includes/config.php';

$u = need('admin');
$title = 'Audit Trail & Log History';

$search = trim((string)($_GET['q'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));

$sql = 'SELECT * FROM audit_logs WHERE 1=1';
$params = [];

if ($roleFilter !== '' && in_array($roleFilter, ['employee', 'employer', 'admin', 'guest'], true)) {
    $sql .= ' AND user_role = ?';
    $params[] = $roleFilter;
}

if ($search !== '') {
    $sql .= ' AND (user_name LIKE ? OR action LIKE ? OR details LIKE ? OR ip_address LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql .= ' ORDER BY created_at DESC LIMIT 300';
$logs = q($sql, $params)->fetchAll();

// Handle CSV export for reporting/documentation.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="abeka_audit_trail_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Timestamp', 'User ID', 'User Name', 'Role', 'Action', 'Details', 'IP Address']);
    foreach ($logs as $r) {
        fputcsv($out, [
            $r['id'],
            $r['created_at'],
            $r['user_id'],
            $r['user_name'],
            $r['user_role'],
            $r['action'],
            $r['details'],
            $r['ip_address'],
        ]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<section class="card glass">
    <p class="elearn-kicker">SYSTEM SECURITY &amp; LOGGING</p>
    <h1>Audit Trail / Log History</h1>
    <p class="muted">Tracks logins, failed logins, logouts, registrations, password resets, job actions, document uploads, and admin updates.</p>

    <form method="get" action="audit_trail.php" class="alert-filter-form">
        <label>
            Role
            <select name="role">
                <option value="">All roles</option>
                <?php foreach (['employee', 'employer', 'admin', 'guest'] as $rf): ?>
                    <option value="<?=e($rf)?>" <?=$roleFilter === $rf ? 'selected' : ''?>><?=e(ucfirst($rf))?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="alert-search-field">
            Search user, action, details, or IP
            <input type="search" name="q" value="<?=e($search)?>" placeholder="e.g. Login, Hired, Document">
        </label>

        <div class="alert-filter-actions">
            <button class="btn" type="submit">Filter</button>
            <a class="btn blue" href="audit_trail.php">Reset</a>
            <a class="btn blue" href="audit_trail.php?role=<?=rawurlencode($roleFilter)?>&amp;q=<?=rawurlencode($search)?>&amp;export=csv">Export CSV &#8599;</a>
        </div>
    </form>
</section>

<section class="card glass">
    <h2>Recorded Events (<?=count($logs)?>)</h2>

    <?php if (!$logs): ?>
        <p class="muted">No matching audit log records found.</p>
    <?php else: ?>
        <div class="alert-admin-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $r): ?>
                        <tr>
                            <td><?=e($r['created_at'])?></td>
                            <td><?=e($r['user_name'] ?: 'Guest')?></td>
                            <td><?=e($r['user_role'] ?: '—')?></td>
                            <td><b><?=e($r['action'])?></b></td>
                            <td><?=e($r['details'] ?: '—')?></td>
                            <td><small class="muted"><?=e($r['ip_address'] ?: '—')?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
