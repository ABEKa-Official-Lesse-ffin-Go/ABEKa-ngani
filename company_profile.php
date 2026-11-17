<?php
// Employer Company Profile Editor: manage company name, industry, contact info, address, and links.
require 'includes/config.php';

$u = need('employer');
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

$ok = '';
$err = '';

// Handle company profile save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid request.');
    }

    $company       = trim((string)($_POST['company'] ?? ''));
    $industry      = trim((string)($_POST['industry'] ?? ''));
    $companySize   = trim((string)($_POST['company_size'] ?? ''));
    $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
    $contactEmail  = trim((string)($_POST['contact_email'] ?? ''));
    $contactPhone  = trim((string)($_POST['contact_phone'] ?? ''));
    $website       = trim((string)($_POST['website'] ?? ''));
    $address       = trim((string)($_POST['address'] ?? ''));
    $mapUrl        = trim((string)($_POST['map_url'] ?? ''));
    $description   = trim((string)($_POST['description'] ?? ''));

    if ($company === '') {
        $err = 'Company / Business Name is required.';
    } elseif ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid company contact email address.';
    } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $err = 'Please enter a valid Website or Facebook Page URL (starting with https:// or http://).';
    } elseif ($mapUrl !== '' && !filter_var($mapUrl, FILTER_VALIDATE_URL)) {
        $err = 'Please enter a valid Google Maps URL (starting with https:// or http://).';
    } else {
        $exists = q('SELECT 1 FROM employer_profiles WHERE user_id = ?', [$u['id']])->fetch();
        if ($exists) {
            q(
                'UPDATE employer_profiles
                 SET company = ?, industry = ?, company_size = ?, contact_person = ?,
                     contact_email = ?, contact_phone = ?, website = ?, address = ?,
                     map_url = ?, description = ?
                 WHERE user_id = ?',
                [
                    $company, $industry ?: null, $companySize ?: null, $contactPerson ?: null,
                    $contactEmail ?: null, $contactPhone ?: null, $website ?: null, $address ?: null,
                    $mapUrl ?: null, $description ?: null, $u['id']
                ]
            );
        } else {
            q(
                'INSERT INTO employer_profiles
                 (user_id, company, industry, company_size, contact_person, contact_email, contact_phone, website, address, map_url, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $u['id'], $company, $industry ?: null, $companySize ?: null, $contactPerson ?: null,
                    $contactEmail ?: null, $contactPhone ?: null, $website ?: null, $address ?: null,
                    $mapUrl ?: null, $description ?: null
                ]
            );
        }

        $ok = 'Company details saved successfully.';
        audit_log('Company Profile Updated', 'Updated details for ' . $company);
    }
}

// Load current company profile
$ep = q('SELECT * FROM employer_profiles WHERE user_id = ?', [$u['id']])->fetch() ?: [];

$sizes = ['1–10 employees', '11–50 employees', '51–200 employees', '201–500 employees', '500+ employees'];

$title = 'Edit Company Details';
require 'includes/header.php';
?>

<!-- Edit Company Details Form -->
<section class="card glass">
    <div class="dash-head-row">
        <div>
            <h1>Edit Company Details</h1>
            <p class="muted">Keep your business profile, contact information, and Pampanga location up to date for applicants.</p>
        </div>
        <a class="btn sm blue" href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($ok): ?><p class="alert-admin-success"><?=e($ok)?></p><?php endif; ?>
    <?php if ($err): ?><p class="alert-admin-error"><?=e($err)?></p><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">

        <label>
            Company / Business Name *
            <input type="text" name="company" required maxlength="160" placeholder="e.g., Kapampangan Tech Solutions" value="<?=e($ep['company'] ?? $u['name'] ?? '')?>">
        </label>

        <div class="row" style="gap:12px; flex-wrap:wrap;">
            <label style="flex:1; min-width:220px;">
                Industry / Sector
                <input type="text" name="industry" maxlength="100" placeholder="e.g., IT, Retail, Food &amp; Beverage, BPO" value="<?=e($ep['industry'] ?? '')?>">
            </label>

            <label style="flex:1; min-width:220px;">
                Company Size
                <select name="company_size">
                    <option value="">Select size</option>
                    <?php foreach ($sizes as $sz): ?>
                        <option value="<?=e($sz)?>" <?=($ep['company_size'] ?? '') === $sz ? 'selected' : ''?>><?=e($sz)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="row" style="gap:12px; flex-wrap:wrap;">
            <label style="flex:1; min-width:220px;">
                HR / Contact Person
                <input type="text" name="contact_person" maxlength="120" placeholder="Full name of recruiter or owner" value="<?=e($ep['contact_person'] ?? '')?>">
            </label>

            <label style="flex:1; min-width:220px;">
                Company Contact Email
                <input type="email" name="contact_email" maxlength="190" placeholder="hr@company.com" value="<?=e($ep['contact_email'] ?? $u['email'] ?? '')?>">
            </label>

            <label style="flex:1; min-width:200px;">
                Contact Phone / Mobile
                <input type="text" name="contact_phone" maxlength="50" placeholder="e.g., 0917-123-4567" value="<?=e($ep['contact_phone'] ?? '')?>">
            </label>
        </div>

        <label>
            Exact Business / Office Address (Pampanga)
            <input type="text" name="address" maxlength="255" placeholder="Street, Barangay, Municipality/City, Pampanga" value="<?=e($ep['address'] ?? '')?>">
        </label>

        <div class="row" style="gap:12px; flex-wrap:wrap;">
            <label style="flex:1; min-width:240px;">
                Official Website or Facebook Page URL
                <input type="url" name="website" maxlength="255" placeholder="https://facebook.com/yourcompany" value="<?=e($ep['website'] ?? '')?>">
            </label>

            <label style="flex:1; min-width:240px;">
                Google Maps Location Link (Optional)
                <input type="url" name="map_url" maxlength="500" placeholder="https://maps.google.com/..." value="<?=e($ep['map_url'] ?? '')?>">
            </label>
        </div>

        <label>
            About the Company
            <textarea name="description" rows="4" placeholder="Describe your business, workplace culture, products/services, and what you look for in applicants..."><?=e($ep['description'] ?? '')?></textarea>
        </label>

        <div style="margin-top:12px;">
            <button class="btn" type="submit">Save Company Details</button>
        </div>
    </form>
</section>

<?php require 'includes/footer.php'; ?>
