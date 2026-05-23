<?php
/**
 * Download handler for converted SQLite files
 */

header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: Binary');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$uploadDir = __DIR__ . '/upload/';

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    echo 'No file specified.';
    exit;
}

$fileName = basename($_GET['file']); // Prevent directory traversal
$filePath = $uploadDir . $fileName;

// Validate file exists and is in the upload directory
$realPath = realpath($filePath);
$realDir = realpath($uploadDir);

if ($realPath === false || $realDir === false || strpos($realPath, $realDir) !== 0) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

if (!file_exists($realPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

// Set appropriate headers for download
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: attachment; filename="' . $fileName . '"');

// Read and output file
readfile($realPath);

// Delete the file after download (cleanup)
@unlink($realPath);

exit;