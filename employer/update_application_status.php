<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Adjust to your PDO connection file
require_once __DIR__ . '/../config/brevo_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: applicants.php');
    exit;
}

$applicationId = (int)($_POST['application_id'] ?? 0);
$newStatus     = trim($_POST['status'] ?? '');
$allowed       = ['For Review', 'For Interview', 'Interview Passed', 'Not Selected', 'Hired'];

if ($applicationId <= 0 || !in_array($newStatus, $allowed, true)) {
    die('Invalid application or status.');
}

// Fetch current application + applicant + job info (adjust table/column names to match your schema)
$stmt = $pdo->prepare("
    SELECT a.*, u.id AS applicant_user_id, u.full_name AS applicant_name, u.email AS applicant_email,
           j.title AS job_title, e.company_name AS company_name
    FROM applications a
    JOIN users u ON u.id = a.applicant_id
    JOIN jobs j ON j.id = a.job_id
    JOIN employers e ON e.id = j.employer_id
    WHERE a.id = ?
");
$stmt->execute([$applicationId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    die('Application not found.');
}

// Guard: Hired is only allowed after applicant accepts the offer
if ($newStatus === 'Hired' && $app['status'] !== 'Offer Accepted') {
    die('Applicant must accept the job offer before marking as Hired.');
}

$interviewAt   = $app['interview_at'];
$interviewLink = $app['interview_link'];
$offerFile     = $app['offer_file'];
$offerSentAt   = $app['offer_sent_at'];

if ($newStatus === 'For Interview') {
    $interviewAt   = !empty($_POST['interview_at']) ? date('Y-m-d H:i:s', strtotime($_POST['interview_at'])) : null;
    $interviewLink = trim($_POST['interview_link'] ?? '');
    if (empty($interviewLink)) {
        $interviewLink = 'https://meet.jit.si/ABEKa-Interview-' . $applicationId . '-' . bin2hex(random_bytes(4));
    }
}

if ($newStatus === 'Interview Passed' && !empty($_FILES['offer_file']['name'])) {
    $uploadDir = __DIR__ . '/../uploads/offers/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = strtolower(pathinfo($_FILES['offer_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        die('Offer letter must be PDF, DOC, or DOCX.');
    }
    $filename = 'offer_' . $applicationId . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['offer_file']['tmp_name'], $uploadDir . $filename)) {
        $offerFile   = $filename;
        $offerSentAt = date('Y-m-d H:i:s');
    }
}

$upd = $pdo->prepare("
    UPDATE applications
    SET status = ?, interview_at = ?, interview_link = ?, offer_file = ?, offer_sent_at = ?
    WHERE id = ?
");
$upd->execute([$newStatus, $interviewAt, $interviewLink, $offerFile, $offerSentAt, $applicationId]);

$applicantName = htmlspecialchars($app['applicant_name'] ?? 'Applicant');
$jobTitle      = htmlspecialchars($app['job_title'] ?? 'the position');
$companyName   = htmlspecialchars($app['company_name'] ?? 'the employer');

if ($newStatus === 'Not Selected') {
    $title = "Application Update: {$jobTitle}";
    $msg   = "Hi {$applicantName}, thank you for taking the time to apply and interview for {$jobTitle} at {$companyName}. "
           . "While we won't be moving forward with your application at this time, we truly appreciate your effort "
           . "and encourage you to keep exploring new opportunities on ABEKa.";
    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
        ->execute([$app['applicant_user_id'], $title, $msg]);
    sendBrevoEmail($app['applicant_email'], $applicantName, $title, "<p>{$msg}</p>");
}

if ($newStatus === 'Hired') {
    $title = "Congratulations! You're Hired for {$jobTitle}";
    $msg   = "Congratulations, {$applicantName}! {$companyName} has officially marked your application for {$jobTitle} as Hired. "
           . "Please check your ABEKa messages or email for onboarding details.";
    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
        ->execute([$app['applicant_user_id'], $title, $msg]);
    sendBrevoEmail($app['applicant_email'], $applicantName, $title, "<p>{$msg}</p>");
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'applicants.php'));
exit;
