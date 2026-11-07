<?php
// Endpoint de debug para inspecionar JSON recebido
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$decoded = json_decode($raw, true);
$err = json_last_error();
$errMsg = function_exists('json_last_error_msg') ? json_last_error_msg() : $err;

// Save raw to logs for inspection
@mkdir(__DIR__ . '/../logs', 0755, true);
$fn = __DIR__ . '/../logs/debug_recv_' . date('Ymd_His') . '.json';
file_put_contents($fn, json_encode(['time'=>date('c'),'remote'=>$_SERVER['REMOTE_ADDR'] ?? 'cli','raw'=>$raw], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

$response = ['ok' => true, 'received_length' => strlen($raw), 'json_ok' => ($decoded !== null), 'json_error' => $errMsg, 'log_file' => basename($fn)];
if($decoded !== null) $response['sample_keys'] = array_slice(array_keys($decoded),0,10);

echo json_encode($response, JSON_UNESCAPED_UNICODE);
