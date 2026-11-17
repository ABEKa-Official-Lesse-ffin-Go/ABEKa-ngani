<?php
// Public Reset Password page: verifies the confirmation token and updates the user's password.
require_once __DIR__ . '/includes/config.php';

$title = 'Reset Password';
$rawToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$err = '';
$done = false;
$validReset = null;

if ($rawToken !== '' && ctype_xdigit($rawToken)) {
    $tokenHash = hash('sha256', $rawToken);
    $validReset = q(
        'SELECT pr.id AS reset_id, pr.user_id, u.email, u.role
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
         LIMIT 1',
        [$tokenHash]
    )->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = (string)($_POST['password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if (!$validReset) {
        $err = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($newPass) < 6) {
        $err = 'Password must be at least 6 characters long.';
    } elseif ($newPass !== $confirmPass) {
        $err = 'Passwords do not match.';
    } else {
        // Update user password and mark the reset token as used.
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        q('UPDATE users SET pass = ? WHERE id = ?', [$hashed, $validReset['user_id']]);
        q('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$validReset['reset_id']]);
        audit_log('Password Reset Completed', 'Password updated for ' . $validReset['email'], $validReset['user_id'], $validReset['email'], $validReset['role']);
        $done = true;
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- Reset Password card -->
<form class="card auth glass" method="post">
    <h1>Reset Password</h1>

    <?php if ($done): ?>
        <p class="alert-admin-success">
            Your password has been updated. You can now log in with your new password.
        </p>
        <p style="text-align:center">
            <a class="btn blue" href="login.php" style="display:inline-block;width:100%;text-align:center">Go to Login</a>
        </p>
    <?php elseif (!$validReset): ?>
        <p class="alert-admin-error">
            This password reset link is invalid or has expired.
        </p>
        <p style="text-align:center">
            <a class="btn blue" href="forgot_password.php" style="display:inline-block;width:100%;text-align:center">Request a New Link</a>
        </p>
    <?php else: ?>
        <p class="muted">
            Setting a new password for <b><?=e($validReset['email'])?></b> (<?=e($validReset['role'])?>).
        </p>

        <?php if ($err): ?>
            <p class="alert-admin-error"><?=e($err)?></p>
        <?php endif; ?>

        <input type="hidden" name="token" value="<?=e($rawToken)?>">

        <label>
            New password (min 6 characters)
            <span class="pw">
                <input type="password" name="password" minlength="6" required>
                <button type="button" data-eye><?=ico('eye')?></button>
            </span>
        </label>

        <label>
            Confirm new password
            <span class="pw">
                <input type="password" name="confirm_password" minlength="6" required>
                <button type="button" data-eye><?=ico('eye')?></button>
            </span>
        </label>

        <button class="btn blue" style="width:100%" type="submit">Update Password</button>
    <?php endif; ?>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
