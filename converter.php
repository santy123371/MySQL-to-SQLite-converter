<?php
/**
 * SQL to SQLite Converter Engine
 *
 * Converts MySQL SQL files to SQLite compatible format.
 *
 * Conversion Mappings:
 * - AUTO_INCREMENT → INTEGER PRIMARY KEY AUTOINCREMENT
 * - TINYINT/SMALLINT/MEDIUMINT → INTEGER
 * - LONGTEXT/MEDIUMTEXT → TEXT
 * - DATETIME → TEXT
 * - Backticks (`) → Double quotes (")
 * - ENGINE=InnoDB → removed
 * - CREATE DATABASE / USE → skipped
 *
 * @param string $sqlContent The raw SQL content from uploaded file
 * @return array ['success' => bool, 'sqlite_sql' => string, 'errors' => string[]]
 */
function convertSqlToSqlite(string $sqlContent): array
{
    $errors = [];
    $output = "";

    // Remove UTF-8 BOM if present
    $sqlContent = preg_replace('/^(\xEF\xBB\xBF)/', '', $sqlContent);

    // Remove MySQL-specific statements that don't apply to SQLite
    $sqlContent = preg_replace('/SET\s+NAMES\s+utf8mb4;?\s*/i', '', $sqlContent);
    $sqlContent = preg_replace('/SET\s+CHARACTER\s+SET\s+utf8mb4;?\s*/i', '', $sqlContent);
    $sqlContent = preg_replace('/CREATE\s+DATABASE.*?;?\s*/i', '', $sqlContent);
    $sqlContent = preg_replace('/USE\s+[a-zA-Z_0-9]+;?\s*/i', '', $sqlContent);

    // Remove line comments (-- comment)
    $sqlContent = preg_replace('/--[^\n]*/', '', $sqlContent);

    // Remove block comments (/* comment */)
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);

    // Remove ALTER TABLE (not supported in SQLite for adding columns this way)
    $sqlContent = preg_replace('/ALTER\s+TABLE.*?;/i', '', $sqlContent);

    // Remove CREATE INDEX (SQLite handles indexes differently)
    $sqlContent = preg_replace('/CREATE\s+(?:UNIQUE\s+)?INDEX.*?;/i', '', $sqlContent);

    // Strategy: Extract CREATE TABLE statements using regex that captures the entire table definition
    // This handles tables with multiple semicolons inside (like FOREIGN KEY constraints)
    $tablePattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z_][a-zA-Z_0-9]*)\s*\(([^;]+)\)\s*(?:ENGINE\s*=\s*\w+)?(?:\s*DEFAULT\s+CHARSET\s*=\s*\w+)?(?:\s*COLLATE\s*=\s*\w+)?\s*;?/i';

    if (preg_match_all($tablePattern, $sqlContent, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tableName = $match[1];
            $tableDef = $match[2];

            $converted = convertCreateTableFull($tableName, $tableDef);
            if (!empty($converted)) {
                $output .= $converted . "\n\n";
            }
        }
    }

    // Also try to extract INSERT statements
    // Find all INSERT INTO statements (they're usually in the seed.sql file)
    // Only match INSERT INTO, NOT INSERT IGNORE INTO
    $insertPattern = '/INSERT\s+(?:IGNORE\s+)?INTO\s+([a-zA-Z_][a-zA-Z_0-9]*)\s*\(([^)]+)\)\s*VALUES\s*(.+?);/is';
    if (preg_match_all($insertPattern, $sqlContent, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $converted = convertInsertStatement($match[0]);
            if (!empty($converted)) {
                $output .= $converted . "\n";
            }
        }
    }

    return [
        'success' => strpos($output, 'CREATE TABLE') !== false,
        'sqlite_sql' => $output,
        'errors' => $errors
    ];
}

/**
 * Convert full CREATE TABLE with table name and definition
 */
