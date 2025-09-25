<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';

$estudiante = isset($_GET['estudiante']) ? (int)$_GET['estudiante'] : 0;
$habilidad = isset($_GET['habilidad']) ? (int)$_GET['habilidad'] : 0;
$actividad = isset($_GET['actividad']) ? (int)$_GET['actividad'] : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

if ($estudiante>0 && $habilidad>0 && $actividad>0 && in_array($accion,['mas','menos'])) {
  $valor = ($accion==='mas') ? 1 : -1;
  $conn->begin_transaction();
  try {
    $stmt=$conn->prepare("UPDATE puntuaciones SET puntaje = puntaje + ? WHERE estudiante_id=? AND habilidad_id=? AND actividad_id=?");
    $stmt->bind_param("iiii",$valor,$estudiante,$habilidad,$actividad);
    $stmt->execute();
    if ($stmt->affected_rows===0) {
      $stmt2=$conn->prepare("INSERT INTO puntuaciones (estudiante_id, actividad_id, habilidad_id, puntaje) VALUES (?, ?, ?, ?)");
      $stmt2->bind_param("iiii",$estudiante,$actividad,$habilidad,$valor);
      $stmt2->execute();
    }
    $conn->commit();
  } catch(Exception $e){ $conn->rollback(); }
}
header("Location: puntuar_estudiante.php?id=".$estudiante."&actividad_id=".$actividad);
exit;
