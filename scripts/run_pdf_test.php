<?php
// Script to test PDF generation via gerar_pdf.py
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php scripts/run_pdf_test.php\n"; exit(1); }

$sample = [
    'date' => date('Y-m-d'),
    'area' => 'Sala Elétrica',
    'nome' => 'Teste PDF',
    'matricula' => '00001',
    'setor' => 'POC',
    'responsavel' => 'Supervisor POC',
    'obs_geral' => 'Teste de geração PDF com evidências',
    'signature' => null,
    'items' => [
        ['section'=>'Painéis Elétricos','description'=>'Verificar temperatura','status'=>'conforme','obs'=>'Dentro do limite','photos'=>[]]
    ]
];

$planilha_name = 'checklist_eletrica_'.$sample['matricula'].'_'.str_replace('-','',$sample['date']).'.xlsx';
$planilha_path = __DIR__ . '/../uploads/planilhas/' . $planilha_name;
$json_for_python = sys_get_temp_dir().'/checklist_test_'.uniqid().'.json';
file_put_contents($json_for_python, json_encode(['checklist_id'=>0,'data'=>$sample,'signature_path'=>null,'planilha_path'=>$planilha_path]));

$config = require __DIR__ . '/../config/app.php';
$python = isset($config['python_path']) ? $config['python_path'] : 'python';
$py = escapeshellarg(__DIR__ . '/../python/gerar_pdf.py');
$cmd = escapeshellarg($python) . " $py " . escapeshellarg($json_for_python) . ' 2>&1';

echo "Executando: $cmd\n";
$out = [];
$ret = 0;
exec($cmd, $out, $ret);

echo "Return code: $ret\n";
echo "Output:\n" . implode(PHP_EOL, $out) . "\n";

$base = 'checklist_eletrica_'.$sample['matricula'].'_'.str_replace('-','',$sample['date']);
$pdf = __DIR__ . '/../uploads/pdfs/' . $base . '.pdf';
if (file_exists($pdf)) echo "PDF criado: $pdf\n"; else echo "PDF nao encontrado.\n";

echo "JSON: $json_for_python\n";