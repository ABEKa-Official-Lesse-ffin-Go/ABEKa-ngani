<?php

require 'includes/config.php';

// Make sure the user is logged in as an employee.
$u = need('employee');
expire_stale_hired_offers();

// Generate a CSRF token if one does not exist.
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

// Handle employee reply to an employer message.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'reply' &&
    hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')
) {
    $appRow = q(
        'SELECT a.id, j.employer_id, j.title
         FROM applications a
         JOIN jobs j ON j.id = a.job_id
         WHERE a.id = ? AND a.employee_id = ?',
        [(int)($_POST['app'] ?? 0), $u['id']]
    )->fetch();

    $replyBody = trim((string)($_POST['message'] ?? ''));
    if ($appRow && $replyBody !== '' && strlen($replyBody) <= 2000) {
        q(
            "INSERT INTO application_messages (application_id, sender_id, sender_role, message) VALUES (?, ?, 'employee', ?)",
            [$appRow['id'], $u['id'], $replyBody]
        );
        notify(
            $appRow['employer_id'],
            'Reply from ' . $u['name'] . ' regarding "' . $appRow['title'] . '": ' . mb_strimwidth($replyBody, 0, 90, '…'),
            'applicants.php'
        );
    }

    header('Location: inbox.php');
    exit;
}

// Handle accepting a Hired job offer within the 14-day window.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'accept_offer' &&
    hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')
) {
    $offerRow = q(
        "SELECT a.id, j.employer_id, j.title
         FROM applications a
         JOIN jobs j ON j.id = a.job_id
         WHERE a.id = ? AND a.employee_id = ? AND a.status = 'Interview Passed'",
        [(int)($_POST['app'] ?? 0), $u['id']]
    )->fetch();

    if ($offerRow) {
        q("UPDATE applications SET status = 'Offer Accepted', offer_accepted_at = NOW(), status_changed = NOW() WHERE id = ?", [$offerRow['id']]);
        notify($u['id'], 'You accepted the job offer for "' . $offerRow['title'] . '".', 'inbox.php');
        notify($offerRow['employer_id'], $u['name'] . ' accepted your job offer for "' . $offerRow['title'] . '"!', 'applicants.php');
        audit_log('Offer Accepted', 'Accepted hired offer for ' . $offerRow['title']);
    }

    header('Location: inbox.php');
    exit;
}

// Handle hiding completed applications.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'hide' &&
    hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')
) {
    q(
        "UPDATE applications
         SET hidden = 1
         WHERE id = ?
         AND employee_id = ?
         AND status IN ('Hired', 'Not Selected')",
        [(int) ($_POST['app'] ?? 0), $u['id']]
    );

    header('Location: inbox.php');
    exit;
}

$title = 'Inbox';

require 'includes/header.php';

// Get all notifications for the employee.
$ns = q(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created DESC',
    [$u['id']]
)->fetchAll();

// Mark all notifications as read.
q(
    'UPDATE notifications SET is_read = 1 WHERE user_id = ?',
    [$u['id']]
);

// Get the employee's applications that are not hidden.
$apps = q(
    'SELECT a.id, a.job_id, a.status, a.status_changed, a.created, a.interview_at, a.interview_link, a.offer_file, a.offer_original_name, j.title,
            COALESCE(ep.company, emp.name) AS company_name
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     JOIN users emp ON emp.id = j.employer_id
     LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id
     WHERE a.employee_id = ?
     AND a.hidden = 0
     ORDER BY a.created DESC',
    [$u['id']]
)->fetchAll();

