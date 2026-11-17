<?php
// Admin-only Emergency Hotline Manager: add, edit, verify, or delete Pampanga hotlines.
require_once __DIR__ . '/includes/config.php';
$u = need('admin');
$title = 'Manage Emergency Hotlines';

$services = ['PNP','BFP','PDRRMO','CDRRMO','MDRRMO','LGU','Medical','Rescue'];
$districtOptions = ['1st District','2nd District','3rd District','4th District','Provincial / Highly Urbanized City'];
$areas = [
    '1st District' => ['Mabalacat City','Magalang'],
    '2nd District' => ['Floridablanca','Guagua','Lubao','Porac','Santa Rita','Sasmuan'],
    '3rd District' => ['Arayat','Bacolor','City of San Fernando','Mexico','Santa Ana'],
    '4th District' => ['Apalit','Candaba','Macabebe','Masantol','Minalin','San Luis','San Simon','Santo Tomas'],
    'Provincial / Highly Urbanized City' => ['PG-Pampanga', 'Angeles City']
];

if (empty($_SESSION['alert_admin_csrf'])) {
    $_SESSION['alert_admin_csrf'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['alert_admin_csrf'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid security token. Please reload the page and try again.');
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            q('DELETE FROM emergency_hotlines WHERE id=?', [(int)($_POST['id'] ?? 0)]);
            $message = 'Hotline record deleted.';
            audit_log('Hotline Deleted', 'Deleted hotline ID ' . (int)($_POST['id'] ?? 0));
        } elseif ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $district = trim((string)($_POST['district'] ?? ''));
            $municipality = trim((string)($_POST['municipality'] ?? ''));
            $service = trim((string)($_POST['service'] ?? ''));
            $office = trim((string)($_POST['office_name'] ?? ''));
            $hotline = trim((string)($_POST['hotline'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $verified = !empty($_POST['verified']) ? date('Y-m-d') : null;

            if (!in_array($district, $districtOptions, true) ||
                !in_array($municipality, $areas[$district] ?? [], true) ||
                !in_array($service, $services, true) ||
                $office === '' || $hotline === '') {
                throw new RuntimeException('Please complete all required fields with valid district, municipality, service, office and hotline.');
            }
            if (strlen($office) > 180 || strlen($hotline) > 150 || strlen($address) > 255) {
                throw new RuntimeException('One or more fields exceed the allowed length.');
            }

            if ($id > 0) {
                q('UPDATE emergency_hotlines SET district=?, municipality=?, service=?, office_name=?, hotline=?, address=?, verified_at=? WHERE id=?',
                    [$district,$municipality,$service,$office,$hotline,$address ?: null,$verified,$id]);
                $message = 'Hotline record updated.';
                audit_log('Hotline Updated', $municipality . ' ' . $service . ': ' . $hotline);
            } else {
                q('INSERT INTO emergency_hotlines (district, municipality, service, office_name, hotline, address, verified_at) VALUES (?,?,?,?,?,?,?)',
                    [$district,$municipality,$service,$office,$hotline,$address ?: null,$verified]);
                $message = 'Hotline record added.';
                audit_log('Hotline Added', $municipality . ' ' . $service . ': ' . $hotline);
            }
        }
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save this record. Check that the emergency hotline database migration has been imported.';
        if (!($e instanceof RuntimeException)) {
            error_log('ABEKa hotline admin: ' . $e->getMessage());
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = q('SELECT * FROM emergency_hotlines WHERE id=?', [(int)$_GET['edit']])->fetch() ?: null;
}
$rows = [];
try {
    $rows = q('SELECT * FROM emergency_hotlines ORDER BY FIELD(district,\'1st District\',\'2nd District\',\'3rd District\',\'4th District\',\'Provincial / Highly Urbanized City\'), municipality, service LIMIT 500')->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: 'Database table missing. Import sql/update_emergency_alerts.sql first.';
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Emergency Hotline Management</h1>
    <p class="muted">Dev/admin access only. Keep every number current and verify it with the responsible office before publishing.</p>
    <?php if ($message): ?><p class="alert-admin-success"><?=e($message)?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert-admin-error"><?=e($error)?></p><?php endif; ?>

    <form method="post" class="alert-admin-form">
        <input type="hidden" name="csrf" value="<?=e($token)?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=e($edit['id'] ?? 0)?>">
        <label>District
            <select name="district" id="alertDistrict" required>
                <option value="">Choose district</option>
                <?php foreach ($districtOptions as $d): ?>
                    <option value="<?=e($d)?>" <?=($edit['district'] ?? '') === $d ? 'selected' : ''?>><?=e($d)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Municipality / City
            <select name="municipality" id="alertMunicipality" required>
                <option value="<?=e($edit['municipality'] ?? '')?>"><?=e($edit['municipality'] ?? 'Choose municipality')?></option>
            </select>
        </label>
        <label>Service
            <select name="service" required>
                <option value="">Choose service</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?=e($s)?>" <?=($edit['service'] ?? '') === $s ? 'selected' : ''?>><?=e($s)?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Office name
            <input name="office_name" maxlength="180" required value="<?=e($edit['office_name'] ?? '')?>" placeholder="e.g. Municipal Police Station">
        </label>
        <label>Hotline number
            <input name="hotline" maxlength="150" required value="<?=e($edit['hotline'] ?? '')?>" placeholder="One number per record">
        </label>
        <label>Office address (optional)
            <input name="address" maxlength="255" value="<?=e($edit['address'] ?? '')?>" placeholder="Street, barangay, municipality">
        </label>
        <label class="alert-admin-check">
            <input type="checkbox" name="verified" value="1" <?=!empty($edit['verified_at']) ? 'checked' : ''?>>
            Mark as verified today
        </label>
        <div class="alert-filter-actions">
            <button class="btn" type="submit"><?= $edit ? 'Update hotline' : 'Add hotline' ?></button>
            <?php if ($edit): ?><a class="btn blue" href="alert_admin.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2>Existing hotline records (<?=count($rows)?>)</h2>
    <div class="alert-admin-table-wrap">
        <table class="alert-admin-table">
            <thead><tr><th>Area</th><th>Service / Office</th><th>Hotline</th><th>Verification</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?=e($r['municipality'])?><br><small><?=e($r['district'])?></small></td>
                    <td><?=e($r['service'])?><br><?=e($r['office_name'])?></td>
                    <td><?=e($r['hotline'])?></td>
                    <td><?=!empty($r['verified_at']) ? e($r['verified_at']) : 'Pending'?></td>
                    <td>
                        <a class="btn blue sm" href="alert_admin.php?edit=<?=(int)$r['id']?>">Edit</a>
                        <form method="post" class="alert-inline-delete" onsubmit="return confirm('Delete this hotline record?')">
                            <input type="hidden" name="csrf" value="<?=e($token)?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                            <button class="btn danger sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">No hotline records have been added.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(function () {
    const areas = <?=json_encode($areas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
    const district = document.getElementById('alertDistrict');
    const municipality = document.getElementById('alertMunicipality');
    const selected = <?=json_encode($edit['municipality'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
    function refreshMunicipalities() {
        const values = areas[district.value] || [];
        municipality.innerHTML = '';
        const first = document.createElement('option');
        first.value = '';
        first.textContent = 'Choose municipality';
        municipality.appendChild(first);
        values.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            if (value === selected) option.selected = true;
            municipality.appendChild(option);
        });
    }
    district.addEventListener('change', refreshMunicipalities);
    if (district.value) refreshMunicipalities();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
