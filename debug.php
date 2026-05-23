<?php
require_once __DIR__ . '/converter.php';

$sql = file_get_contents(__DIR__ . '/../DB/init.sql');

// Remove UTF-8 BOM
$sql = preg_replace('/^(\xEF\xBB\xBF)/', '', $sql);

echo "=== SQL Content Info ===\n";
echo "Length: " . strlen($sql) . " chars\n\n";

// Split by semicolon
$statements = preg_split('/;(?:[\r\n]+|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY);
echo "Total statements: " . count($statements) . "\n\n";

echo "=== Looking for CREATE TABLE statements ===\n";
foreach ($statements as $i => $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    if (preg_match('/CREATE\s+TABLE/i', $stmt)) {
        echo "--- Statement $i ---\n";
        echo substr($stmt, 0, 150) . "\n...\n";
    }
}

echo "\n=== First 10 Statements ===\n";
for ($i = 0; $i < min(15, count($statements)); $i++) {
    $stmt = trim($statements[$i]);
    if (empty($stmt)) continue;

    echo "--- Statement $i ---\n";
    echo "First 100 chars: " . substr($stmt, 0, 100) . "\n";

    // Check what type
    $type = 'UNKNOWN';
    if (preg_match('/^CREATE\s+TABLE/i', $stmt)) {
        $type = 'CREATE TABLE';
    } elseif (preg_match('/^INSERT\s+INTO/i', $stmt)) {
        $type = 'INSERT';
    } elseif (preg_match('/^CREATE\s+DATABASE/i', $stmt)) {
        $type = 'CREATE DATABASE';
    } elseif (preg_match('/^USE/i', $stmt)) {
        $type = 'USE';
    } elseif (preg_match('/^SET/i', $stmt)) {
        $type = 'SET';
    } elseif (preg_match('/^--/', $stmt)) {
        $type = 'COMMENT';
    }
    echo "Type: $type\n\n";
}

echo "=== Testing convertSqlToSqlite() ===\n";
$result = convertSqlToSqlite($sql);
echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Output length: " . strlen($result['sqlite_sql']) . " chars\n";
echo "CREATE TABLE count: " . substr_count($result['sqlite_sql'], 'CREATE TABLE') . "\n";
echo "Errors: " . count($result['errors']) . "\n";

if (!empty($result['errors'])) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $e) {
        echo "  - $e\n";
    }
}

echo "\n=== First 500 chars of output ===\n";
echo substr($result['sqlite_sql'], 0, 500);