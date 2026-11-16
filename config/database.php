<?php
// Secure MySQLi database connection (XAMPP / local MariaDB)

$host = '127.0.0.1'; // Use TCP so it works with XAMPP and Docker alike
$username = 'root';
$password = '';     // Default XAMPP MySQL password is empty
$db_name = 'school_management';
$port = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $db_name, $port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:2rem;max-width:560px;margin:3rem auto;">
        <h2>Database Connection Failed</h2>
        <p>Could not connect to MySQL. Import <code>schema.sql</code> into database <code>school_management</code> and ensure MySQL is running.</p>
        <p style="color:#64748B;font-size:0.9rem;">' . htmlspecialchars($e->getMessage()) . '</p>
        </div>');
}