function convertCreateTableFull(string $tableName, string $tableDef): string
{
    $hasIfNotExists = true; // We extracted with IF NOT EXISTS pattern

    // Remove INDEX definitions (both inline and separate lines)
    $tableDef = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $tableDef);
    $tableDef = preg_replace('/INDEX\s+\w+\s*\([^)]+\)/i', '', $tableDef);
    // Remove UNIQUE KEY definitions
    $tableDef = preg_replace('/,\s*UNIQUE\s+KEY\s+\w+\s*\([^)]+\)/i', '', $tableDef);
    $tableDef = preg_replace('/UNIQUE\s+KEY\s+\w+\s*\([^)]+\)/i', '', $tableDef);
    // Remove ON UPDATE from DEFAULT (MySQL specific)
    $tableDef = preg_replace('/DEFAULT\s+CURRENT_TIMESTAMP\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', 'DEFAULT CURRENT_TIMESTAMP', $tableDef);
    // Remove ON DELETE from FOREIGN KEY (SQLite handles this differently, keep the FK but remove actions)
    $tableDef = preg_replace('/ON\s+DELETE\s+(RESTRICT|SET\s+NULL|CASCADE|NO\s+ACTION)/i', '', $tableDef);
    $tableDef = preg_replace('/ON\s+UPDATE\s+(RESTRICT|SET\s+NULL|CASCADE|NO\s+ACTION)/i', '', $tableDef);

    // Convert data types: TINYINT, SMALLINT, MEDIUMINT -> INTEGER (MUST come first!)
    $tableDef = preg_replace('/TINYINT(?:\(\d+\))?/i', 'INTEGER', $tableDef);
    $tableDef = preg_replace('/SMALLINT(?:\(\d+\))?/i', 'INTEGER', $tableDef);
    $tableDef = preg_replace('/MEDIUMINT(?:\(\d+\))?/i', 'INTEGER', $tableDef);
    // Convert INT to INTEGER (use negative lookahead to avoid converting INTEGER to INTEGEREGER)
    $tableDef = preg_replace('/(?<!INTEG)INT(?:\(\d+\))?(?!\s*\()/', 'INTEGER', $tableDef);

    // Convert AUTO_INCREMENT to AUTOINCREMENT (after INT conversion!)
    $tableDef = preg_replace('/INTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $tableDef);
    $tableDef = preg_replace('/INTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $tableDef);
    $tableDef = preg_replace('/AUTO_INCREMENT\s*=\s*\d+/i', '', $tableDef);

    // Convert text types
    $tableDef = preg_replace('/LONGTEXT/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/MEDIUMTEXT/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/TINYTEXT/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/TEXT(?!\()(?:\(\d+\))?/i', 'TEXT', $tableDef);

    // Convert DATETIME/DATE/TIME
    $tableDef = preg_replace('/DATETIME/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/TIMESTAMP(?!\s+WITH\s+TIME\s+ZONE)/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/DATE/i', 'TEXT', $tableDef);
    $tableDef = preg_replace('/TIME/i', 'TEXT', $tableDef);

    // Convert VARCHAR to TEXT
    $tableDef = preg_replace('/VARCHAR(?:\(\d+\))?/i', 'TEXT', $tableDef);

    // Convert DECIMAL/NUMERIC to REAL
    $tableDef = preg_replace('/DECIMAL(?:\(\d+(?:,\d+)?\))?/i', 'REAL', $tableDef);
    $tableDef = preg_replace('/NUMERIC(?:\(\d+(?:,\d+)?\))?/i', 'REAL', $tableDef);

    // Convert FLOAT/DOUBLE
    $tableDef = preg_replace('/FLOAT/i', 'REAL', $tableDef);
    $tableDef = preg_replace('/DOUBLE/i', 'REAL', $tableDef);

    // Convert BOOLEAN
    $tableDef = preg_replace('/BOOLEAN/i', 'INTEGER', $tableDef);

    // Convert ENUM to TEXT
    $tableDef = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $tableDef);

    // Convert backticks to double quotes
    $tableDef = preg_replace('/`/', '"', $tableDef);

    // Clean up multiple spaces and newlines
    $tableDef = preg_replace('/\s+/', ' ', $tableDef);

    // Remove multiple commas or trailing commas before closing paren
    $tableDef = preg_replace('/,\s*,/', ',', $tableDef);
    $tableDef = preg_replace('/,\s*\)/', ')', $tableDef);

    // Build final CREATE TABLE statement
    $sql = 'CREATE TABLE IF NOT EXISTS "' . $tableName . '" (' . trim($tableDef) . ');';

    return $sql;
}

/**
 * Convert CREATE TABLE statement to SQLite format
 */
