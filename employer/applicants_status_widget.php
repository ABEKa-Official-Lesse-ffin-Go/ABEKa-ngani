<?php
// Expects $app array with: id, status, interview_at, interview_link, offer_file
$employerOptions = [
    'For Review',
    'For Interview',
    'Interview Passed',
    'Not Selected',
    'Hired',
];
?>
<form method="POST" action="update_application_status.php" enctype="multipart/form-data" class="abeka-status-form">
  <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">

  <select name="status" onchange="abekaToggleFields(this)">
    <?php foreach ($employerOptions as $opt): ?>
      <option value="<?= htmlspecialchars($opt) ?>" <?= ($app['status'] ?? 'For Review') === $opt ? 'selected' : '' ?>>
        <?= htmlspecialchars($opt) ?>
      </option>
    <?php endforeach; ?>
    <?php if (($app['status'] ?? '') === 'Offer Accepted'): ?>
      <option value="Offer Accepted" selected disabled>Offer Accepted</option>
    <?php endif; ?>
  </select>

  <button type="submit">Update</button>

  <div class="interview-fields" style="display: <?= ($app['status'] ?? '') === 'For Interview' ? 'block' : 'none' ?>; margin-top: 8px;">
    <label>Interview Date &amp; Time:</label>
    <input type="datetime-local" name="interview_at" value="<?= !empty($app['interview_at']) ? date('Y-m-d\TH:i', strtotime($app['interview_at'])) : '' ?>">
    <label>Video Call Link (optional — auto-generates Jitsi Meet if blank):</label>
    <input type="url" name="interview_link" placeholder="https://meet.jit.si/..." value="<?= htmlspecialchars($app['interview_link'] ?? '') ?>">
    <?php if (!empty($app['interview_link'])): ?>
      <a href="<?= htmlspecialchars($app['interview_link']) ?>" target="_blank" rel="noopener">Join Video Call</a>
    <?php endif; ?>
  </div>

  <div class="offer-fields" style="display: <?= ($app['status'] ?? '') === 'Interview Passed' ? 'block' : 'none' ?>; margin-top: 8px;">
    <label>Upload Job Offer Letter (PDF/DOCX):</label>
    <input type="file" name="offer_file" accept=".pdf,.doc,.docx">
    <?php if (!empty($app['offer_file'])): ?>
      <a href="../uploads/offers/<?= htmlspecialchars($app['offer_file']) ?>" target="_blank">View Current Offer</a>
    <?php endif; ?>
  </div>
</form>

<script>
function abekaToggleFields(selectEl) {
  const form = selectEl.closest('form');
  form.querySelector('.interview-fields').style.display = (selectEl.value === 'For Interview') ? 'block' : 'none';
  form.querySelector('.offer-fields').style.display = (selectEl.value === 'Interview Passed') ? 'block' : 'none';
}
</script>
