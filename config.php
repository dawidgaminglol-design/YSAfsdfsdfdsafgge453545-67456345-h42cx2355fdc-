<?php
// Luckyware C&C Database Configuration
// PostgreSQL (Supabase)

$db_host = 'db.mljxejdqoxraqimxjhnn.supabase.co';
$db_name = 'postgres';
$db_user = 'postgres';
$db_pass = 'Palette1853141!';
$db_port = 5432;

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