// Get messages for the employee's applications.
$appMsgs = [];
$appIds = array_values(array_unique(array_column($apps, 'id')));
if ($appIds) {
    foreach (
        q(
            'SELECT m.*, u.name AS sender_name
             FROM application_messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.application_id IN (' .
            implode(',', array_fill(0, count($appIds), '?')) .
            ')
             ORDER BY m.created ASC',
            $appIds
        ) as $m
    ) {
        $appMsgs[$m['application_id']][] = $m;
    }
}
?>

<!-- Confirmation center / Notifications (Minimize & Search) -->
<section class="card glass">
    <div class="dash-head-row">
        <h1 style="margin:0;">Confirmation center</h1>
        <button type="button" class="btn sm" id="abeka-toggle-cc-btn" title="Minimize" aria-label="Minimize" onclick="abekaToggleConfirmationCenter()">-</button>
    </div>

    <div id="abeka-cc-body" style="margin-top:10px;">
        <div style="margin-bottom:10px;">
            <input
                type="search"
                id="abeka-cc-search"
                placeholder="Search notifications (e.g., job title, company, status, date)..."
                oninput="abekaFilterNotifications(this.value)"
                style="width:100%; margin:0;"
            >
        </div>

        <div id="abeka-cc-list">
            <?php foreach ($ns as $n): ?>
                <div class="card abeka-cc-item <?= $n['is_read'] ? '' : 'unread' ?>">
                    <?= e($n['message']) ?>

                    <div class="muted">
                        <?= e($n['created']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p id="abeka-cc-empty" class="muted" style="display:none;">No matching notifications found.</p>
        <?php if (!$ns) echo '<p class="muted">Nothing yet. Apply to a job to get a receipt here.</p>'; ?>
    </div>
</section>

<script>
// Toggle minimize/expand for Confirmation center
function abekaToggleConfirmationCenter() {
    const body = document.getElementById('abeka-cc-body');
    const btn = document.getElementById('abeka-toggle-cc-btn');
    if (!body || !btn) return;
    const isHidden = body.style.display === 'none';
    body.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? '-' : '+';
    btn.title = isHidden ? 'Minimize' : 'Expand';
    btn.setAttribute('aria-label', btn.title);
}

// Live filter Confirmation center notifications
function abekaFilterNotifications(query) {
    const term = (query || '').trim().toLowerCase();
    const items = document.querySelectorAll('#abeka-cc-list .abeka-cc-item');
    const emptyMsg = document.getElementById('abeka-cc-empty');
    let visible = 0;

    items.forEach(function (item) {
        const match = term === '' || item.textContent.toLowerCase().includes(term);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    if (emptyMsg) {
        emptyMsg.style.display = (items.length > 0 && visible === 0) ? 'block' : 'none';
    }
}
</script>

<!-- Employee applications -->
<section class="card glass">
    <h2>My applications</h2>

    <table>
        <tr>
            <th>Job</th>
            <th>Status</th>
            <th>Date</th>
            <th></th>
        </tr>

        <?php foreach ($apps as $a): ?>
            <tr>
                <td><?= e($a['title']) ?></td>
                <td><?= e($a['status']) ?></td>
                <td><?= e($a['created']) ?></td>

                <td>
                    <!-- Reapply option for applicants who were not selected -->
                    <?php if ($a['status'] === 'Not Selected'): ?>
                        <?php
                        // Calculate the date when the employee can reapply.
                        $t = strtotime(($a['status_changed'] ?? null) ?: $a['created']) + 30 * 86400;

                        echo $t > time()
                            ? '<span class="muted">Reapply after ' . date('M j, Y', $t) . '</span>'
                            : '<a class="btn sm" href="job.php?id=' . $a['job_id'] . '">Reapply</a>';
                        ?>

                    <!-- Interview schedule & video call link for For Interview applications -->
                    <?php elseif ($a['status'] === 'For Interview'): ?>
                        <?php if (!empty($a['interview_at'])): ?>
                            <small><b><?= date('M j, Y g:i A', strtotime($a['interview_at'])) ?></b></small><br>
                        <?php endif; ?>
                        <a class="btn sm blue" href="interview_call.php?app=<?= (int)$a['id'] ?>">Join Video Call</a>
                        <form
                            method="post"
                            action="job.php?id=<?= $a['job_id'] ?>"
                            style="display:inline"
                            onsubmit="return confirm('Withdraw this application?')"
                        >
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="action" value="withdraw">
                            <button class="btn sm danger">Withdraw</button>
                        </form>

                    <!-- Job offer letter & Accept Offer button for Interview Passed applications -->
                    <?php elseif ($a['status'] === 'Interview Passed'): ?>
                        <?php if (!empty($a['offer_file'])): ?>
                            <a class="btn sm blue" href="<?= e($a['offer_file']) ?>" target="_blank" rel="noopener noreferrer">View Offer Letter &#8599;</a>
                        <?php endif; ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="accept_offer">
                            <input type="hidden" name="app" value="<?= $a['id'] ?>">
                            <button class="btn sm" type="submit">Accept Offer</button>
                        </form>

                    <!-- Withdraw option for applications that are still active -->
                    <?php elseif (!in_array($a['status'], ['Offer Accepted', 'Expired'], true)): ?>
                        <form
                            method="post"
                            action="job.php?id=<?= $a['job_id'] ?>"
                            onsubmit="return confirm('Withdraw this application?')"
                        >
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf'] ?? '') ?>">
                            <input type="hidden" name="action" value="withdraw">

                            <button class="btn sm danger">Withdraw</button>
                        </form>
                    <?php endif; ?>

                    <!-- Remove option for completed applications -->
                    <?php if (in_array($a['status'], ['Hired', 'Offer Accepted', 'Expired', 'Not Selected'], true)): ?>
                        <form
                            method="post"
                            style="display:inline"
                            onsubmit="return confirm('Remove this from your list? The employer keeps their record, and the 30-day reapply wait still applies.')"
                        >
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="hide">
                            <input type="hidden" name="app" value="<?= $a['id'] ?>">

                            <button class="btn sm danger">Remove</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>

<!-- Employer Messages (Minimize & Search) -->
<section class="card glass" id="messages">
    <div class="dash-head-row">
        <h2 style="margin:0;">Messages from Employers</h2>
        <button type="button" class="btn sm" id="abeka-toggle-msgs-btn" title="Minimize" aria-label="Minimize" onclick="abekaToggleEmployerMessages()">-</button>
    </div>

    <div id="abeka-msgs-body" style="margin-top:10px;">
        <?php
        $hasAnyMsg = false;
        foreach ($apps as $a) {
            if (!empty($appMsgs[$a['id']])) {
                $hasAnyMsg = true;
                break;
            }
        }
        ?>
        <div style="margin-bottom:10px;">
            <input
                type="search"
                id="abeka-msgs-search"
                placeholder="Search employer messages (e.g., job title, company, message)..."
                oninput="abekaFilterEmployerMessages(this.value)"
                style="width:100%; margin:0;"
            >
        </div>

        <div id="abeka-msgs-list">
            <?php foreach ($apps as $a):
                if (empty($appMsgs[$a['id']])) continue;
            ?>
                <div class="card abeka-msg-item">
                    <p>
                        <strong><?= e($a['title']) ?></strong>
                        <span class="muted">· <?= e($a['company_name']) ?> (<?= e($a['status']) ?>)</span>
                    </p>
                    <div class="msg-thread">
                        <?php foreach ($appMsgs[$a['id']] as $m): ?>
                            <div class="msg-bubble <?= $m['sender_role'] === 'employee' ? 'msg-out' : 'msg-in' ?>">
                                <div class="msg-meta">
                                    <strong><?= e($m['sender_name']) ?></strong>
                                    <small class="muted"><?= e($m['created']) ?></small>
                                </div>
                                <p><?= nl2br(e($m['message'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="msg-form">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="app" value="<?= $a['id'] ?>">
                        <textarea name="message" rows="2" maxlength="2000" required placeholder="Reply to <?= e($a['company_name']) ?>..."></textarea>
                        <button class="btn sm" type="submit">Send Reply</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <p id="abeka-msgs-empty" class="muted" style="display:none;">No matching messages found.</p>
        <?php if (!$hasAnyMsg): ?>
            <p class="muted">No messages from employers yet.</p>
        <?php endif; ?>
    </div>
</section>

<script>
// Toggle minimize/expand for Messages from Employers
function abekaToggleEmployerMessages() {
    const body = document.getElementById('abeka-msgs-body');
    const btn = document.getElementById('abeka-toggle-msgs-btn');
    if (!body || !btn) return;
    const isHidden = body.style.display === 'none';
    body.style.display = isHidden ? 'block' : 'none';
    btn.textContent = isHidden ? '-' : '+';
    btn.title = isHidden ? 'Minimize' : 'Expand';
    btn.setAttribute('aria-label', btn.title);
}

// Live filter Messages from Employers
function abekaFilterEmployerMessages(query) {
    const term = (query || '').trim().toLowerCase();
    const items = document.querySelectorAll('#abeka-msgs-list .abeka-msg-item');
    const emptyMsg = document.getElementById('abeka-msgs-empty');
    let visible = 0;

    items.forEach(function (item) {
        const match = term === '' || item.textContent.toLowerCase().includes(term);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    if (emptyMsg) {
        emptyMsg.style.display = (items.length > 0 && visible === 0) ? 'block' : 'none';
    }
}
</script>

<?php require 'includes/footer.php'; ?>
