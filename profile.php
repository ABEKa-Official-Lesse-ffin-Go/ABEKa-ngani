<?php
// Include database configuration and check if the user is an employee
require 'includes/config.php';
$u = need('employee');

// Process submitted profile information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;

    // Save employee personal information
    q(
        'REPLACE INTO employee_profiles VALUES(?,?,?,?,?,?,?)',
        [
            $u['id'],
            $p['first_name'],
            $p['middle_name'],
            $p['last_name'],
            (int)$p['age'],
            $p['dob'] ?: null,
            $p['birth_place']
        ]
    );

    // Update the account name to match the employee's full name
    $full = trim(preg_replace('/\s+/', ' ', $p['first_name'] . ' ' . $p['middle_name'] . ' ' . $p['last_name']));
    // keep the account name (header greeting, employer views) in sync
    q('UPDATE users SET name=? WHERE id=?', [$full, $u['id']]);
    $_SESSION['user']['name'] = $full;

    // Delete existing resume items, skills, and contacts before saving updated information
    q('DELETE FROM resume_items WHERE user_id=?', [$u['id']]);
    q('DELETE FROM employee_skills WHERE user_id=?', [$u['id']]);
    q('DELETE FROM employee_contacts WHERE user_id=?', [$u['id']]);

    // Save additional mobile numbers and email addresses
    foreach (['mobile', 'email'] as $ct)
        foreach (array_slice($p[$ct] ?? [], 0, 5) as $cv) {
            $cv = trim($cv);

            if ($cv !== '' && strlen($cv) <= 190 && ($ct !== 'email' || filter_var($cv, FILTER_VALIDATE_EMAIL)))
                q(
                    'INSERT INTO employee_contacts(user_id,contact_type,contact_value) VALUES(?,?,?)',
                    [$u['id'], $ct, $cv]
                );
        }

    // Get selected skills and custom skills, then remove duplicates and empty values
    $chosen = array_unique(array_filter(array_map('trim', array_merge($p['skills'] ?? [], preg_split('/[,\n]+/', $p['custom_skills'] ?? '')))));

    // Save up to 40 employee skills
    foreach (array_slice($chosen, 0, 40) as $skill)
        if ($skill !== '')
            q('INSERT INTO employee_skills(user_id,skill) VALUES(?,?)', [$u['id'], substr($skill, 0, 80)]);

    // Save education, seminars, and certifications
    foreach ($p['rtype'] ?? [] as $i => $t)
        if (in_array($t, ['education', 'seminar', 'certification']) && trim($p['rtitle'][$i]))
            q(
                'INSERT INTO resume_items(user_id,type,title,detail,year) VALUES(?,?,?,?,?)',
                [$u['id'], $t, $p['rtitle'][$i], $p['rdetail'][$i], $p['ryear'][$i]]
            );

    // Handle document deletions and uploads
    $docTypes = employee_doc_types();
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $uploadDir = __DIR__ . '/uploads/documents/' . (int)$u['id'];

    foreach ($docTypes as $docKey => $docLabel) {
        if (!empty($p['remove_doc'][$docKey])) {
            $oldDoc = q('SELECT file_path FROM employee_documents WHERE user_id=? AND doc_type=?', [$u['id'], $docKey])->fetch();
            if ($oldDoc) {
                $oldFull = __DIR__ . '/' . ltrim($oldDoc['file_path'], '/');
                if (is_file($oldFull)) @unlink($oldFull);
                q('DELETE FROM employee_documents WHERE user_id=? AND doc_type=?', [$u['id'], $docKey]);
            }
        }

        if (!empty($_FILES['docs']['name'][$docKey]) && ($_FILES['docs']['error'][$docKey] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = $_FILES['docs']['tmp_name'][$docKey];
            $orig = basename((string)$_FILES['docs']['name'][$docKey]);
            $size = (int)($_FILES['docs']['size'][$docKey] ?? 0);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

            if ($size > 0 && $size <= 5 * 1024 * 1024 && in_array($ext, $allowedExts, true)) {
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                // Remove old file if extension changed
                $oldDoc = q('SELECT file_path FROM employee_documents WHERE user_id=? AND doc_type=?', [$u['id'], $docKey])->fetch();
                if ($oldDoc) {
                    $oldFull = __DIR__ . '/' . ltrim($oldDoc['file_path'], '/');
                    if (is_file($oldFull)) @unlink($oldFull);
                }

                $relPath = 'uploads/documents/' . (int)$u['id'] . '/' . $docKey . '.' . $ext;
                $destPath = __DIR__ . '/' . $relPath;
                if (move_uploaded_file($tmp, $destPath)) {
                    q(
                        'REPLACE INTO employee_documents(user_id, doc_type, file_path, original_name) VALUES(?,?,?,?)',
                        [$u['id'], $docKey, $relPath, substr($orig, 0, 255)]
                    );
                }
            }
        }
    }

    audit_log('Profile Updated', 'Updated profile, resume, or required documents');

    // Redirect to the profile page after saving
    header('Location: profile.php?saved=1');
    exit;
}

