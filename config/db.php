<?php
// Config de conexão PDO - ajustar conforme ambiente
// Valores podem vir de variáveis de ambiente (recomendado) ou ficar hardcoded abaixo
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'checklist_vallourec';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

// DSN com porta
$dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";

try{
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB connection failed: '.$e->getMessage()]);
    exit;
}
