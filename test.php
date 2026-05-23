<?php
require_once __DIR__ . '/converter.php';

$sqlContent = file_get_contents(__DIR__ . '/../DB/init.sql');

// Remove UTF-8 BOM if present
$sqlContent = preg_replace('/^(\xEF\xBB\xBF)/', '', $sqlContent);

echo "Content length: " . strlen($sqlContent) . "\n";

// Split into statements by semicolon
$statements = preg_split('/;(?:[\r\n]+|$)/', $sqlContent, -1, PREG_SPLIT_NO_EMPTY);

echo "Total statements: " . count($statements) . "\n\n";

// Show first few statements
for ($i = 0; $i < min(10, count($statements)); $i++) {
    $stmt = trim($statements[$i]);
    if (empty($stmt)) continue;

    echo "Statement $i: " . substr($stmt, 0, 80) . "...\n";
    echo "  Starts with: " . (preg_match('/^([A-Z]+)/', $stmt, $m) ? $m[1] : 'none') . "\n";
    echo "  Is CREATE TABLE: " . (preg_match('/^CREATE\s+TABLE/i', $stmt) ? 'YES' : 'NO') . "\n";
    echo "\n";
}