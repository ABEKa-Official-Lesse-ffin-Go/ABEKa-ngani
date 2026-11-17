<?php
// Public Forgot Password page: sends a password reset link to Employee, Employer, or Admin accounts.
require_once __DIR__ . '/includes/config.php';

$title = 'Forgot Password';
$msg = '';
$err = '';
$localPreviewLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        // Look up any active account (Employee, Employer, or Admin) matching this email.
        $userRow = q('SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1', [$email])->fetch();

        if ($userRow) {
            // Invalidate previous unused reset tokens for this user.
            q('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userRow['id']]);

            // Create a secure random token and store its SHA-256 hash.
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            q(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
                [$userRow['id'], $tokenHash]
            );

            // Build the reset confirmation link.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/')), '/');
            $resetUrl = $scheme . '://' . $host . $dir . '/reset_password.php?token=' . urlencode($rawToken);

            // Attempt to send confirmation link via PHP mail() (requires sendmail/SMTP in php.ini).
            $subject = 'ABEKa Password Reset Confirmation';
            $body = "Hello {$userRow['name']},\n\n"
                . "We received a request to reset your ABEKa ({$userRow['role']}) account password.\n"
                . "Click the confirmation link below to set a new password (valid for 1 hour):\n\n"
                . $resetUrl . "\n\n"
                . "If you did not request this, you can safely ignore this email.\n";
            $sent = abeka_send_mail($userRow['email'], $subject, $body);
            audit_log('Password Reset Requested', 'Reset link generated for ' . $userRow['email'], $userRow['id'], $userRow['name'], $userRow['role']);

            // On localhost XAMPP (when sendmail is not configured), display the reset link for testing.
            $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', 'abeka.test', 'www.abeka.test'], true);
            if (!$sent && $isLocal) {
                $localPreviewLink = $resetUrl;
            }
        }

        $msg = 'If an account is registered with that email address, a password reset confirmation link has been sent.';
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- Forgot Password form -->
<form class="card auth glass" method="post">
    <h1>Forgot Password</h1>
    <p class="muted">
        Enter the email address for your Employee, Employer, or Admin account and we'll send you a reset confirmation link.
    </p>

    <?php if ($err): ?>
        <p class="alert-admin-error"><?=e($err)?></p>
    <?php endif; ?>

    <?php if ($msg): ?>
        <p class="alert-admin-success"><?=e($msg)?></p>
    <?php endif; ?>

    <?php if ($localPreviewLink): ?>
        <div class="card" style="margin:.75rem 0; word-break:break-all;">
            <strong>Local XAMPP Test Link:</strong><br>
            <small class="muted">Mail server is not configured locally, so use this confirmation link to test resetting your password:</small><br>
            <a href="<?=e($localPreviewLink)?>"><?=e($localPreviewLink)?></a>
        </div>
    <?php endif; ?>

    <label>
        Email address
        <input type="email" name="email" required placeholder="you@example.com" value="<?=e($_POST['email'] ?? '')?>">
    </label>

    <button class="btn blue" style="width:100%" type="submit">Send Reset Link</button>

    <p class="muted" style="text-align:center">
        Remembered your password? <a class="btn sm blue" href="login.php">Back to Login</a>
    </p>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
