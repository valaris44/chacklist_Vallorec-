<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');

function checklist_project_relative_path($path){
    $projectRoot = realpath(BASE_DIR);
    $resolvedPath = realpath($path);
    if($projectRoot === false || $resolvedPath === false){
        throw new RuntimeException('Generated file path could not be resolved.');
    }

    $prefix = rtrim(str_replace('\\','/',$projectRoot), '/') . '/';
    $resolvedPath = str_replace('\\','/',$resolvedPath);
    if(stripos($resolvedPath, $prefix) !== 0){
        throw new RuntimeException('Generated file is outside the project directory.');
    }

    return substr($resolvedPath, strlen($prefix));
}

// Ensure any PHP error/exception results in a JSON error response and is logged.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

set_error_handler(function($severity, $message, $file, $line){
    $msg = "[".date('c')."] PHP ERROR: {$message} in {$file}:{$line}\n";
    file_put_contents(LOGS_DIR . '/submit.log', $msg, FILE_APPEND);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'Erro interno no servidor']);
    exit;
});

set_exception_handler(function($e){
    $msg = "[".date('c')."] PHP EXCEPTION: ".$e->getMessage()."\n".$e->getTraceAsString()."\n";
    file_put_contents(LOGS_DIR . '/submit.log', $msg, FILE_APPEND);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'Erro interno no servidor']);
    exit;
});

register_shutdown_function(function(){
    $err = error_get_last();
    if($err){
        $msg = "[".date('c')."] SHUTDOWN ERROR: ".json_encode($err)."\n";
        file_put_contents(LOGS_DIR . '/submit.log', $msg, FILE_APPEND);
        if(!headers_sent()){
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'Erro fatal no servidor']);
        }
    }
});

$raw = file_get_contents('php://input');
if(!$raw){
    http_response_code(400); echo json_encode(['error'=>'Sem payload']); exit;
}
$payload = json_decode($raw, true);
if(json_last_error() !== JSON_ERROR_NONE){
    http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit;
}
$data = $payload['data'] ?? null;
if(!$data || empty($data['date']) || empty($data['nome']) || empty($data['matricula'])){
    http_response_code(400); echo json_encode(['error'=>'Campos obrigatórios faltando']); exit;
}

