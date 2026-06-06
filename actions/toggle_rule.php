<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$id     = (int)($_POST['rule_id'] ?? 0);
$active = (int)($_POST['active']  ?? 0);

if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid rule ID.']); exit; }

try {
    $db   = getDB();
    $user = currentUser();

    $db->prepare("UPDATE DETECTION_RULE SET is_active = ? WHERE rule_id = ?")->execute([$active, $id]);

    $db->prepare("INSERT INTO AUDIT_LOG (system_user_id,action_type,target_table,target_id,details,timestamp) VALUES (?,?,?,?,?,NOW())")
       ->execute([$user['id'],'rule_toggled','DETECTION_RULE',$id, json_encode(['is_active'=>$active])]);

    echo json_encode(['success'=>true,'message'=>'Rule '.($active ? 'activated' : 'deactivated').'.']);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
