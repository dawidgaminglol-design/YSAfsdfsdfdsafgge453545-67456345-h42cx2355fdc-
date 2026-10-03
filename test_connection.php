<?php
// Test Supabase connection with different pooler regions
header('Content-Type: text/plain');

$password = 'Palette1853141!';
$poolers = [
    'aws-0-us-east-1.pooler.supabase.com',
    'aws-0-us-west-1.pooler.supabase.com',
    'aws-0-us-west-2.pooler.supabase.com',
    'aws-0-eu-central-1.pooler.supabase.com',
    'aws-0-eu-west-1.pooler.supabase.com',
    'aws-0-eu-west-2.pooler.supabase.com',
    'aws-0-ap-southeast-1.pooler.supabase.com',
    'aws-0-ap-northeast-1.pooler.supabase.com',
];

echo "Testing Supabase Connection Poolers...\n";
echo "=========================================\n\n";

foreach($poolers as $host) {
    echo "Testing: $host:6543\n";
    
    try {
        $dsn = "pgsql:host=$host;port=6543;dbname=postgres";
        $pdo = new PDO($dsn, 'postgres', $password, [  // Changed from postgres.mljxejdqoxraqimxjhnn
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        
        $result = $pdo->query("SELECT version()")->fetch();
        echo "✅ SUCCESS! PostgreSQL: " . substr($result[0], 0, 50) . "...\n";
        echo "👉 USE THIS HOST: $host\n\n";
        break;
        
    } catch(PDOException $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    }
}

echo "=========================================\n";
echo "Testing direct connection (IPv4 fallback)...\n";
echo "Host: db.mljxejdqoxraqimxjhnn.supabase.co:5432\n";

try {
    $dsn = "pgsql:host=db.mljxejdqoxraqimxjhnn.supabase.co;port=5432;dbname=postgres";
    $pdo = new PDO($dsn, 'postgres', $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
    
    $result = $pdo->query("SELECT version()")->fetch();
    echo "✅ DIRECT CONNECTION WORKS!\n";
    echo "PostgreSQL: " . substr($result[0], 0, 50) . "...\n";
    
} catch(PDOException $e) {
    echo "❌ Direct connection failed: " . $e->getMessage() . "\n";
}
?>
