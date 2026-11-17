<?php

require 'includes/config.php';

// Check if the user is an employer
$u = need('employer');
expire_stale_hired_offers();
$ok = '';
$err = '';

// Generate a CSRF token if one does not exist
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

// Employer-selectable application statuses (Offer Accepted is triggered by the applicant)
$st = [
    'For Review',
    'For Interview',
    'Interview Passed',
    'Not Selected',
    'Hired'
];

// Handle application status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate the CSRF token
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid request.');
    }

    // Get the application and verify that it belongs to this employer
    $a = q(
        'SELECT a.id, a.status, a.employee_id, a.interview_at, a.interview_link, a.offer_file, a.offer_original_name, a.offer_sent_at,
                j.title, COALESCE(ep.company, u.name) AS company_name,
                emp.name AS applicant_name, emp.email AS applicant_email
         FROM applications a
         JOIN jobs j ON j.id = a.job_id
         JOIN users u ON u.id = j.employer_id
         JOIN users emp ON emp.id = a.employee_id
         LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id
         WHERE a.id = ? AND j.employer_id = ?',
        [
            (int)($_POST['app'] ?? 0),
            $u['id']
        ]
    )->fetch();

    $action = (string)($_POST['action'] ?? 'status');

    // Handle sending a direct message to the applicant
    if ($a && $action === 'message') {
        $msgBody = trim((string)($_POST['message'] ?? ''));
        if ($msgBody !== '' && strlen($msgBody) <= 2000) {
            q(
                "INSERT INTO application_messages (application_id, sender_id, sender_role, message) VALUES (?, ?, 'employer', ?)",
                [$a['id'], $u['id'], $msgBody]
            );
            notify(
                $a['employee_id'],
                'New message from ' . $a['company_name'] . ' regarding "' . $a['title'] . '": ' . mb_strimwidth($msgBody, 0, 90, '…'),
                'inbox.php'
            );
            $ok = 'Message sent to the applicant.';
            audit_log('Message Sent', 'Messaged applicant for ' . $a['title']);
        }
    }

    // Check if the application exists and the submitted status is valid
    elseif ($a && in_array($_POST['status'] ?? '', $st, true)) {
        $newStatus = $_POST['status'];

        // Guard: Hired is only allowed after the applicant accepts the job offer
        if ($newStatus === 'Hired' && $a['status'] !== 'Offer Accepted') {
            $err = 'Please send a job offer under "Interview Passed" and wait for the applicant to accept the offer ("Offer Accepted") before marking as Hired.';
        } else {
            $interviewAt   = $a['interview_at'];
            $interviewLink = $a['interview_link'];
            $offerFile     = $a['offer_file'];
            $offerOrig     = $a['offer_original_name'];
            $offerSentAt   = $a['offer_sent_at'];

            if ($newStatus === 'For Interview') {
                $rawDate = trim((string)($_POST['interview_at'] ?? ''));
                if ($rawDate !== '') {
                    $ts = strtotime($rawDate);
                    if ($ts !== false) {
                        $interviewAt = date('Y-m-d H:i:s', $ts);
                    }
                }
                if (empty($interviewLink) || preg_match('#^https?://#i', $interviewLink)) {
                    $interviewLink = 'ABEKa-Interview-' . $a['id'] . '-' . bin2hex(random_bytes(4));
                }
            }

            if ($newStatus === 'Interview Passed' && !empty($_FILES['offer_file']['name']) && $_FILES['offer_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['offer_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                    $err = 'Job offer file must be a PDF, DOC, or DOCX file.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/offers';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $savedName = 'offer_' . $a['id'] . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['offer_file']['tmp_name'], $uploadDir . '/' . $savedName)) {
                        $offerFile   = 'uploads/offers/' . $savedName;
                        $offerOrig   = basename($_FILES['offer_file']['name']);
                        $offerSentAt = date('Y-m-d H:i:s');
                    } else {
                        $err = 'Could not save the uploaded job offer file.';
                    }
                }
            }

            if ($err === '') {
                q(
                    'UPDATE applications
                     SET status = ?, status_changed = NOW(),
                         interview_at = ?, interview_link = ?,
                         offer_file = ?, offer_original_name = ?, offer_sent_at = ?
                     WHERE id = ?',
                    [
                        $newStatus,
                        $interviewAt,
                        $interviewLink,
                        $offerFile,
                        $offerOrig,
                        $offerSentAt,
                        $a['id']
                    ]
                );

                $applicantName = $a['applicant_name'] ?: 'Applicant';
                $jobTitle      = $a['title'];
                $companyName   = $a['company_name'];

                if ($newStatus === 'For Interview') {
                    $whenStr = $interviewAt ? date('M j, Y g:i A', strtotime($interviewAt)) : 'to be confirmed';
                    $notifMsg = 'Interview scheduled for "' . $jobTitle . '" at ' . $companyName . ' on ' . $whenStr . '. Open your Inbox to join the ABEKa Video Call.';
                    abeka_send_mail(
                        $a['applicant_email'],
                        'ABEKa: Interview Scheduled for ' . $jobTitle,
                        "Hello {$applicantName},\n\n"
                        . "{$companyName} has scheduled your interview for \"{$jobTitle}\".\n"
                        . "Date & Time: {$whenStr}\n\n"
                        . "Log in to ABEKa and open your Inbox to join the built-in Video Call."
                    );
                } elseif ($newStatus === 'Interview Passed') {
                    if ($offerFile) {
                        $notifMsg = 'Great news! You passed the interview for "' . $jobTitle . '" at ' . $companyName . '. A job offer letter has been uploaded—please review and accept it in your Inbox.';
                    } else {
                        $notifMsg = 'Great news! You passed the interview for "' . $jobTitle . '" at ' . $companyName . '. Please check your Inbox for your upcoming job offer.';
                    }
                    abeka_send_mail(
                        $a['applicant_email'],
                        'ABEKa: Interview Passed — ' . $jobTitle,
                        "Hello {$applicantName},\n\n"
                        . "{$notifMsg}\n\n"
                        . "Log in to ABEKa and open your Inbox to view and accept your offer."
                    );
                } elseif ($newStatus === 'Not Selected') {
                    $notifMsg = "Thank you for taking the time to apply and interview for \"{$jobTitle}\" at {$companyName}. While we won't be moving forward with your application at this time, we truly appreciate your effort and encourage you to explore other opportunities on ABEKa. You may reapply to this company after 30 days.";
                    abeka_send_mail(
                        $a['applicant_email'],
                        'ABEKa Application Update: ' . $jobTitle,
                        "Hello {$applicantName},\n\n{$notifMsg}"
                    );
                } elseif ($newStatus === 'Hired') {
                    $notifMsg = "Congratulations, {$applicantName}! You are officially Hired for \"{$jobTitle}\" at {$companyName}. Check your ABEKa Inbox or messages for onboarding details.";
                    abeka_send_mail(
                        $a['applicant_email'],
                        'Congratulations! You are Hired — ' . $jobTitle,
                        "Congratulations {$applicantName}!\n\n"
                        . "{$companyName} has officially marked your application for \"{$jobTitle}\" as Hired.\n"
                        . "Please log in to ABEKa to view your messages and onboarding instructions."
                    );
                } else {
                    $notifMsg = 'Update on your application for "' . $jobTitle . '": ' . $newStatus . '.';
                }

                notify($a['employee_id'], $notifMsg, 'inbox.php');
                $ok = 'Status updated to "' . $newStatus . '" and the applicant was notified.';
                audit_log('Application Status Updated', 'Set ' . $a['title'] . ' status to ' . $newStatus);
            }
        }
    }
}

