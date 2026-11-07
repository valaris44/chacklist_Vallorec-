<?php
// Serve planilha files safely by basename checking
require_once __DIR__ . '/../config/app.php';

if (empty($_GET['file']) || !is_string($_GET['file'])) { http_response_code(400); echo 'Missing file parameter'; exit; }
$file = $_GET['file'];
$basename = basename($file);

$allowed_dirs = [PLANILHAS_DIR, PDFS_DIR, ASSINATURAS_DIR];
$found = null;
foreach($allowed_dirs as $d){
    $path = $d . DIRECTORY_SEPARATOR . $basename;
    if(is_file($path)){ $found = $path; break; }
}

$evidence_root = realpath(EVIDENCIAS_DIR);
if (!$found && $evidence_root !== false) {
    $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    $candidate = realpath($evidence_root . DIRECTORY_SEPARATOR . $relative);
    $evidence_prefix = $evidence_root . DIRECTORY_SEPARATOR;
    if ($candidate !== false && strncmp($candidate, $evidence_prefix, strlen($evidence_prefix)) === 0 && is_file($candidate)) {
        $found = $candidate;
    }
}

if(!$found){ http_response_code(404); echo "Not found"; exit; }

$mime = function_exists('mime_content_type') ? mime_content_type($found) : null;
if(!$mime){
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12'
    ];
    $mime = $mimeTypes[strtolower(pathinfo($found, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($found) . '"');
readfile($found);
exit;
