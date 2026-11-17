<?php
// Include database configuration
require 'includes/config.php';
$err = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Determine the user's role
    $role = $_POST['role'] === 'employer' ? 'employer' : 'employee';

    // Check if the email is already registered
    if (q('SELECT 1 FROM users WHERE email=?', [$_POST['email']])->fetch())
        $err = 'That email is already registered.';

    // Check if the password has at least 8 characters
    elseif (strlen($_POST['password']) < 8)
        $err = 'Password needs at least 8 characters.';

    // Check if employer details are complete
    elseif ($role === 'employer' && (!trim($_POST['company']) || !trim($_POST['primary_location'])))
        $err = 'Company name and primary location are required.';

    else {
        // Insert the user's information into the database
        q(
            'INSERT INTO users(name,email,pass,role) VALUES(?,?,?,?)',
            [
                $_POST['name'],
                $_POST['email'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $role
            ]
        );

        // Get the ID of the newly registered user
        $id = $pdo->lastInsertId();

        // Save additional details if the user is an employer
        if ($role === 'employer') {
            q(
                'INSERT INTO employer_profiles (user_id, company, primary_location, phone, contact_email) VALUES (?, ?, ?, ?, ?)',
                [
                    $id,
                    $_POST['company'],
                    $_POST['primary_location'],
                    $_POST['phone'],
                    $_POST['contact_email']
                ]
            );

            // Save additional company addresses
            foreach (array_filter(array_map('trim', $_POST['addresses'] ?? [])) as $a)
                q('INSERT INTO employer_addresses(user_id,address) VALUES(?,?)', [$id, $a]);
        }

        // Redirect the user(employee) to the mock_interview page, if employer redirect to login page
         if ($role === 'employee') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $id;
            header('Location: mock_interview.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

// Set the page title and include the header
$title = 'Register';
require 'includes/header.php';
?>

<!-- Registration form -->
<form class="card auth glass" method="post" style="width:min(520px,92%)">

    <h1>Create an account</h1>

    <!-- Display error message if there is one -->
    <?php if ($err): ?>
        <p><?=e($err)?></p>
    <?php endif; ?>

    <!-- Select account type -->
    <p>
        <label>
            <input type="radio" name="role" value="employee" checked style="width:auto">
            Employee
        </label>
        &nbsp;
        <label>
            <input type="radio" name="role" value="employer" style="width:auto">
            Employer
        </label>
    </p>

    <!-- User information -->
    <label>
        Full name
        <input name="name" required>
    </label>

    <label>
        Email address
        <input type="email" name="email" required>
    </label>

    <label>
        Password
        <span class="pw">
            <input type="password" name="password" required minlength="8">
            <button type="button" data-eye><?=ico('eye')?></button>
        </span>
    </label>

    <!-- Additional fields shown for employers -->
    <div id="employerFields" class="hidden">

        <h2>Company details</h2>

        <label>
            Company name
            <input name="company">
        </label>

        <label for="pl">Primary location</label>

        <!-- Primary location and add address button -->
        <div class="row">
            <input id="pl" name="primary_location">
            <button
                type="button"
                id="addAddr"
                class="btn sm addbtn"
                title="Add another address"
                aria-label="Add another address"
            >+</button>
        </div>

        <!-- Container for additional addresses -->
        <div id="addrs"></div>

        <label>
            Contact number
            <input name="phone">
        </label>

        <label>
            Contact email
            <input type="email" name="contact_email">
        </label>

    </div>

    <!-- Terms and conditions agreement -->
    <p>
        <label>
            <input type="checkbox" required style="width:auto">
            I agree to the Terms and Conditions
        </label>
    </p>

    <!-- Submit registration form -->
    <button class="btn blue" style="width:100%">Create</button>

    <!-- Link to the login page -->
    <p class="muted" style="text-align:center">
        Already have an account? <a href="login.php">Login here</a>
    </p>

</form>

<?php
// Include the footer
require 'includes/footer.php';
