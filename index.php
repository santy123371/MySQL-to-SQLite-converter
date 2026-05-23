<?php
/**
 * SQL to SQLite Converter - Web Interface
 *
 * Upload a MySQL .sql file and convert it to SQLite .db format
 */

require_once __DIR__ . '/converter.php';

// Configuration
$maxFileSize = 10 * 1024 * 1024; // 10MB
$warnFileSize = 5 * 1024 * 1024; // 5MB
$uploadDir = __DIR__ . '/upload/';

// Set PHP timeout for large file processing
set_time_limit(300);

$message = '';
$messageType = ''; // 'success' or 'error'
$downloadUrl = '';
$showProgress = false;

// Clean up old temporary files (older than 1 hour)
cleanupTempFiles($uploadDir);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showProgress = true;

    // Validate file upload
    if (!isset($_FILES['sqlFile']) || $_FILES['sqlFile']['error'] === UPLOAD_ERR_NO_FILE) {
        $message = 'No file uploaded.';
        $messageType = 'error';
        $showProgress = false;
    } elseif ($_FILES['sqlFile']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload error: ' . getUploadErrorMessage($_FILES['sqlFile']['error']);
        $messageType = 'error';
        $showProgress = false;
    } else {
        $file = $_FILES['sqlFile'];

        // Validate file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'sql') {
            $message = 'Only .sql files are allowed.';
            $messageType = 'error';
            $showProgress = false;
        }
        // Validate file size
        elseif ($file['size'] > $maxFileSize) {
            $message = 'File exceeds maximum size of 10MB.';
            $messageType = 'error';
            $showProgress = false;
        }
        // Validate non-empty file
        elseif ($file['size'] === 0) {
            $message = 'File is empty.';
            $messageType = 'error';
            $showProgress = false;
        }
        else {
            // Large file warning
            $isLargeFile = $file['size'] > $warnFileSize;

            // Read and convert the SQL file
            $sqlContent = file_get_contents($file['tmp_name']);

            if ($sqlContent === false) {
                $message = 'Error reading uploaded file.';
                $messageType = 'error';
                $showProgress = false;
            } else {
                // Convert SQL
                $result = convertSqlToSqlite($sqlContent);

                if (!$result['success'] || empty($result['sqlite_sql'])) {
                    $message = 'No tables found in the SQL file. The file may be empty or contain only comments.';
                    $messageType = 'error';
                    if (!empty($result['errors'])) {
                        $message .= '<br><small>' . implode('<br>', $result['errors']) . '</small>';
                    }
                    $showProgress = false;
                } else {
                    // Generate SQLite database file
                    $outputFileName = pathinfo($file['name'], PATHINFO_FILENAME) . '.db';
                    $outputPath = $uploadDir . $outputFileName;

                    $dbResult = generateSqliteDb($result['sqlite_sql'], $outputPath);

                    if (!$dbResult['success']) {
                        $message = 'Error generating SQLite database.';
                        if (!empty($dbResult['errors'])) {
                            $message .= '<br><small>' . implode('<br>', $dbResult['errors']) . '</small>';
                        }
                        $messageType = 'error';
                    } else {
                        // Success - prepare download
                        $downloadUrl = 'download.php?file=' . urlencode($outputFileName);
                        $message = $isLargeFile
                            ? 'Conversion successful! Large file processed. Click below to download.'
                            : 'Conversion successful! Click below to download your SQLite database.';
                        $messageType = 'success';
                    }
                }
            }
        }
    }
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode): string
{
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
    ];
    return $errors[$errorCode] ?? 'Unknown upload error.';
}

/**
 * Clean up temporary files older than 1 hour
 */
function cleanupTempFiles(string $dir): void
{
    if (!is_dir($dir)) return;

    $files = glob($dir . '*');
    $oneHourAgo = time() - 3600;

    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $oneHourAgo) {
            @unlink($file);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convertidor SQL a SQLite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
            padding: 48px;
            max-width: 520px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 36px;
        }

        .logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        h1 {
            color: #1a1a2e;
            margin-bottom: 8px;
            font-size: 26px;
            font-weight: 700;
        }

        .subtitle {
            color: #6b7280;
            text-align: center;
            font-size: 15px;
        }

        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .file-input-wrapper {
            position: relative;
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-input-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 32px;
            border: 2px dashed #e5e7eb;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: #f9fafb;
        }

        .file-input-label:hover {
            border-color: #667eea;
            background: #f0f4ff;
            transform: translateY(-2px);
        }

        .file-input-label.has-file {
            border-color: #10b981;
            background: #ecfdf5;
        }

        .file-input-label .icon {
            font-size: 48px;
            margin-bottom: 16px;
            transition: transform 0.3s ease;
        }

        .file-input-label:hover .icon {
            transform: scale(1.1);
        }

        .file-input-label .text {
            color: #6b7280;
            font-size: 15px;
            font-weight: 500;
        }

        .file-input-label .text-small {
            color: #9ca3af;
            font-size: 13px;
            margin-top: 8px;
        }

        .file-input-label .file-name {
            color: #1a1a2e;
            font-weight: 600;
            display: block;
            margin-top: 12px;
            font-size: 15px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.45);
        }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            padding: 20px;
            border-radius: 12px;
            margin-top: 24px;
            font-size: 14px;
            line-height: 1.6;
        }

        .message.success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 14px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
        }

        .download-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.45);
        }

        .progress {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .progress.show {
            display: block;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .submit-btn.loading {
            position: relative;
            color: transparent;
            pointer-events: none;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            margin-left: -12px;
            margin-top: -12px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        .large-warning {
            background: #fffbeb;
            color: #92400e;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer {
            margin-top: 28px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🗄️</div>
            <h1>Convertidor SQL a SQLite</h1>
            <p class="subtitle">Convierte tus archivos .sql de MySQL a formato SQLite</p>
        </div>

        <form class="upload-form" method="post" enctype="multipart/form-data">
            <div class="file-input-wrapper">
                <label for="sqlFile" class="file-input-label" id="fileLabel">
                    <div class="icon">📁</div>
                    <div class="text" id="fileText">Arrastra tu archivo .sql aquí</div>
                    <div class="text-small">o haz clic para seleccionar</div>
                    <input type="file" id="sqlFile" name="sqlFile" accept=".sql" required>
                </label>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">✨ Convertir a SQLite</button>
        </form>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= $message ?>
            <?php if ($downloadUrl): ?>
            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="download-btn">📥 Descargar archivo .db</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Tamaño máximo: 10MB</p>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('sqlFile');
        const fileLabel = document.getElementById('fileLabel');
        const fileText = document.getElementById('fileText');

        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const fileSize = this.files[0].size;
                const sizeMB = (fileSize / (1024 * 1024)).toFixed(2);

                fileLabel.classList.add('has-file');
                fileText.innerHTML = `<span class="file-name">${fileName}</span>(${sizeMB} MB)`;
            } else {
                fileLabel.classList.remove('has-file');
                fileText.textContent = 'Arrastra tu archivo .sql aquí';
            }
        });

        // Drag and drop support
        fileLabel.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#667eea';
            this.style.background = '#f0f4ff';
        });

        fileLabel.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#e5e7eb';
            this.style.background = '#f9fafb';
        });

        fileLabel.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#10b981';
            this.style.background = '#ecfdf5';

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            }
        });

        // Loading state on form submit
        const form = document.querySelector('.upload-form');
        const submitBtn = document.getElementById('submitBtn');
        form.addEventListener('submit', function() {
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Convirtiendo...';
        });
    </script>
</body>
</html>