// Retrieve employee profile information or set empty default values
$pr = q('SELECT * FROM employee_profiles WHERE user_id=?', [$u['id']])->fetch() ?: array_fill_keys(['first_name', 'middle_name', 'last_name', 'age', 'dob', 'birth_place'], '');

// Initialize resume categories
$items = ['education' => [], 'seminar' => [], 'certification' => []];

// Retrieve existing resume items from the database
foreach (q('SELECT * FROM resume_items WHERE user_id=?', [$u['id']]) as $r)
    $items[$r['type']][] = $r;

// Retrieve employee skills
$skills = array_column(q('SELECT skill FROM employee_skills WHERE user_id=? ORDER BY skill', [$u['id']])->fetchAll(), 'skill');

// Initialize and retrieve additional contact information
$contacts = ['mobile' => [], 'email' => []];

foreach (q('SELECT contact_type,contact_value FROM employee_contacts WHERE user_id=? ORDER BY id', [$u['id']]) as $c)
    $contacts[$c['contact_type']][] = $c['contact_value'];

// Retrieve uploaded employee documents
$docTypes = employee_doc_types();
$uploadedDocs = [];
foreach (q('SELECT doc_type, original_name, uploaded_at FROM employee_documents WHERE user_id=?', [$u['id']]) as $d) {
    $uploadedDocs[$d['doc_type']] = $d;
}

// Labels for resume categories
$labels = [
    'education' => 'Educational background',
    'seminar' => 'Seminars / workshops',
    'certification' => 'Certifications'
];

// Guidance and placeholders for each resume category
$guidance = [
    'education' => [
        'hint' => 'List your school, college, or university. Include your degree, course, strand, or program and your graduation year (or expected graduation year).',
        'title' => 'School / college / university',
        'detail' => 'Degree, course, strand, or program',
        'year' => 'Graduation year'
    ],
    'seminar' => [
        'hint' => 'Add relevant seminars, workshops, or training you attended. Include the organizer and a short description of what you learned.',
        'title' => 'Seminar / workshop / training title',
        'detail' => 'Organizer and topics or skills learned',
        'year' => 'Year attended'
    ],
    'certification' => [
        'hint' => 'List certificates, professional licenses, or qualifications you have earned. Include the issuing organization and, if applicable, the validity or expiration date.',
        'title' => 'Certificate / license name',
        'detail' => 'Issuing organization (and validity, if applicable)',
        'year' => 'Year earned'
    ]
];

// Set the page title and include the header
$title = 'My profile';
require 'includes/header.php';
?>

