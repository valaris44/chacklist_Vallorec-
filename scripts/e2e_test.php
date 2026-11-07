<?php
// Simple E2E test that posts a sample checklist to the API endpoint
$host = getenv('TEST_HOST') ?: 'http://127.0.0.1';
$base = getenv('TEST_BASE') ?: '';
$url = rtrim($host, '/') . $base . '/api/checklist_submit.php';

// Small 1x1 PNG base64 to simulate signature
$dummy_sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HwAFAgH+Qb/RAAAAAElFTkSuQmCC';

$payload = ['data' => [
    'date'=>date('Y-m-d'),
    'area'=>'Sala Elétrica',
    'nome'=>'E2E Test',
    'matricula'=>'99999',
    'setor'=>'POC',
    'responsavel'=>'Supervisor',
    'obs_geral'=>'Teste E2E',
    'signature' => $dummy_sig,
    'items' => [[ 'section'=>'Painéis','description'=>'Teste','status'=>'verificado','obs'=>'OK','photos'=>[$dummy_sig] ]]
]];

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($payload),
        'timeout' => 60
    ]
];
$context  = stream_context_create($options);
$result = @file_get_contents($url, false, $context);
if ($result === false) {
    echo "Request failed. Check server and URL: $url\n";
    if(isset($http_response_header)) echo implode("\n", $http_response_header);
    exit(1);
}

echo "Response:\n" . $result . "\n";