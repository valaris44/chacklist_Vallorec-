<?php
// Script CLI para importar schema.sql e sample_data.sql
// Uso: php scripts/import_schema.php

if (php_sapi_name() !== 'cli') {
    echo "Execute este script via linha de comando: php scripts/import_schema.php\n";
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

function runSqlFile($pdo, $path) {
    if (!file_exists($path)) {
        echo "Arquivo não encontrado: $path\n";
        return false;
    }
    echo "Lendo: $path\n";
    $sql = file_get_contents($path);
    // Split by semicolon respeitando comentários simples
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (Exception $e) {
            echo "Erro ao executar statement: " . substr($stmt,0,120) . "...\n";
            echo "Mensagem: " . $e->getMessage() . "\n";
            return false;
        }
    }
    return true;
}

$schemaFile = __DIR__ . '/../schema.sql';
$sampleFile = __DIR__ . '/../data/sample_data.sql';

try{
    echo "Importando schema...\n";
    if(!runSqlFile($pdo, $schemaFile)){
        echo "Falha ao importar schema.\n";
        exit(1);
    }
    echo "Schema importado com sucesso.\n";

    if(file_exists($sampleFile)){
        echo "Inserindo dados de exemplo...\n";
        if(!runSqlFile($pdo, $sampleFile)){
            echo "Falha ao inserir dados de exemplo.\n";
            exit(1);
        }
        echo "Dados de exemplo inseridos com sucesso.\n";
    } else {
        echo "Arquivo de dados de exemplo não encontrado (opcional): $sampleFile\n";
    }

    echo "Pronto. Verifique no phpMyAdmin ou via consulta.\n";
} catch(Exception $e){
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
