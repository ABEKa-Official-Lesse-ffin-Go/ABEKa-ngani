<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Adjust to your PDO connection file

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_applications.php');
    exit;
}

$applicationId = (int)($_POST['application_id'] ?? 0);
$userId        = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    UPDATE applications
    SET status = 'Offer Accepted', offer_accepted_at = NOW()
    WHERE id = ? AND applicant_id = ? AND status = 'Interview Passed' AND offer_file IS NOT NULL
");
$stmt->execute([$applicationId, $userId]);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'my_applications.php'));
exit;
