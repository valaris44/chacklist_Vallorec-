<?php
// Test DB connection quick script
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/plain');

try{
    $stmt = $pdo->query('SELECT 1 as ok');
    $row = $stmt->fetch();
    echo "Conexão OK\n";
    echo "Host: " . ($DB_HOST ?? 'n/a') . "\n";
    echo "Database: " . ($DB_NAME ?? 'n/a') . "\n";
    echo "Port: " . ($DB_PORT ?? 'n/a') . "\n";
    echo "Query result: "; var_export($row);
}catch(Exception $e){
    http_response_code(500);
    echo "Erro ao conectar: " . $e->getMessage();
}
