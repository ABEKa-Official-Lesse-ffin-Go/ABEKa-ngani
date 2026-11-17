<?php
// Secure document viewer: streams uploaded clearances/IDs only to the owning employee or employers they applied to.
require_once __DIR__ . '/includes/config.php';

$u = user();
if (!$u) {
    http_response_code(403);
    exit('Access denied.');
}

$targetUserId = (int)($_GET['user'] ?? 0);
$docType = (string)($_GET['doc'] ?? '');
$docTypes = employee_doc_types();

if ($targetUserId <= 0 || !isset($docTypes[$docType])) {
    http_response_code(400);
    exit('Invalid document request.');
}

$allowed = false;
if ($u['role'] === 'employee' && (int)$u['id'] === $targetUserId) {
    $allowed = true;
} elseif ($u['role'] === 'employer') {
    $hasApp = q(
        'SELECT 1 FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.employee_id = ? AND j.employer_id = ? LIMIT 1',
        [$targetUserId, $u['id']]
    )->fetchColumn();
    $allowed = (bool)$hasApp;
}

if (!$allowed) {
    http_response_code(403);
    exit('You do not have permission to view this document.');
}

$row = q(
    'SELECT file_path, original_name FROM employee_documents WHERE user_id = ? AND doc_type = ? LIMIT 1',
    [$targetUserId, $docType]
)->fetch();

if (!$row) {
    http_response_code(404);
    exit('Document not found.');
}

$baseDir = realpath(__DIR__ . '/uploads/documents');
$fullPath = realpath(__DIR__ . '/' . ltrim($row['file_path'], '/'));

if (!$baseDir || !$fullPath || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found on server.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $row['original_name'] ?: ($docType . '.' . $ext));

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