try{
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO checklists (date, area, nome, matricula, setor, responsavel, obs_geral, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$data['date'], $data['area'] ?? null, $data['nome'], $data['matricula'], $data['setor'] ?? null, $data['responsavel'] ?? null, $data['obs_geral'] ?? null]);
    $checklist_id = $pdo->lastInsertId();

    // handle signature
    $signature_path = null;
    if(!empty($data['signature']) && preg_match('/^data:image\/png;base64,/', $data['signature'])){
        $sigBin = base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$data['signature']));
        $filename = "sig_{$checklist_id}_" . time() . ".png";
        $path = ASSINATURAS_DIR . '/' . $filename;
        file_put_contents($path, $sigBin);
        $signature_path = 'uploads/assinaturas/' . $filename;
        $pdo->prepare("UPDATE checklists SET signature_path = ? WHERE id = ?")->execute([$signature_path, $checklist_id]);
    }

    // items and photos
    $items = $data['items'] ?? [];
    $photo_records = [];
    $idx = 0;
    foreach($items as $it){
        $stmt = $pdo->prepare("INSERT INTO checklist_items (checklist_id, description, status, obs) VALUES (?, ?, ?, ?)");
        $stmt->execute([$checklist_id, $it['description'] ?? null, $it['status'] ?? null, $it['obs'] ?? null]);
        $item_id = $pdo->lastInsertId();

        // photos (data URLs)
        $photos = $it['photos'] ?? [];
        // ensure we attach saved file paths back into the original payload for Python consumption
        if(!isset($data['items'][$idx]['photos'])) $data['items'][$idx]['photos'] = [];
        $item_photo_dir = EVIDENCIAS_DIR . "/{$checklist_id}/{$item_id}";
        if(!is_dir($item_photo_dir)) @mkdir($item_photo_dir, 0777, true);
        $n = 1;
        foreach($photos as $p){
            if(!preg_match('/^data:image\\/(\w+);base64,/', $p, $m)) continue;
            $ext = strtolower($m[1])==='jpeg' ? 'jpg' : strtolower($m[1]);
            $filename = "photo_{$n}." . $ext;
            $bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$p));
            $fpath = "{$item_photo_dir}/{$filename}";
            file_put_contents($fpath, $bin);
            $rel = "uploads/evidencias/{$checklist_id}/{$item_id}/{$filename}";
            $pdo->prepare("INSERT INTO checklist_item_photos (item_id, path) VALUES (?, ?)")->execute([$item_id, $rel]);
            $n++;
            $photo_records[] = $rel;
            // push server-relative path back into the payload so Python scripts receive it
            $data['items'][$idx]['photos'][] = $rel;
        }
        $idx++;
    }

    $pdo->commit();

    // prepare temp JSON for Python scripts
    $tmpfile = TMP_DIR . "/checklist_{$checklist_id}_" . time() . ".json";
    $out = [
        'checklist_id' => (int)$checklist_id,
        'data' => $data,
        'signature_path' => $signature_path ? realpath(UPLOADS_DIR . '/assinaturas/' . basename($signature_path)) : null,
    ];
    file_put_contents($tmpfile, json_encode($out));
    file_put_contents(LOGS_DIR . '/submit.log', "---- ".date('c')." ----\nJSON for Python: {$tmpfile}\n", FILE_APPEND);

    // Ensure exec available
    if(!function_exists('exec') && !function_exists('shell_exec')){
        $msg = 'Função exec/shell_exec desabilitada; habilite para geração automática de planilhas/PDF.';
        file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] ERROR: $msg\n", FILE_APPEND);
        http_response_code(500); echo json_encode(['error'=>$msg]); exit;
    }

    // 1) gerar planilha (keep existing behavior)
    $planCmd = PYTHON_BIN . ' ' . escapeshellarg(__DIR__ . '/../python/gerar_planilha.py') . ' ' . escapeshellarg($tmpfile) . ' 2>&1';
    file_put_contents(LOGS_DIR . '/submit.log', "Exec planilha: {$planCmd}\n", FILE_APPEND);
    exec($planCmd, $planOut, $planRet);
    file_put_contents(LOGS_DIR . '/submit.log', "gerar_planilha output:\n".implode("\n",$planOut)."\nreturn:{$planRet}\n", FILE_APPEND);

    // 2) gerar PDF com Python (preferred)
    $pdfPath = null;
    $pdfCmd = PYTHON_BIN . ' ' . escapeshellarg(__DIR__ . '/../python/gerar_pdf.py') . ' ' . escapeshellarg($tmpfile) . ' 2>&1';
    file_put_contents(LOGS_DIR . '/submit.log', "Exec PDF: {$pdfCmd}\n", FILE_APPEND);
    exec($pdfCmd, $pdfOut, $pdfRet);
    $pdfFull = implode("\n", $pdfOut);
    file_put_contents(LOGS_DIR . '/submit.log', "gerar_pdf output:\n{$pdfFull}\nret={$pdfRet}\n", FILE_APPEND);

    // parse structured RESULT_JSON from python output
    if(preg_match('/RESULT_JSON:\\s*(\{.*\})/s', $pdfFull, $m)){
        $res = json_decode($m[1], true);
        if(isset($res['status']) && $res['status'] === 'ok' && !empty($res['pdf_path'])){
            $pdfPath = $res['pdf_path'];
        } else {
            // python reported an error (dependencies or other)
            $hintMsg = isset($res['message']) ? $res['message'] : 'Erro não especificado pelo gerador Python';
            file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] gerar_pdf.py reported error: {$hintMsg}\n", FILE_APPEND);
        }
    } else {
        // If python program writes old-style message we also try to find a path substring
        if(preg_match('/uploads\/pdfs\/[^\s]+\.pdf/i', $pdfFull, $mm)){
            $pdfPath = $mm[0];
        } else {
            file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] gerar_pdf.py did not return RESULT_JSON or pdf path\n", FILE_APPEND);
        }
    }

    // If Python failed to produce PDF -> PHP fallback generator (includes signature if available)
    if(empty($pdfPath)){
        file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] PDF via Python failed; using PHP fallback generator\n", FILE_APPEND);

        function pdf_fallback_create($data, $checklist_id, $signature_path = null){
            // Single page A4 PDF with text listing and optional embedded signature (JPEG via GD)
            $pdf_name = "checklist_php_{$checklist_id}_" . date('Ymd_His') . ".pdf";
            $out = PDFS_DIR . '/' . $pdf_name;

            $lines = [];
            $lines[] = "Checklist Elétrica – Finishing";
            $lines[] = "Data: " . ($data['date'] ?? '');
            $lines[] = "Área: " . ($data['area'] ?? '');
            $lines[] = "Colaborador: " . ($data['nome'] ?? '') . " (Mat.: " . ($data['matricula'] ?? '') . ")";
            $lines[] = "Setor: " . ($data['setor'] ?? '') . " • Responsável: " . ($data['responsavel'] ?? '');
            $lines[] = "";
            $lines[] = "Itens:";
            foreach($data['items'] ?? [] as $it){
                $status = $it['status'] ?: 'Pendente';
                $desc = $it['description'] ?? '';
                $lines[] = "- {$desc} [{$status}]";
                if(!empty($it['obs'])) $lines[] = "  Obs: " . $it['obs'];
            }
            $lines[] = "";
            $lines[] = "Observações gerais:";
            $lines[] = $data['obs_geral'] ?? '';

            // Prepare signature JPEG binary if available
            $hasImage = false;
            $jpegData = null;
            $imgW = $imgH = 0;
            if($signature_path && file_exists($signature_path) && function_exists('imagecreatefromstring')){
                $sigRaw = file_get_contents($signature_path);
                $im = @imagecreatefromstring($sigRaw);
                if($im !== false){
                    ob_start();
                    imagejpeg($im, NULL, 80);
                    $jpegData = ob_get_clean();
                    $imgW = imagesx($im);
                    $imgH = imagesy($im);
                    imagedestroy($im);
                    if($jpegData) $hasImage = true;
                }
            }

            // Build PDF objects (simple)
            $objects = [];
            // 1 Catalog & Pages
            $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
            $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
            // 3 Page
            $resources = "<< /Font << /F1 4 0 R >>";
            if($hasImage) $resources .= " /XObject << /Im0 6 0 R >>";
            $resources .= " >>";
            $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources $resources /MediaBox [0 0 595 842] /Contents 5 0 R >>\nendobj\n";
            // 4 Font
            $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

            // content stream: text lines and optionally image drawing at bottom-right
            $content = "BT /F1 12 Tf 40 800 Td\n";
            foreach($lines as $ln){
                // escape parentheses
                $safe = str_replace(['\\','(' ,')'], ['\\\\','\\(', '\\)'], $ln);
                $content .= " (" . $safe . ") Tj T*\n";
            }
            if($hasImage){
                // place image with max width 160 points and maintain ratio, at x=380 y=60
                $maxw = 160; $scale = min(1, $maxw / max(1, $imgW));
                $drawW = intval($imgW * $scale);
                $drawH = intval($imgH * $scale);
                $x = 380; $y = 60;
                $content .= "ET\nq\n{$drawW} 0 0 {$drawH} {$x} {$y} cm /Im0 Do\nQ\n";
            } else {
                $content .= "ET\n";
            }

            $stream = $content;
            $objects[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";

            // image object (if present) as DCTDecode (JPEG)
            if($hasImage){
                $imgLen = strlen($jpegData);
                $objImg = "6 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imgW} /Height {$imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$imgLen} >>\nstream\n";
                $objects[] = $objImg . $jpegData . "\nendstream\nendobj\n";
            }

            // assemble file with offsets
            $header = "%PDF-1.3\n";
            $body = '';
            $xref = [];
            $pos = strlen($header);
            foreach($objects as $o){
                $xref[] = $pos;
                $body .= $o;
                $pos += strlen($o);
            }
            $xref_pos = strlen($header) + strlen($body);
            $file = $header . $body;
            $file .= "xref\n0 " . (count($objects)+1) . "\n0000000000 65535 f \n";
            foreach($xref as $off){
                $file .= sprintf("%010d 00000 n \n", $off);
            }
            $file .= "trailer\n<< /Size " . (count($objects)+1) . " /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF";

            if(@file_put_contents($out, $file) === false) return false;
            return $out;
        }

        $fallback_pdf = pdf_fallback_create($data, $checklist_id, $signature_path ?? null);
        if($fallback_pdf && file_exists($fallback_pdf)){
            // store DB relative path
            $relPdf = checklist_project_relative_path($fallback_pdf);
            $pdo->prepare("UPDATE checklists SET pdf_path = ? WHERE id = ?")->execute([$relPdf, $checklist_id]);
            file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] PDF fallback created: {$fallback_pdf}\n", FILE_APPEND);

            // try to send email via enviar_email.py first (non-fatal)
            $email_ok = false;
            $emailCmd = PYTHON_BIN . ' ' . escapeshellarg(__DIR__ . '/../python/enviar_email.py') . ' ' . escapeshellarg($relPdf) . ' ' . escapeshellarg(DEFAULT_EMAIL) . ' 2>&1';
            exec($emailCmd, $mailOut, $mailRet);
            $mailFull = implode("\n", $mailOut);
            file_put_contents(LOGS_DIR . '/submit.log', "enviar_email output (fallback):\n{$mailFull}\nret={$mailRet}\n", FILE_APPEND);
            if(preg_match('/RESULT_JSON:\\s*(\{.*\})/s', $mailFull, $mm)){
                $mres = json_decode($mm[1], true);
                $email_ok = (isset($mres['status']) && $mres['status'] === 'ok');
            }

            // if email not ok, try PHP mail()
            if(!$email_ok && DEFAULT_EMAIL !== ''){
                // build multipart email with attachments
                $to = DEFAULT_EMAIL;
                $subject = 'Checklist Elétrica – Vallourec';
                $message = "O checklist foi gerado automaticamente. Em anexo: relatório PDF.\n\nChecklist ID: {$checklist_id}";
                $boundary = "==CKBNDY_" . md5(uniqid('', true));
                $headers = "From: no-reply@vallourec.local\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $body = "--{$boundary}\r\nContent-Type: text/plain; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 7bit\r\n\r\n{$message}\r\n";

                // attach PDF
                $pdf_data = file_get_contents($fallback_pdf);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/pdf; name=\"" . basename($fallback_pdf) . "\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"" . basename($fallback_pdf) . "\"\r\n\r\n";
                $body .= chunk_split(base64_encode($pdf_data)) . "\r\n";

                // attach signature if exists
                if(!empty($signature_path) && file_exists($signature_path)){
                    $sigdata = file_get_contents($signature_path);
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Type: application/octet-stream; name=\"" . basename($signature_path) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . basename($signature_path) . "\"\r\n\r\n";
                    $body .= chunk_split(base64_encode($sigdata)) . "\r\n";
                }

                // attach planilha if present
                if(!empty($planilha_path) && file_exists(realpath($planilha_path))){
                    $xlsdata = file_get_contents(realpath($planilha_path));
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Type: application/octet-stream; name=\"" . basename($planilha_path) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . basename($planilha_path) . "\"\r\n\r\n";
                    $body .= chunk_split(base64_encode($xlsdata)) . "\r\n";
                }

                $body .= "--{$boundary}--";
                $sent = @mail($to, $subject, $body, $headers);
                file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] PHP mail() sent=".(int)$sent." to={$to}\n", FILE_APPEND);
            }

            $pdf_download = "../" . str_replace('\\','/',$relPdf);
            $resp = ['success'=>true, 'id'=>$checklist_id, 'pdf_download'=>$pdf_download];
            if(isset($sent) && !$sent) $resp['warning'] = 'PDF gerado, mas envio por mail() falhou. Veja logs.';
            echo json_encode($resp);
            exit;
        } else {
            file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] PDF fallback generation failed\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error'=>'Falha ao gerar PDF. Consulte logs/submit.log']);
            exit;
        }
    }

    // If we have $pdfPath from Python, update DB and attempt email (with fallback)
    $relPdf = checklist_project_relative_path($pdfPath);
    $pdo->prepare("UPDATE checklists SET pdf_path = ? WHERE id = ?")->execute([$relPdf, $checklist_id]);

    // Try enviar_email.py (preferred) and fallback to PHP mail() if needed
    $emailCmd = PYTHON_BIN . ' ' . escapeshellarg(__DIR__ . '/../python/enviar_email.py') . ' ' . escapeshellarg($relPdf) . ' ' . escapeshellarg(DEFAULT_EMAIL) . ' 2>&1';
    exec($emailCmd, $mailOut, $mailRet);
    $mailFull = implode("\n", $mailOut);
    file_put_contents(LOGS_DIR . '/submit.log', "enviar_email output:\n{$mailFull}\nret={$mailRet}\n", FILE_APPEND);
    $email_ok = false;
    if(preg_match('/RESULT_JSON:\\s*(\{.*\})/s', $mailFull, $mm)){
        $mres = json_decode($mm[1], true);
        $email_ok = (isset($mres['status']) && $mres['status'] === 'ok');
    }
    if(!$email_ok && DEFAULT_EMAIL !== ''){
        // fallback to PHP mail() (attach PDF and signature if present)
        $to = DEFAULT_EMAIL;
        $subject = 'Checklist Elétrica – Vallourec';
        $message = "O checklist foi gerado automaticamente. Em anexo: relatório PDF.\n\nChecklist ID: {$checklist_id}";
        $boundary = "==CKBNDY_" . md5(uniqid('', true));
        $headers = "From: no-reply@vallourec.local\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 7bit\r\n\r\n{$message}\r\n";

        // attach PDF
        $pdf_file = realpath(__DIR__ . '/../' . $relPdf);
        if($pdf_file && file_exists($pdf_file)){
            $pdf_data = file_get_contents($pdf_file);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"" . basename($pdf_file) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . basename($pdf_file) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($pdf_data)) . "\r\n";
        }

        // attach signature if exists
        if($signature_path && file_exists($signature_path)){
            $sigdata = file_get_contents($signature_path);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"" . basename($signature_path) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . basename($signature_path) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($sigdata)) . "\r\n";
        }

        $body .= "--{$boundary}--";
        $sent = @mail($to, $subject, $body, $headers);
        file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] PHP mail() sent=".(int)$sent." to={$to}\n", FILE_APPEND);
    }

    $pdf_download = "../" . $relPdf;
    $resp = ['success'=>true,'id'=>$checklist_id,'pdf_download'=>$pdf_download];
    if(isset($sent) && !$sent) $resp['warning'] = 'PDF gerado mas envio de email falhou. Consulte logs/submit.log';
    echo json_encode($resp);
    exit;

} catch(Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    file_put_contents(LOGS_DIR . '/submit.log', "[".date('c')."] EXCEPTION: ".$e->getMessage()."\nRawPayload: ".$raw."\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error'=>'Erro interno: '.$e->getMessage()]);
    exit;
}
