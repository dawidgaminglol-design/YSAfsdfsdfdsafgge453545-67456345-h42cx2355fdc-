<?php
// Luckyware C&C Database Configuration
// MySQL (InfinityFree)

// IMPORTANT: Change the database name from 'luckyware' to your actual database name
// Go to InfinityFree control panel → MySQL Databases → use the name listed there
// It will be like: if0_43071029_luckyware or if0_43071029_XXX

$db_host = 'sql113.infinityfree.com';
$db_name = 'if0_43071029_luckyware';  // ⚠️ CHANGE THIS to your actual database name!
$db_user = 'if0_43071029';
$db_pass = '77oUN0U6Fp';
$db_port = 3306;

// Create PDO connection
function getDB() {
    global $db_host, $db_name, $db_user, $db_pass, $db_port;
    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