// Get the selected job ID, if any
$jid = (int)($_GET['job'] ?? 0);

// Get all jobs posted by the employer
$jobs = q(
    'SELECT id, title
     FROM jobs
     WHERE employer_id = ?
     ORDER BY title',
    [$u['id']]
)->fetchAll();

// Get applicants, including their personal and profile information
$rows = q(
    'SELECT a.id, a.status, a.created, a.employee_id,
            a.interview_at, a.interview_link, a.offer_file, a.offer_original_name, a.offer_sent_at, a.offer_accepted_at,
            j.title jt, us.name, us.email,
            ep.age, ep.dob, ep.birth_place
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     JOIN users us ON us.id = a.employee_id
     LEFT JOIN employee_profiles ep ON ep.user_id = a.employee_id
     WHERE j.employer_id = ?' .
    ($jid ? ' AND j.id = ?' : '') .
    ' ORDER BY a.created DESC',
    $jid ? [$u['id'], $jid] : [$u['id']]
)->fetchAll();

// Initialize arrays for applicant resume information
$ri = [];
$rs = [];
$rc = [];
$rd = [];
$rm = [];

// Get resume information for all applicants
if ($rows) {
    $ids = array_values(array_unique(array_column($rows, 'employee_id')));

    foreach (
        q(
            'SELECT * FROM resume_items WHERE user_id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        ) as $r
    ) {
        $ri[$r['user_id']][$r['type']][] = $r;
    }

    foreach (
        q(
            'SELECT * FROM employee_skills WHERE user_id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY skill',
            $ids
        ) as $x
    ) {
        $rs[$x['user_id']][] = $x['skill'];
    }

    foreach (
        q(
            'SELECT * FROM employee_contacts WHERE user_id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id',
            $ids
        ) as $x
    ) {
        $rc[$x['user_id']][$x['contact_type']][] = $x['contact_value'];
    }

    foreach (
        q(
            'SELECT user_id, doc_type, original_name, uploaded_at FROM employee_documents WHERE user_id IN (' .
            implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        ) as $x
    ) {
        $rd[$x['user_id']][$x['doc_type']] = $x;
    }

    $appIds = array_values(array_unique(array_column($rows, 'id')));
    if ($appIds) {
        foreach (
            q(
                'SELECT m.*, u.name AS sender_name
                 FROM application_messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.application_id IN (' .
                implode(',', array_fill(0, count($appIds), '?')) .
                ') ORDER BY m.created ASC',
                $appIds
            ) as $m
        ) {
            $rm[$m['application_id']][] = $m;
        }
    }
}

$docTypes = employee_doc_types();

$lb = [
    'education' => 'Education',
    'seminar' => 'Seminars / workshops',
    'certification' => 'Certifications'
];

$title = 'Applicants';
require 'includes/header.php';
?>

<!-- Applicants page header, job/status filters, search, and minimize -->
<section class="card glass">
    <div class="dash-head-row">
        <h1 style="margin:0;">Applicants</h1>
        <button type="button" class="btn sm" id="abeka-toggle-applicants-btn" title="Minimize" aria-label="Minimize" onclick="abekaToggleApplicants()">-</button>
    </div>

    <?php if ($ok): ?><p class="alert-admin-success"><?=e($ok)?></p><?php endif; ?>
    <?php if ($err): ?><p class="alert-admin-error"><?=e($err)?></p><?php endif; ?>

    <p>
        <a class="btn sm <?=$jid ? 'blue' : ''?>" href="applicants.php">
            All jobs
        </a>

        <?php foreach ($jobs as $j): ?>
            <a
                class="btn sm <?=$jid == $j['id'] ? '' : 'blue'?>"
                href="applicants.php?job=<?=$j['id']?>"
            >
                <?=e($j['title'])?>
            </a>
        <?php endforeach; ?>

        <a class="btn sm blue" href="employer.php">Back to vacancies</a>
    </p>

    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px;">
        <input
            type="search"
            id="abeka-app-search"
            placeholder="Search applicant name, email, or job..."
            oninput="abekaFilterApplicants()"
            style="flex:1; min-width:220px; margin:0;"
        >
        <select id="abeka-app-status-filter" onchange="abekaFilterApplicants()" style="margin:0; min-width:170px;">
            <option value="">All Statuses</option>
            <option value="For Review">For Review</option>
            <option value="For Interview">For Interview</option>
            <option value="Interview Passed">Interview Passed</option>
            <option value="Offer Accepted">Offer Accepted</option>
            <option value="Hired">Hired</option>
            <option value="Not Selected">Not Selected</option>
        </select>
    </div>
</section>

<div id="abeka-applicants-list">
<!-- Display each applicant -->
<?php foreach ($rows as $r): ?>
    <section class="card glass abeka-applicant-card" data-status="<?=e($r['status'])?>">

        <div class="foldhead">

            <!-- Applicant name, email, and job applied for -->
            <div>
                <b><?=e($r['name'])?></b>
                <span class="muted"><?=e($r['email'])?></span>
                <br>

                Applied for
                <b><?=e($r['jt'])?></b>

                <span class="muted">
                    on <?=e($r['created'])?>
                </span>

                <?php if ($r['status'] === 'For Interview' && (!empty($r['interview_at']) || !empty($r['interview_link']))): ?>
                    <div style="margin-top:6px;">
                        <?php if (!empty($r['interview_at'])): ?>
                            <small><b>Interview:</b> <?=e(date('M j, Y g:i A', strtotime($r['interview_at'])))?></small>
                        <?php endif; ?>
                        <?php if (!empty($r['interview_link'])): ?>
                            &nbsp;<a class="btn sm blue" href="interview_call.php?app=<?=(int)$r['id']?>">Join Video Call &#8599;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($r['offer_file'])): ?>
                    <div style="margin-top:6px;">
                        <small><b>Job Offer:</b></small>
                        <a class="btn sm blue" href="<?=e($r['offer_file'])?>" target="_blank" rel="noopener noreferrer"><?=e($r['offer_original_name'] ?: 'View Offer Letter')?> &#8599;</a>
                        <?php if ($r['status'] === 'Offer Accepted' && !empty($r['offer_accepted_at'])): ?>
                            <small class="muted">(Accepted on <?=e(date('M j, Y g:i A', strtotime($r['offer_accepted_at'])))?>)</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Form for updating the applicant's status -->
            <form
                method="post"
                enctype="multipart/form-data"
                action="applicants.php<?=$jid ? '?job=' . $jid : ''?>"
                style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;"
            >
                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                <input type="hidden" name="app" value="<?=$r['id']?>">

                <div class="row" style="gap:6px;">
                    <select name="status" style="margin:0" onchange="abekaToggleStatusFields(this)">
                        <?php foreach ($st as $s): ?>
                            <option value="<?=e($s)?>" <?=($s === $r['status'] ? 'selected' : '')?>><?=e($s)?></option>
                        <?php endforeach; ?>
                        <?php if ($r['status'] === 'Offer Accepted'): ?>
                            <option value="Offer Accepted" selected disabled>Offer Accepted (by Applicant)</option>
                        <?php endif; ?>
                    </select>

                    <button class="btn sm" type="submit">Update</button>
                </div>

                <!-- Calendar date + time & video call link (shown when For Interview is selected) -->
                <div class="abeka-interview-fields" style="display:<?=$r['status'] === 'For Interview' ? 'flex' : 'none'?>; flex-wrap:wrap; gap:6px; justify-content:flex-end;">
                    <input
                        type="datetime-local"
                        name="interview_at"
                        value="<?=!empty($r['interview_at']) ? date('Y-m-d\TH:i', strtotime($r['interview_at'])) : ''?>"
                        title="Interview Date and Time"
                        style="margin:0"
                    >

                </div>

                <!-- Job offer letter upload (shown when Interview Passed is selected) -->
                <div class="abeka-offer-fields" style="display:<?=$r['status'] === 'Interview Passed' ? 'flex' : 'none'?>; flex-wrap:wrap; gap:6px; align-items:center; justify-content:flex-end;">
                    <small class="muted">Job Offer Letter (PDF/DOCX):</small>
                    <input type="file" name="offer_file" accept=".pdf,.doc,.docx" style="margin:0; max-width:220px;">
                </div>
            </form>

        </div>

        <!-- Expandable applicant details and resume -->
        <details>
            <?php
                $docCount = count($rd[$r['employee_id']] ?? []);
                $docTotal = count($docTypes);
            ?>
            <summary>View applicant details, resume &amp; documents (<?=$docCount?>/<?=$docTotal?>)</summary>

            <!-- Personal information -->
            <p>
                Age: <?=e($r['age'] ?? '-')?>
                · Born: <?=e($r['dob'] ?? '-')?>
                in <?=e($r['birth_place'] ?? '-')?>
            </p>

            <!-- Additional contact information -->
            <?php if (!empty($rc[$r['employee_id']])): ?>
                <b>Additional contact information</b>

                <p>
                    <?php foreach ($rc[$r['employee_id']]['mobile'] ?? [] as $v): ?>
                        Mobile: <?=e($v)?> ·
                    <?php endforeach; ?>

                    <?php foreach ($rc[$r['employee_id']]['email'] ?? [] as $v): ?>
                        Personal email: <?=e($v)?> ·
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <!-- Applicant skills -->
            <?php if (!empty($rs[$r['employee_id']])): ?>
                <b>Skills</b>

                <p>
                    <?=e(implode(' · ', $rs[$r['employee_id']]))?>
                </p>
            <?php endif; ?>

            <!-- Education, seminars, and certifications -->
            <?php foreach ($lb as $t => $l) if (!empty($ri[$r['employee_id']][$t])): ?>
                <b><?=$l?></b>

                <ul>
                    <?php foreach ($ri[$r['employee_id']][$t] as $x): ?>
                        <li>
                            <?=e($x['title'])?>
                            <?=e($x['detail'])?>
                            <i><?=e($x['year'])?></i>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Required Documents Checklist -->
            <b>Required Documents</b>
            <ul class="doc-check-list">
                <?php foreach ($docTypes as $docKey => $docLabel):
                    $hasDoc = !empty($rd[$r['employee_id']][$docKey]);
                ?>
                    <li class="<?=$hasDoc ? 'doc-check-yes' : 'doc-check-no'?>">
                        <?php if ($hasDoc): ?>
                            <span class="doc-tick" aria-label="Uploaded">&#10004;</span>
                            <strong><?=e($docLabel)?></strong>
                            <a class="btn sm blue" href="view_doc.php?user=<?=(int)$r['employee_id']?>&amp;doc=<?=e($docKey)?>" target="_blank" rel="noopener noreferrer">View file &#8599;</a>
                        <?php else: ?>
                            <span class="doc-miss" aria-label="Not uploaded">&#9675;</span>
                            <span><?=e($docLabel)?></span>
                            <small class="muted">Not uploaded</small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            if (empty($ri[$r['employee_id']]) && empty($rs[$r['employee_id']])) {
                echo '<p class="muted">This applicant has not filled in a resume yet.</p>';
            }
            ?>

            <!-- Direct Messages with Applicant -->
            <b>Messages with applicant</b>
            <div class="msg-thread">
                <?php if (!empty($rm[$r['id']])): ?>
                    <?php foreach ($rm[$r['id']] as $m): ?>
                        <div class="msg-bubble <?=$m['sender_role'] === 'employer' ? 'msg-out' : 'msg-in'?>">
                            <div class="msg-meta">
                                <strong><?=e($m['sender_name'])?></strong>
                                <small class="muted"><?=e($m['created'])?></small>
                            </div>
                            <p><?=nl2br(e($m['message']))?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No messages yet. Send onboarding details or follow-up instructions below.</p>
                <?php endif; ?>
            </div>

            <form method="post" class="msg-form" action="applicants.php<?=$jid ? '?job=' . $jid : ''?>">
                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                <input type="hidden" name="action" value="message">
                <input type="hidden" name="app" value="<?=$r['id']?>">
                <textarea name="message" rows="2" maxlength="2000" required placeholder="Write a message to <?=e($r['name'])?> (e.g., start date, reporting time, requirements)..."></textarea>
                <button class="btn sm" type="submit">Send Message</button>
            </form>

        </details>

    </section>
<?php endforeach; ?>

<section id="abeka-applicants-empty" class="card glass" style="display:none;">
    <p class="muted">No matching applicants found.</p>
</section>
<?php
if (!$rows) {
    echo '<section class="card glass"><p class="muted">No applicants yet.</p></section>';
}
?>
</div>

<script>
// Toggle minimize/expand for the Applicants list
function abekaToggleApplicants() {
    const list = document.getElementById('abeka-applicants-list');
    const btn = document.getElementById('abeka-toggle-applicants-btn');
    if (!list || !btn) return;
    const isHidden = list.style.display === 'none';
    list.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? '-' : '+';
    btn.title = isHidden ? 'Minimize' : 'Expand';
    btn.setAttribute('aria-label', btn.title);
}

// Live filter applicants by search text and status
function abekaFilterApplicants() {
    const query = (document.getElementById('abeka-app-search')?.value || '').trim().toLowerCase();
    const status = document.getElementById('abeka-app-status-filter')?.value || '';
    const cards = document.querySelectorAll('#abeka-applicants-list .abeka-applicant-card');
    const emptyBox = document.getElementById('abeka-applicants-empty');
    let visible = 0;

    cards.forEach(function (card) {
        const cardStatus = card.getAttribute('data-status') || '';
        const textMatch = query === '' || card.textContent.toLowerCase().includes(query);
        const statusMatch = status === '' || cardStatus === status;
        const show = textMatch && statusMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    if (emptyBox) {
        emptyBox.style.display = (cards.length > 0 && visible === 0) ? 'block' : 'none';
    }
}

function abekaToggleStatusFields(selectEl) {
    const form = selectEl.closest('form');
    const interviewBox = form.querySelector('.abeka-interview-fields');
    const offerBox = form.querySelector('.abeka-offer-fields');
    if (interviewBox) {
        interviewBox.style.display = (selectEl.value === 'For Interview') ? 'flex' : 'none';
    }
    if (offerBox) {
        offerBox.style.display = (selectEl.value === 'Interview Passed') ? 'flex' : 'none';
    }
}
</script>

<?php require 'includes/footer.php'; ?>
