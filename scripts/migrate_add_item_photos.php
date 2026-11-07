<?php
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php scripts/migrate_add_item_photos.php\n"; exit(1); }
require_once __DIR__ . '/../config/db.php';

$colExists = $pdo->prepare("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'checklist_item_photos'");
$colExists->execute([$DB_NAME]);
$row = $colExists->fetch();
if ($row && $row['cnt'] > 0) {
    echo "Table checklist_item_photos already exists.\n";
    exit(0);
}

try{
    $pdo->exec("CREATE TABLE checklist_item_photos (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      item_id BIGINT NOT NULL,
      path VARCHAR(512) NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (item_id) REFERENCES checklist_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table checklist_item_photos created.\n";
}catch(Exception $e){
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