function convertCreateTable(string $sql): string
{
    // Check if table has IF NOT EXISTS and preserve it
    $hasIfNotExists = preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $sql);
    
    // Remove ENGINE=InnoDB and other engine specifications
    $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);

    // Remove DEFAULT CHARSET and COLLATE
    $sql = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
    $sql = preg_replace('/COLLATE\s*=\s*\w+/i', '', $sql);

    // Remove INDEX definitions (SQLite handles these differently)
    $sql = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);
    $sql = preg_replace('/INDEX\s+\w+\s*\([^)]+\)/i', '', $sql);

    // Convert AUTO_INCREMENT to AUTOINCREMENT
    $sql = preg_replace('/INT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);

    // Convert data types: TINYINT, SMALLINT, MEDIUMINT -> INTEGER
    $sql = preg_replace('/TINYINT(?:\(\d+\))?/i', 'INTEGER', $sql);
    $sql = preg_replace('/SMALLINT(?:\(\d+\))?/i', 'INTEGER', $sql);
    $sql = preg_replace('/MEDIUMINT(?:\(\d+\))?/i', 'INTEGER', $sql);

    // Convert text types: LONGTEXT, MEDIUMTEXT -> TEXT
    $sql = preg_replace('/LONGTEXT/i', 'TEXT', $sql);
    $sql = preg_replace('/MEDIUMTEXT/i', 'TEXT', $sql);
    $sql = preg_replace('/TEXT(?!\()(?:\(\d+\))?/i', 'TEXT', $sql);

    // Convert DATETIME -> TEXT
    $sql = preg_replace('/DATETIME/i', 'TEXT', $sql);
    $sql = preg_replace('/TIMESTAMP(?!\s+WITH\s+TIME\s+ZONE)/i', 'TEXT', $sql);
    $sql = preg_replace('/DATE/i', 'TEXT', $sql);
    $sql = preg_replace('/TIME/i', 'TEXT', $sql);

    // Convert VARCHAR to TEXT (SQLite treats both as TEXT)
    $sql = preg_replace('/VARCHAR(?:\(\d+\))?/i', 'TEXT', $sql);

    // Convert DECIMAL/NUMERIC to REAL
    $sql = preg_replace('/DECIMAL(?:\(\d+(?:,\d+)?\))?/i', 'REAL', $sql);
    $sql = preg_replace('/NUMERIC(?:\(\d+(?:,\d+)?\))?/i', 'REAL', $sql);

    // Convert FLOAT/DOUBLE
    $sql = preg_replace('/FLOAT/i', 'REAL', $sql);
    $sql = preg_replace('/DOUBLE/i', 'REAL', $sql);

    // Convert BOOLEAN (MySQL uses TINYINT(1)) -> INTEGER
    $sql = preg_replace('/BOOLEAN/i', 'INTEGER', $sql);

    // Convert BLOB
    $sql = preg_replace('/BLOB/i', 'BLOB', $sql);

    // Convert ENUM to TEXT
    $sql = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $sql);

    // Convert backticks to double quotes
    $sql = preg_replace('/`/', '"', $sql);

    // Clean up multiple spaces
    $sql = preg_replace('/\s+/', ' ', $sql);

    // Remove multiple commas or trailing commas before closing paren
    $sql = preg_replace('/,\s*,/', ',', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);

    // Clean up any extra whitespace at the end
    $sql = trim($sql);

    // Add semicolon if missing
    if (substr($sql, -1) !== ';') {
        $sql .= ';';
    }

    // Restore IF NOT EXISTS if it was present
    if ($hasIfNotExists) {
        $sql = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $sql, 1);
    }

    return $sql;
}

/**
 * Convert INSERT INTO statement to SQLite format
 */
function convertInsertStatement(string $sql): string
{
    // Convert INSERT IGNORE to INSERT (SQLite doesn't support IGNORE)
    $sql = preg_replace('/INSERT\s+IGNORE\s+INTO/i', 'INSERT INTO', $sql);

    // Convert backticks to double quotes in identifiers
    $sql = preg_replace('/`/', '"', $sql);

    // Handle MySQL specific functions in VALUES
    // Convert NOW() to current timestamp
    $sql = preg_replace('/NOW\(\)/i', "'2024-01-01 00:00:00'", $sql);

    // Convert CURDATE() to current date
    $sql = preg_replace('/CURDATE\(\)/i', "'2024-01-01'", $sql);

    // Convert NULL to SQLite format
    $sql = preg_replace('/NULL\b/i', 'NULL', $sql);

    // Clean up whitespace
    $sql = preg_replace('/\s+/', ' ', $sql);

    return trim($sql);
}

/**
 * Generate SQLite database file from converted SQL
 *
 * @param string $sqliteSql The converted SQLite SQL
 * @param string $outputPath Path where the .db file should be created
 * @return array ['success' => bool, 'errors' => string[]]
 */
function generateSqliteDb(string $sqliteSql, string $outputPath): array
{
    $errors = [];

    try {
        // Remove existing file if exists
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        // Create new SQLite database
        $pdo = new PDO("sqlite:$outputPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Execute each statement
        $statements = preg_split('/;(?:[\r\n]+|$)/', $sqliteSql, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            $pdo->exec($statement);
        }

        return ['success' => true, 'errors' => []];

    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        return ['success' => false, 'errors' => $errors];
    }
}