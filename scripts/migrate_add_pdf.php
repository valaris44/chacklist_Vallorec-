<?php
// Script para adicionar coluna pdf_path na tabela checklists se não existir
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php scripts/migrate_add_pdf.php\n"; exit(1); }
require_once __DIR__ . '/../config/db.php';

$DB_NAME = getenv('DB_NAME') ?: 'checklist_vallourec';

$colExists = $pdo->prepare("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'checklists' AND COLUMN_NAME = 'pdf_path'");
$colExists->execute([$DB_NAME]);
$row = $colExists->fetch();
if ($row && $row['cnt'] > 0) {
    echo "Column pdf_path already exists.\n";
    exit(0);
}

try{
    $pdo->exec("ALTER TABLE checklists ADD COLUMN pdf_path VARCHAR(512) NULL AFTER planilha_path");
    echo "Column pdf_path added.\n";
}catch(Exception $e){
    echo "Error adding column: " . $e->getMessage() . "\n";
    exit(1);
}
