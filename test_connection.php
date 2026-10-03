<?php
// Diagnostic script to check database connection
header('Content-Type: text/plain');
echo "=== NEON POSTGRESQL CONNECTION TEST ===\n\n";

// Check if environment variables exist
echo "Environment Variables:\n";
echo "PGHOST: " . (getenv('PGHOST') ?: 'NOT SET') . "\n";
echo "PGDATABASE: " . (getenv('PGDATABASE') ?: 'NOT SET') . "\n";
echo "PGUSER: " . (getenv('PGUSER') ?: 'NOT SET') . "\n";
echo "PGPASSWORD: " . (getenv('PGPASSWORD') ? '***SET***' : 'NOT SET') . "\n\n";

// Get values
$db_host = getenv('PGHOST');
$db_name = getenv('PGDATABASE');
$db_user = getenv('PGUSER');
$db_pass = getenv('PGPASSWORD');

if (!$db_host || !$db_name || !$db_user || !$db_pass) {
    echo "ERROR: One or more environment variables are missing!\n";
    echo "Please check Render environment settings.\n";
    exit;
}

// Try to connect
echo "Attempting connection...\n";
try {
    $dsn = "pgsql:host=$db_host;dbname=$db_name";
    echo "DSN: $dsn\n";
    
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "\n✅ SUCCESS! Connected to PostgreSQL!\n\n";
    
    // Test query
    echo "Testing tables...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients");
    $count = $stmt->fetchColumn();
    echo "✅ clients table exists - $count records\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM commands");
    $count = $stmt->fetchColumn();
    echo "✅ commands table exists - $count records\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM logs");
    $count = $stmt->fetchColumn();
    echo "✅ logs table exists - $count records\n";
    
    echo "\n✅ ALL TESTS PASSED! Database is working correctly!\n";
    
} catch(PDOException $e) {
    echo "\n❌ CONNECTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPossible issues:\n";
    echo "1. Environment variables not set correctly in Render\n";
    echo "2. Neon database not accessible\n";
    echo "3. Wrong credentials\n";
}
?>
