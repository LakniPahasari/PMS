<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$id = (int)($_POST['rule_id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid rule ID.']); exit; }

try {
    $db   = getDB();
    $user = currentUser();

    $check = $db->prepare("SELECT rule_name FROM DETECTION_RULE WHERE rule_id = ? LIMIT 1");
    $check->execute([$id]);
    $rule  = $check->fetch();
    if (!$rule) { echo json_encode(['success'=>false,'message'=>'Rule not found.']); exit; }

    $db->prepare("DELETE FROM DETECTION_RULE WHERE rule_id = ?")->execute([$id]);

    $db->prepare("INSERT INTO AUDIT_LOG (system_user_id,action_type,target_table,target_id,details,timestamp) VALUES (?,?,?,?,?,NOW())")
       ->execute([$user['id'],'rule_deleted','DETECTION_RULE',$id, json_encode(['rule_name'=>$rule['rule_name']])]);

    echo json_encode(['success'=>true,'message'=>"Rule \"{$rule['rule_name']}\" deleted."]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
