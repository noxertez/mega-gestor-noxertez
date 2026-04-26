<?php
require_once 'api/config.php';
$db = conectar();

$mats = $db->query("SELECT * FROM materiales")->fetchAll(PDO::FETCH_ASSOC);

// 1. Rename to TEMP to avoid collisions
foreach ($mats as $i => $m) {
    $old_ref = $m['REF_MAT'];
    $temp_ref = "TEMP_" . $i;
    
    $db->beginTransaction();
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->prepare("UPDATE materiales SET REF_MAT = ? WHERE REF_MAT = ?")->execute([$temp_ref, $old_ref]);
    $db->prepare("UPDATE despiece_articulos SET REF_MAT = ? WHERE REF_MAT = ?")->execute([$temp_ref, $old_ref]);
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    $db->commit();
    $mats[$i]['TEMP_REF'] = $temp_ref;
}

// 2. Rename to FINAL
$global_counter = 1;
foreach ($mats as $m) {
    $temp_ref = $m['TEMP_REF'];
    
    $marca = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $m['MARCA'] ?? 'GEN'), 0, 3));
    $cat   = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $m['CATEGORIA'] ?? 'GEN'), 0, 3));
    $sub   = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $m['SUBCATEGORIA'] ?? 'GEN'), 0, 3));
    $prefix = "M-" . $marca . $cat . $sub;
    
    $new_ref = $prefix . str_pad($global_counter, 4, '0', STR_PAD_LEFT);
    $global_counter++;
    
    echo "Migrating " . $m['REF_MAT'] . " -> $new_ref\n";
    
    $db->beginTransaction();
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    $db->prepare("UPDATE materiales SET REF_MAT = ? WHERE REF_MAT = ?")->execute([$new_ref, $temp_ref]);
    $db->prepare("UPDATE despiece_articulos SET REF_MAT = ? WHERE REF_MAT = ?")->execute([$new_ref, $temp_ref]);
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    $db->commit();
}