<!-- Employee profile form -->
<form method="post" enctype="multipart/form-data" class="card glass">

    <h1>My profile</h1>

    <!-- Display confirmation message after saving -->
    <?php if (isset($_GET['saved'])) echo '<p>Saved.</p>'; ?>

    <!-- Personal information fields -->
    <div class="grid3">
        <label>
            First name
            <input name="first_name" value="<?=e($pr['first_name'])?>" required>
        </label>

        <label>
            Middle name (optional)
            <input name="middle_name" value="<?=e($pr['middle_name'])?>">
        </label>

        <label>
            Last name
            <input name="last_name" value="<?=e($pr['last_name'])?>" required>
        </label>

        <label>
            Age
            <input type="number" name="age" min="15" max="100" value="<?=e($pr['age'])?>">
        </label>

        <label>
            Date of birth
            <input type="date" name="dob" value="<?=e($pr['dob'])?>">
        </label>

        <label>
            Birth place
            <input name="birth_place" value="<?=e($pr['birth_place'])?>">
        </label>
    </div>

    <!-- Additional contact information -->
    <h2>Additional contact information</h2>
    <p class="muted">
        Add extra ways employers can reach you. These are optional; your account email remains available too.
    </p>

    <!-- Mobile number fields -->
    <h3>Mobile number</h3>

    <div id="mobile-contacts">
        <?php foreach ($contacts['mobile'] as $v): ?>
            <div class="row">
                <input type="tel" name="mobile[]" value="<?=e($v)?>" placeholder="e.g. 0917 123 4567">
                <button type="button" class="btn sm" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="btn sm blue" data-add-contact="mobile">
        + Add mobile number
    </button>

    <!-- Personal email fields -->
    <h3>Personal email</h3>

    <div id="email-contacts">
        <?php foreach ($contacts['email'] as $v): ?>
            <div class="row">
                <input type="email" name="email[]" value="<?=e($v)?>" placeholder="e.g. yourname@example.com">
                <button type="button" class="btn sm" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="btn sm blue" data-add-contact="email">
        + Add personal email
    </button>

    <!-- Resume builder section -->
    <h2>Resume builder</h2>

    <!-- Display each resume category and its saved information -->
    <?php foreach ($labels as $t => $lb): ?>

        <h3><?=$lb?></h3>

        <p class="muted"><?=e($guidance[$t]['hint'])?></p>

        <div id="r-<?=$t?>">
            <?php foreach ($items[$t] as $r): ?>
                <div class="row">
                    <input type="hidden" name="rtype[]" value="<?=$t?>">

                    <input
                        name="rtitle[]"
                        aria-label="<?=$lb?> title"
                        placeholder="<?=e($guidance[$t]['title'])?>"
                        value="<?=e($r['title'])?>"
                    >

                    <input
                        name="rdetail[]"
                        aria-label="<?=$lb?> details"
                        placeholder="<?=e($guidance[$t]['detail'])?>"
                        value="<?=e($r['detail'])?>"
                    >

                    <input
                        name="ryear[]"
                        aria-label="<?=$lb?> year"
                        placeholder="<?=e($guidance[$t]['year'])?>"
                        value="<?=e($r['year'])?>"
                        style="max-width:125px"
                    >

                    <button type="button" class="btn sm" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn sm blue" data-add-resume="<?=$t?>">
            + Add
        </button>

    <?php endforeach; ?>

    <!-- Skills selection -->
    <h3>Skills</h3>

    <p class="muted">
        Choose the skills that honestly match your abilities. You can select several, then add any skills not listed.
    </p>

    <div class="skill-pool">
        <?php
        // List of available skills
        $skillPool = [
            'Communication',
            'Teamwork',
            'Problem-solving',
            'Time management',
            'Adaptability',
            'Leadership',
            'Critical thinking',
            'Customer service',
            'Attention to detail',
            'Creativity',
            'Decision-making',
            'Conflict resolution',
            'Microsoft Word',
            'Microsoft Excel',
            'Microsoft PowerPoint',
            'Google Workspace',
            'Data entry',
            'Computer literacy',
            'Technical support',
            'Troubleshooting',
            'Programming',
            'Web development',
            'Graphic design',
            'Social media management',
            'Digital marketing',
            'Research',
            'Project management',
            'Sales',
            'Cash handling',
            'Inventory management',
            'Driving',
            'Written communication',
            'Public speaking',
            'Organization',
            'Fast learner'
        ];

        // Display available skills and check previously selected ones
        foreach ($skillPool as $sk):
        ?>
            <label class="skill-option">
                <input
                    type="checkbox"
                    name="skills[]"
                    value="<?=e($sk)?>"
                    <?=in_array($sk, $skills, true) ? 'checked' : ''?>
                >
                <?=e($sk)?>
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Field for adding skills not included in the list -->
    <label>
        Other skills (separate with commas)
        <textarea name="custom_skills" rows="2" placeholder="e.g. Canva, basic photography, bookkeeping"><?=e(implode(', ', array_diff($skills, $skillPool)))?></textarea>
    </label>

    <!-- Required Government IDs and Clearances -->
    <h3>Required Documents &amp; IDs</h3>
    <p class="muted">
        Upload clear copies of your clearances and government IDs (PDF, JPG, PNG, or WEBP, max 5 MB each). Employers you apply to will be able to view these documents.
    </p>
    <div class="doc-grid">
        <?php foreach ($docTypes as $docKey => $docLabel):
            $hasDoc = isset($uploadedDocs[$docKey]);
        ?>
            <div class="doc-item">
                <div class="doc-item-head">
                    <strong><?=e($docLabel)?></strong>
                    <?php if ($hasDoc): ?>
                        <span class="doc-status-ok">&#10004; Uploaded</span>
                    <?php else: ?>
                        <span class="doc-status-miss">Not uploaded</span>
                    <?php endif; ?>
                </div>
                <?php if ($hasDoc): ?>
                    <div class="doc-meta">
                        <a href="view_doc.php?user=<?=(int)$u['id']?>&amp;doc=<?=e($docKey)?>" target="_blank" rel="noopener noreferrer">View (<?=e($uploadedDocs[$docKey]['original_name'])?>) &#8599;</a>
                        <label class="doc-remove">
                            <input type="checkbox" name="remove_doc[<?=e($docKey)?>]" value="1"> Remove
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" name="docs[<?=e($docKey)?>]" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Save profile or print resume -->
    <p>
        <button class="btn">Save profile</button>
        <button type="button" class="btn blue" onclick="print()">Print resume</button>
    </p>

