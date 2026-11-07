<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (empty($_GET['id'])) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing id']); exit; }
$id = (int)$_GET['id'];
$stmtChecklist = $pdo->prepare('SELECT id, date, area, nome, matricula, setor, responsavel, obs_geral, pdf_path FROM checklists WHERE id = ?');
$stmtChecklist->execute([$id]);
$checklist = $stmtChecklist->fetch();
if (!$checklist) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Checklist not found']); exit; }

// Fetch items
$stmt = $pdo->prepare('SELECT id, description, status, obs FROM checklist_items WHERE checklist_id = ? ORDER BY id ASC');
$stmt->execute([$id]);
$items = [];
$baseEvid = realpath(__DIR__ . '/../uploads/evidencias');
$evidencePrefix = $baseEvid ? rtrim(str_replace('\\','/',$baseEvid), '/') . '/' : null;
$stmtPhotos = $pdo->prepare('SELECT path FROM checklist_item_photos WHERE item_id = ? ORDER BY id ASC');
while($r = $stmt->fetch()){
    $stmtPhotos->execute([$r['id']]);
    $photos = [];
    while($p = $stmtPhotos->fetch()){
        $storedPath = str_replace('\\','/',$p['path']);
        $photoPath = realpath(__DIR__ . '/../' . ltrim($storedPath, '/'));
        $normalizedPhotoPath = $photoPath ? str_replace('\\','/',$photoPath) : null;
        if ($evidencePrefix && $normalizedPhotoPath && stripos($normalizedPhotoPath, $evidencePrefix) === 0){
            $rel = substr($normalizedPhotoPath, strlen($evidencePrefix));
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/api/checklist_detail.php')), '/');
            $photos[] = [ 'path' => $rel, 'download' => $scheme . '://' . $host . $scriptDir . '/checklist_download.php?file=' . rawurlencode($rel) ];
        } else {
            $photos[] = [ 'path' => basename($storedPath), 'download' => null ];
        }
    }
    $items[] = ['id'=>$r['id'],'description'=>$r['description'],'status'=>$r['status'],'obs'=>$r['obs'],'photos'=>$photos];
}

$checklist['items'] = $items;
echo json_encode(['success'=>true,'data'=>$checklist]);
