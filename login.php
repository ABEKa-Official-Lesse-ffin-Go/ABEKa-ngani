<?php
// Include database configuration
require 'includes/config.php';
$err = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Find the user using the entered email address
    $r = q(
        'SELECT * FROM users WHERE email=?',
        [trim($_POST['email'])]
    )->fetch();

    // Verify the entered password
    if ($r && password_verify($_POST['password'], $r['pass'])) {

        // Regenerate the session ID for security
        session_regenerate_id(true);

        // Store user information in the session
        $_SESSION['user'] = [
            'id' => $r['id'],
            'name' => $r['name'],
            'role' => $r['role']
        ];

        audit_log('Login', 'Signed in successfully', $r['id'], $r['name'], $r['role']);

        // Redirect the user based on their role
        header('Location: ' . home_for($r['role']));
        exit;
    }

    audit_log('Login Failed', 'Invalid credentials for ' . trim((string)($_POST['email'] ?? '')), null, trim((string)($_POST['email'] ?? 'Guest')), 'guest');

    // Display error message for incorrect login credentials
    $err = 'Email or password is incorrect.';
}

// Set the page title and include the header
$title = 'Log in';
require 'includes/header.php';
?>

<!-- Login form -->
<form class="card auth glass" method="post">

    <h1>Login</h1>

    <!-- Display error message if login fails -->
    <?php if ($err): ?>
        <p><?=e($err)?></p>
    <?php endif; ?>

    <!-- Email address field -->
    <label>
        Email address
        <input type="email" name="email" required>
    </label>

    <!-- Password field with visibility toggle -->
    <label>
        Password
        <span class="pw">
            <input type="password" name="password" required>
            <button type="button" data-eye><?=ico('eye')?></button>
        </span>
    </label>

    <!-- Forgot password link for all users (Employee, Employer, Admin) -->
    <p class="auth-forgot">
        <a href="forgot_password.php">Forgot password?</a>
    </p>

    <!-- Submit login form -->
    <button class="btn blue" style="width:100%">Log in</button>

    <!-- Link to registration page -->
    <p class="muted" style="text-align:center">
        Don't have an account? <a href="register.php">Register here</a>
    </p>

</form>

<?php
// Include the footer
require 'includes/footer.php';
