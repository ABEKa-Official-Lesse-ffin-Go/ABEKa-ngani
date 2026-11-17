<?php

require 'includes/config.php';

// Start the session if it is not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only log out when the user confirmed (form submitted via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Clear all session data
    $_SESSION = [];

    // Remove the session cookie
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $c['path'],
            $c['domain']
        );
    }

    // Destroy the session
    if (user()) {
    audit_log('Logout', 'Signed out');
}
session_destroy();

    // Redirect to the home page
    header('Location: index.php');
    exit;
}

// Set the page title and include the header
$title = 'Confirm Logout';
require 'includes/header.php';
?>

<!-- Logout confirmation form -->
<form
    class="card auth glass"
    method="post"
    style="text-align:center"
>
    <h1>Log out</h1>

    <p class="muted">Do you want to log out?</p>

    <button
        type="submit"
        class="btn blue"
        style="width:100%"
    >
        Yes, log out
    </button>

    <p style="margin-top:12px">
        <a href="javascript:history.back()">Cancel</a>
    </p>
</form>

<?php require 'includes/footer.php'; ?>
