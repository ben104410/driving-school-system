<?php
// =============================================================
// Database Configuration
// Online Driving School Management System
// Case Study: AA Driving School - Mombasa Branch
// =============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'driving_school_db');
define('SITE_NAME', 'AA Driving School - Mombasa');
define('SITE_TAGLINE', 'Driving School Management System');
define('CURRENCY', 'KES');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If DB connection fails, show a clean message with setup instructions
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center min-vh-100">
        <div class="container" style="max-width: 600px;">
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Database Connection Error</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Unable to connect to MySQL database <code>' . DB_NAME . '</code>.</p>
                    <h6>How to solve this in XAMPP:</h6>
                    <ol class="small">
                        <li>Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to MySQL and Apache.</li>
                        <li>Open your browser and visit <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a>.</li>
                        <li>Create a new database named <code>' . DB_NAME . '</code>.</li>
                        <li>Click <strong>Import</strong> and select the <code>database.sql</code> file included in this project folder.</li>
                        <li>Reload this page!</li>
                    </ol>
                    <hr>
                    <small class="text-danger">Technical details: ' . htmlspecialchars($e->getMessage()) . '</small>
                </div>
            </div>
        </div>
    </body>
    </html>
    ');
}
?>
