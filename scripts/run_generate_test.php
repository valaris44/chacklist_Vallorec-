<?php
// Script de teste para gerar planilha manualmente
// Uso: php scripts/run_generate_test.php

if (php_sapi_name() !== 'cli') {
    echo "Execute via linha de comando: php scripts/run_generate_test.php\n";
    exit(1);
}

$sample = [
    'date' => date('Y-m-d'),
    'area' => 'Sala Elétrica',
    'nome' => 'Teste Automático',
    'matricula' => '00000',
    'setor' => 'POC',
    'responsavel' => 'Supervisor POC',
    'obs_geral' => 'Teste de geração manual',
    'signature' => null,
    'items' => [
        ['section'=>'Painéis Elétricos','description'=>'Verificar temperatura','status'=>'verificado','obs'=>'OK']
    ]
];

$planilha_name = 'checklist_eletrica_'.$sample['matricula'].'_'.str_replace('-','',$sample['date']).'.xlsx';
$planilha_path = __DIR__ . '/../uploads/planilhas/' . $planilha_name;
$json_for_python = sys_get_temp_dir().'/checklist_test_'.uniqid().'.json';
file_put_contents($json_for_python, json_encode(['checklist_id'=>0,'data'=>$sample,'signature_path'=>null,'planilha_path'=>$planilha_path]));

$config = require __DIR__ . '/../config/app.php';
$python = isset($config['python_path']) ? $config['python_path'] : 'python';
$py = escapeshellarg(__DIR__ . '/../python/gerar_planilha.py');
$cmd = escapeshellarg($python) . " $py " . escapeshellarg($json_for_python) . ' 2>&1';

echo "Executando: $cmd\n";
$out = [];
$ret = 0;
exec($cmd, $out, $ret);

echo "Return code: $ret\n";
echo "Output:\n" . implode(PHP_EOL, $out) . "\n";

echo "Verificando se planilha foi criada: $planilha_path\n";
if (file_exists($planilha_path)) {
    echo "Planilha criada com sucesso.\n";
} else {
    echo "Planilha NÃO encontrada.\n";
}

echo "Log JSON: $json_for_python\n";
