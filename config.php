<?php
// Luckyware C&C Database Configuration
// PostgreSQL (Supabase Connection Pooler)

$db_host = 'aws-0-us-east-1.pooler.supabase.com';
$db_name = 'postgres';
$db_user = 'postgres.mljxejdqoxraqimxjhnn';
$db_pass = 'Palette1853141!';
$db_port = 6543;

// Create PDO connection
function getDB() {
    global $db_host, $db_name, $db_user, $db_pass, $db_port;
    try {
        $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
