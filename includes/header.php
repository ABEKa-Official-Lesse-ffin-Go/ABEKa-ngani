<?php
// Load the configuration file and required functions.
require_once __DIR__ . '/config.php';

// Get the currently logged-in user's information.
$u = user();

// Get the number of unread notifications for the current user.
$unread = $u
    ? q(
        'SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0',
        [$u['id']]
    )->fetch()['c']
    : 0;

// Set the notification link depending on the user's role.
$mailLink = !$u
    ? ''
    : ($u['role'] === 'employer' ? 'employer.php#alerts' : 'inbox.php');

// Detect active navbar tab for the clicked/current page indicator.
$curPage = basename($_SERVER['PHP_SELF'] ?? '');
$curType = (string)($_GET['type'] ?? '');
$isHomeTab = in_array($curPage, ['index.php', 'about.php', 'place.php'], true);
$isNewsTab = ($curPage === 'feed.php' && $curType === 'news');
$isAnnTab  = ($curPage === 'feed.php' && $curType === 'announcements');
$isAlertTab = ($curPage === 'alerts.php' || $curPage === 'alert_admin.php');
$isGovTab   = ($curPage === 'gov_offices.php');
$isAdminTab = in_array($curPage, ['audit_trail.php', 'content_admin.php', 'fuel_admin.php'], true) || ($curPage === 'dashboard.php' && ($u['role'] ?? '') === 'admin');
$isEmpTab   = in_array($curPage, ['jobs.php', 'job.php', 'elearning.php', 'mock_interview.php', 'employer.php', 'applicants.php', 'interview_call.php'], true);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Set the page title using the provided title or the default ABEKa name. -->
    <title><?=e($title ?? 'ABEKa')?> | ABEKa</title>

    <!-- Load Rubik typography site-wide, with system fallbacks if offline. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Load the website favicon and stylesheet. -->
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link
        rel="stylesheet"
        href="assets/css/glass.css?v=<?=filemtime(__DIR__ . "/../assets/css/glass.css")?>"
    >
    <link
        rel="stylesheet"
        href="assets/css/tourist_spot.css?v=<?=filemtime(__DIR__ . "/../assets/css/tourist_spot.css")?>"
    >
    <!-- Smooth hover & toggle shadow for navbar tabs and buttons -->
    <style>
        header.nav nav > a,
        header.nav nav .nav-dd-trigger {
            position: relative;
            padding: 7px 12px;
            border-radius: 10px;
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.12s ease;
        }
        header.nav nav > a:hover,
        header.nav nav > a:focus-visible,
        header.nav nav .nav-dd-trigger:hover,
        header.nav nav .nav-dd-trigger:focus-visible,
        header.nav nav .nav-dd.open .nav-dd-trigger,
        header.nav nav > a.nav-active,
        header.nav nav .nav-dd-trigger.nav-active {
            background: rgba(255, 247, 237, 0.82);
            box-shadow: 0 6px 18px rgba(249, 115, 22, 0.20), 0 2px 6px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }
        header.nav nav > a.nav-active::after,
        header.nav nav .nav-dd-trigger.nav-active::after {
            content: '';
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: 2px;
            height: 3px;
            border-radius: 999px;
            background: #f97316;
            box-shadow: 0 1px 6px rgba(249, 115, 22, 0.45);
        }
        header.nav nav > a:active,
        header.nav nav .nav-dd-trigger:active,
        .btn:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 2px 8px rgba(249, 115, 22, 0.25);
        }
        .nav-dd-menu a {
            border-radius: 8px;
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }
        .nav-dd-menu a:hover,
        .nav-dd-menu a:focus-visible {
            background: rgba(255, 247, 237, 0.88);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.14);
        }
        .btn,
        .elearn-tab {
            transition: box-shadow 0.2s ease, transform 0.15s ease, filter 0.2s ease;
        }
        .btn:hover,
        .elearn-tab:hover,
        .elearn-tab.active {
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.16);
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <!-- Background logo decoration. -->
    <div class="bg-logo"></div>

    <!-- Main navigation bar. -->
    <header class="glass nav">
        <!-- Website logo that links back to the homepage. -->
        <a href="index.php">
            <img class="logo" src="assets/logo.png" alt="ABEKa">
        </a>

        <!-- Main website navigation links. -->
        <nav>
            <!-- Home tab with click/hover About Us dropdown -->
            <div class="nav-dd nav-dd-click" id="homeNavDd">
                <button
                    type="button"
                    class="nav-dd-trigger <?=$isHomeTab ? 'nav-active' : ''?>"
                    id="homeNavBtn"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <b>Home &#9662;</b>
                </button>
                <div class="nav-dd-menu glass" role="menu" aria-label="Home and About ABEKa">
                    <a href="about.php"><b>About Us</b></a>
                    <a href="about.php#intro">Introduction</a>
                    <a href="about.php#brand">Brand Identity</a>
                    <a href="about.php#goal">Goal, Mission &amp; Vision</a>
                    <a href="about.php#values">Core Values</a>
                    <a href="index.php">Main Home Page</a>
                </div>
            </div>

            <a href="feed.php?type=news" class="<?=$isNewsTab ? 'nav-active' : ''?>"><b>News</b></a>
            <a href="feed.php?type=announcements" class="<?=$isAnnTab ? 'nav-active' : ''?>"><b>Announcements</b></a>
            <a href="alerts.php" class="alert-nav <?=$isAlertTab ? 'nav-active' : ''?>" aria-label="Emergency alerts"><b>Alerts</b></a>
            <a href="gov_offices.php" class="<?=$isGovTab ? 'nav-active' : ''?>"><b>Gov Offices</b></a>

            <!-- Admin tools dropdown (keeps navbar balanced on a single line) -->
            <?php if ($u && $u['role'] === 'admin'): ?>
                <div class="nav-dd nav-dd-click" id="adminNavDd">
                    <button
                        type="button"
                        class="nav-dd-trigger <?=$isAdminTab ? 'nav-active' : ''?>"
                        id="adminNavBtn"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >
                        <b>Admin &#9662;</b>
                    </button>
                    <div class="nav-dd-menu glass" role="menu" aria-label="Admin tools">
                        <a href="dashboard.php"><b>Dashboard</b></a>
                        <a href="audit_trail.php">Audit Trail / Log History</a>
                        <a href="content_admin.php">Edit Site Content</a>
                        <a href="fuel_admin.php">Manage Fuel Prices</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Employment dropdown (visible when logged in as employee or employer) -->
            <?php if ($u && $u['role'] !== 'admin'): ?>
                <div class="nav-dd nav-dd-click" id="empNavDd">
                    <button
                        type="button"
                        class="nav-dd-trigger <?=$isEmpTab ? 'nav-active' : ''?>"
                        id="empNavBtn"
                        aria-haspopup="true"
                        aria-expanded="false"
                    >
                        <b>Employment &#9662;</b>
                    </button>
                    <div class="nav-dd-menu glass" id="empNavMenu" role="menu" aria-label="Employment options">
                        <?php if ($u['role'] === 'employee'): ?>
                            <a href="jobs.php">Jobs Hiring</a>
                            <a href="elearning.php">E-Learnings</a>
                        <?php else: ?>
                            <a href="employer.php">Job Vacancies</a>
                            <a href="applicants.php">Applicants</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Account section containing user information and account actions. -->
        <div class="acct">
            <?php if ($u): ?>
                <!-- Display the contact person's name for employers (or user's name otherwise). -->
                <?php
                $welcomeName = $u['name'];
                if ($u['role'] === 'employer') {
                    try {
                        $cp = q('SELECT contact_person FROM employer_profiles WHERE user_id = ? LIMIT 1', [$u['id']])->fetchColumn();
                        if (!empty(trim((string)$cp))) {
                            $welcomeName = trim((string)$cp);
                        }
                    } catch (Throwable $e) {
                        // Keep default account name if query fails
                    }
                }
                ?>
                <span class="acct-welcome" title="Welcome: <?=e($welcomeName)?>">
                    Welcome: <b><?=e($welcomeName)?></b>
                </span>

                <!-- Show the notification button for non-admin users. -->
                <?php if ($u['role'] !== 'admin'): ?>
                    <div class="dd">
                        <button
                            type="button"
                            class="iconbtn"
                            id="mailBtn"
                            title="Notifications"
                            aria-haspopup="true"
                            aria-expanded="false"
                            data-poll="1"
                            data-all="<?=$mailLink?>"
                        >
                            <!-- Notification icon and unread notification count. -->
                            <?=ico('mail')?>
                            <i class="badge" <?=$unread ? '' : 'hidden'?>>
                                <?=$unread?>
                            </i>
                        </button>

                        <!-- Dropdown panel where notifications are displayed. -->
                        <div class="ddpanel glass" id="mailPanel" hidden></div>
                    </div>
                <?php endif; ?>

                <!-- Profile icon dropdown with My Dashboard for Employee and Employer accounts. -->
                <?php if ($u['role'] !== 'admin'): ?>
                    <div class="nav-dd nav-dd-click acct-dd" id="profileNavDd">
                        <button
                            type="button"
                            class="iconbtn nav-dd-trigger"
                            id="profileNavBtn"
                            title="Account &amp; My Dashboard"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            <?=ico('user')?>
                        </button>
                        <div class="nav-dd-menu nav-dd-right glass" role="menu" aria-label="Account menu">
                            <a href="dashboard.php"><b>My Dashboard</b></a>
                            <?php if ($u['role'] === 'employee'): ?>
                                <a href="profile.php">My Profile &amp; Documents</a>
                                <a href="inbox.php">Inbox &amp; Offers</a>
                            <?php else: ?>
                                <a href="company_profile.php">Edit Company Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Log-out button for the currently logged-in user. -->
                <a class="iconbtn" href="logout.php" title="Log out">
                    <?=ico('out')?>
                    <span class="sr">Log out</span>
                </a>

            <?php else: ?>
                <!-- Display login and registration links for guests. -->
                <a href="login.php">Log in</a> |
                <a href="register.php">Sign up</a>
            <?php endif; ?>
        </div>
    </header>

        <script>
        (function () {
            const dropdowns = document.querySelectorAll(".nav-dd-click");
            dropdowns.forEach(function (dd) {
                const btn = dd.querySelector(".nav-dd-trigger");
                if (!btn) return;
                btn.addEventListener("click", function (e) {
                    e.stopPropagation();
                    dropdowns.forEach(function (other) {
                        if (other !== dd) {
                            other.classList.remove("open");
                            const ob = other.querySelector(".nav-dd-trigger");
                            if (ob) ob.setAttribute("aria-expanded", "false");
                        }
                    });
                    const open = dd.classList.toggle("open");
                    btn.setAttribute("aria-expanded", String(open));
                });
            });
            document.addEventListener("click", function (e) {
                dropdowns.forEach(function (dd) {
                    if (!dd.contains(e.target)) {
                        dd.classList.remove("open");
                        const btn = dd.querySelector(".nav-dd-trigger");
                        if (btn) btn.setAttribute("aria-expanded", "false");
                    }
                });
            });
        })();
        </script>

    <!-- Main page content starts here. -->
    <main>
