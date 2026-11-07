<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query("SELECT id, date, area, nome, matricula, pdf_path FROM checklists ORDER BY created_at DESC LIMIT 100");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = array_map(function($r){
        return [
            'id'=>$r['id'],
            'date'=>$r['date'],
            'area'=>$r['area'],
            'nome'=>$r['nome'],
            'matricula'=>$r['matricula'],
            'pdf_download'=> $r['pdf_path'] ? '../' . $r['pdf_path'] : null
        ];
    }, $rows);
    echo json_encode(['data'=>$data]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
