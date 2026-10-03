<?php
// Luckyware C&C Database Configuration
// PostgreSQL (Supabase Connection Pooler - Transaction Mode)
// Using IPv4 pooler with project-specific hostname for SNI

$db_host = 'mljxejdqoxraqimxjhnn.pooler.supabase.com';  // Project-specific pooler
$db_name = 'postgres';
$db_user = 'postgres.mljxejdqoxraqimxjhnn';  // Full user format for pooler
$db_pass = 'Palette1853141!';
$db_port = 6543;  // Pooler port (NOT 5432)

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
