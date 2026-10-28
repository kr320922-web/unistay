
<?php
// Example database configuration for UniStay
// Copy this file as db.php and enter your own credentials.

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect(
    "127.0.0.1",       // Database host
    "YOUR_DB_USER",    // Database username
    "YOUR_DB_PASSWORD",// Database password
    "unistay_db",      // Database name
    3307               // Database port (change if needed)
);

if (!$conn) {
    die("Database connection failed. Please check your database settings.");
}
?>
