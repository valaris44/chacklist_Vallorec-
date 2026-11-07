<?php
// Script para adicionar coluna planilha_path na tabela checklists se não existir
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php scripts/migrate_add_planilha.php\n"; exit(1); }
require_once __DIR__ . '/../config/db.php';

$colExists = $pdo->prepare("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'checklists' AND COLUMN_NAME = 'planilha_path'");
$colExists->execute([$DB_NAME]);
$row = $colExists->fetch();
if ($row && $row['cnt'] > 0) {
    echo "Column planilha_path already exists.\n";
    exit(0);
}

try{
    $pdo->exec("ALTER TABLE checklists ADD COLUMN planilha_path VARCHAR(512) NULL AFTER signature_path");
    echo "Column planilha_path added.\n";
}catch(Exception $e){
    echo "Error adding column: " . $e->getMessage() . "\n";
    exit(1);
}
