<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$name      = trim($_POST['rule_name']       ?? '');
$desc      = trim($_POST['description']     ?? '');
$type      = trim($_POST['rule_type']       ?? '');
$appliesTo = trim($_POST['applies_to']      ?? 'all');
$catFilter = trim($_POST['category_filter'] ?? '');
$medFilter = (int)($_POST['stock_id_filter']?? 0);
$count     = $_POST['threshold_count']      ?? '';
$days      = $_POST['threshold_days']       ?? '';
$qty       = $_POST['threshold_qty']        ?? '';
$severity  = trim($_POST['severity']        ?? 'warning');

$allowedTypes     = ['patient_frequency','system_frequency','quantity_threshold','staff_volume'];
$allowedApplies   = ['all','category','medicine'];
$allowedSeverity  = ['warning','critical'];

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

    $db->prepare("
        INSERT INTO DETECTION_RULE
            (rule_name, description, rule_type, applies_to, category_filter, stock_id_filter,
             threshold_count, threshold_days, threshold_qty, severity, is_active, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,1,?)
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
        $user['id'],
    ]);

    $newId = $db->lastInsertId();
    $db->prepare("INSERT INTO AUDIT_LOG (system_user_id,action_type,target_table,target_id,details,timestamp) VALUES (?,?,?,?,?,NOW())")
       ->execute([$user['id'],'rule_created','DETECTION_RULE',$newId, json_encode(['rule_name'=>$name,'rule_type'=>$type])]);

    echo json_encode(['success'=>true,'message'=>"Rule \"{$name}\" created successfully."]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
