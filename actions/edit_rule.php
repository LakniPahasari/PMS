<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$id        = (int)($_POST['rule_id']         ?? 0);
$name      = trim($_POST['rule_name']        ?? '');
$desc      = trim($_POST['description']      ?? '');
$type      = trim($_POST['rule_type']        ?? '');
$appliesTo = trim($_POST['applies_to']       ?? 'all');
$catFilter = trim($_POST['category_filter']  ?? '');
$medFilter = (int)($_POST['stock_id_filter'] ?? 0);
$count     = $_POST['threshold_count']       ?? '';
$days      = $_POST['threshold_days']        ?? '';
$qty       = $_POST['threshold_qty']         ?? '';
$severity  = trim($_POST['severity']         ?? 'warning');

$allowedTypes    = ['patient_frequency','system_frequency','quantity_threshold','staff_volume'];
$allowedApplies  = ['all','category','medicine'];
$allowedSeverity = ['warning','critical'];

if ($id <= 0)                              { echo json_encode(['success'=>false,'message'=>'Invalid rule ID.']); exit; }
if ($name === '')                          { echo json_encode(['success'=>false,'message'=>'Rule name is required.']); exit; }
if (!in_array($type, $allowedTypes))       { echo json_encode(['success'=>false,'message'=>'Please select a rule type.']); exit; }
if (!in_array($appliesTo, $allowedApplies)){ echo json_encode(['success'=>false,'message'=>'Invalid applies-to value.']); exit; }
if (!in_array($severity, $allowedSeverity)){ echo json_encode(['success'=>false,'message'=>'Invalid severity.']); exit; }
if ($appliesTo === 'category' && $catFilter === '') { echo json_encode(['success'=>false,'message'=>'Please select a category.']); exit; }
if ($appliesTo === 'medicine' && $medFilter <= 0)   { echo json_encode(['success'=>false,'message'=>'Please select a medicine.']); exit; }

$needsCountDays = in_array($type, ['patient_frequency','system_frequency','staff_volume']);
$needsQty       = $type === 'quantity_threshold';

if ($needsCountDays) {
    if (!is_numeric($count) || (int)$count < 1) { echo json_encode(['success'=>false,'message'=>'Threshold count must be at least 1.']); exit; }
    if (!is_numeric($days)  || (int)$days  < 1) { echo json_encode(['success'=>false,'message'=>'Within-days must be at least 1.']); exit; }
}
if ($needsQty) {
    if (!is_numeric($qty) || (int)$qty < 1) { echo json_encode(['success'=>false,'message'=>'Maximum units must be at least 1.']); exit; }
}

try {
    $db   = getDB();
    $user = currentUser();

    $check = $db->prepare("SELECT rule_id FROM DETECTION_RULE WHERE rule_id = ? LIMIT 1");
    $check->execute([$id]);
    if (!$check->fetch()) { echo json_encode(['success'=>false,'message'=>'Rule not found.']); exit; }

    $db->prepare("
        UPDATE DETECTION_RULE
        SET rule_name=?, description=?, rule_type=?, applies_to=?,
            category_filter=?, stock_id_filter=?,
            threshold_count=?, threshold_days=?, threshold_qty=?, severity=?
        WHERE rule_id=?
    ")->execute([
        $name,
        $desc    ?: null,
        $type,
        $appliesTo,
        $appliesTo === 'category' ? $catFilter : null,
        $appliesTo === 'medicine' ? $medFilter : null,
        $needsCountDays ? (int)$count : null,
        $needsCountDays ? (int)$days  : null,
        $needsQty       ? (int)$qty   : null,
        $severity,
        $id,
    ]);

    $db->prepare("INSERT INTO AUDIT_LOG (system_user_id,action_type,target_table,target_id,details,timestamp) VALUES (?,?,?,?,?,NOW())")
       ->execute([$user['id'],'rule_updated','DETECTION_RULE',$id, json_encode(['rule_name'=>$name])]);

    echo json_encode(['success'=>true,'message'=>"Rule \"{$name}\" updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
