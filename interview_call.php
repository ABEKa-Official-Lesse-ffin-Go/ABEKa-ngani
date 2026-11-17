<?php
// Built-in Interview Video Call page: embeds a Jitsi Meet room for the employer and applicant.
require 'includes/config.php';

// Verify the user is logged in and fetch the application details
$u = need();
$appId = (int)($_GET['app'] ?? 0);

$a = q(
    'SELECT a.id, a.status, a.employee_id, a.interview_at, a.interview_link,
            j.title AS job_title, j.employer_id,
            COALESCE(ep.company, emp.name) AS company_name,
            app_u.name AS applicant_name
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     JOIN users emp ON emp.id = j.employer_id
     JOIN users app_u ON app_u.id = a.employee_id
     LEFT JOIN employer_profiles ep ON ep.user_id = j.employer_id
     WHERE a.id = ?',
    [$appId]
)->fetch();

// Allow access only to the assigned applicant or the job's employer
if (!$a || ((int)$a['employee_id'] !== (int)$u['id'] && (int)$a['employer_id'] !== (int)$u['id'])) {
    die('Interview room not found or access denied.');
}

// Generate or normalize the Jitsi room identifier
$room = trim((string)($a['interview_link'] ?? ''));
if ($room === '' || preg_match('#^https?://#i', $room)) {
    if (preg_match('#meet\.jit\.si/([^/?\#]+)#i', $room, $m)) {
        $room = $m[1];
    } else {
        $room = 'ABEKa-Interview-' . $a['id'] . '-' . substr(hash('sha256', $a['id'] . '|' . $a['employee_id'] . '|' . $a['employer_id']), 0, 10);
    }
    q('UPDATE applications SET interview_link = ? WHERE id = ?', [$room, $a['id']]);
}

// Build the embed URL and return destination based on user role
$displayName = rawurlencode($u['name'] ?? 'ABEKa User');
$embedUrl = 'https://meet.jit.si/' . rawurlencode($room) . '#userInfo.displayName=%22' . $displayName . '%22&config.prejoinPageEnabled=false';
$backUrl = ((int)$a['employer_id'] === (int)$u['id']) ? 'applicants.php' : 'inbox.php';

$title = 'Interview Call — ' . $a['job_title'];
require 'includes/header.php';
?>

<!-- Interview Video Call Container -->
<section class="card glass">
    <div class="dash-head-row">
        <div>
            <h1>Interview Video Call</h1>
            <p class="muted">
                <b><?=e($a['job_title'])?></b> · <?=e($a['company_name'])?>
                <?php if ((int)$a['employer_id'] === (int)$u['id']): ?>
                    · Applicant: <b><?=e($a['applicant_name'])?></b>
                <?php endif; ?>
                <?php if (!empty($a['interview_at'])): ?>
                    · Scheduled: <b><?=e(date('M j, Y g:i A', strtotime($a['interview_at'])))?></b>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a class="btn sm" href="<?=e($embedUrl)?>" target="_blank" rel="noopener noreferrer">Open Secure Call Window &#8599;</a>
            <a class="btn sm blue" href="<?=e($backUrl)?>">Back</a>
        </div>
    </div>

    <!-- Fallback prompt when opened on a non-HTTPS domain -->
    <div id="abeka-insecure-notice" style="display:none; margin-top:12px; padding:18px; border-radius:14px; background:rgba(255,255,255,0.45); border:1px solid rgba(0,0,0,0.08);">
        <h3><?= ((int)$a['employer_id'] === (int)$u['id']) ? 'Ready to Interview your Candidate?' : 'Ready to Join Your Interview' ?></h3>
        <p class="muted">
            Click the button below to open your secure video interview room.
        </p>
        <a class="btn" href="<?=e($embedUrl)?>" target="_blank" rel="noopener noreferrer">Join Video Call &#8599;</a>
    </div>

    <!-- Embedded Jitsi Meet iframe (active on HTTPS or localhost) -->
    <div id="abeka-call-frame" style="margin-top:12px; border-radius:14px; overflow:hidden; border:1px solid rgba(255,255,255,0.35); background:#111;">
        <iframe
            id="abeka-jitsi-iframe"
            src=""
            allow="camera; microphone; fullscreen; display-capture; autoplay"
            style="width:100%; height:620px; border:0; display:block;"
            title="ABEKa Interview Video Call"
        ></iframe>
    </div>
</section>

<!-- Detect secure context before loading embedded WebRTC frame -->
<script>
(function () {
    const embedUrl = <?= json_encode($embedUrl) ?>;
    const notice = document.getElementById('abeka-insecure-notice');
    const frameWrap = document.getElementById('abeka-call-frame');
    const iframe = document.getElementById('abeka-jitsi-iframe');

    if (window.isSecureContext) {
        iframe.src = embedUrl;
    } else {
        frameWrap.style.display = 'none';
        notice.style.display = 'block';
    }
})();
</script>

<?php require 'includes/footer.php'; ?>