</form>

<!-- Resume preview section -->
<section class="resume">

    <!-- Display employee name -->
    <h1><?=e(trim("{$pr['first_name']} {$pr['middle_name']} {$pr['last_name']}"))?></h1>

    <!-- Display basic personal information -->
    <p>
        Age <?=e($pr['age'])?> · Born <?=e($pr['dob'])?> in <?=e($pr['birth_place'])?>
    </p>

    <!-- Display additional contact information if available -->
    <?php if ($contacts['mobile'] || $contacts['email']): ?>
        <p>
            <?php foreach ($contacts['mobile'] as $v): ?>
                Mobile: <?=e($v)?> ·
            <?php endforeach; ?>

            <?php foreach ($contacts['email'] as $v): ?>
                Email: <?=e($v)?> ·
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <!-- Display employee skills -->
    <?php if ($skills): ?>
        <h3>Skills</h3>
        <p><?=e(implode(' · ', $skills))?></p>
    <?php endif; ?>

    <!-- Display resume items by category -->
    <?php foreach ($labels as $t => $lb) if ($items[$t]): ?>

        <h3><?=$lb?></h3>

        <ul>
            <?php foreach ($items[$t] as $r): ?>
                <li>
                    <b><?=e($r['title'])?></b>
                    <?=e($r['detail'])?>
                    <i><?=e($r['year'])?></i>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

</section>

<?php
// Include the footer
require 'includes/footer.php';
