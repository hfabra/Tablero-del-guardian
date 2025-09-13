<?php
require_once 'includes/db.php';

$estudiante = isset($_GET['estudiante']) ? (int)$_GET['estudiante'] : 0;
$habilidad = isset($_GET['habilidad']) ? (int)$_GET['habilidad'] : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

if ($estudiante>0 && $habilidad>0 && in_array($accion,['mas','menos'])) {
  $valor = ($accion==='mas') ? 1 : -1;
  $conn->begin_transaction();
  try {
    $stmt=$conn->prepare("UPDATE puntuaciones SET puntaje = puntaje + ? WHERE estudiante_id=? AND habilidad_id=?");
    $stmt->bind_param("iii",$valor,$estudiante,$habilidad);
    $stmt->execute();
    if ($stmt->affected_rows===0) {
      $stmt2=$conn->prepare("INSERT INTO puntuaciones (estudiante_id, habilidad_id, puntaje) VALUES (?, ?, ?)");
      $stmt2->bind_param("iii",$estudiante,$habilidad,$valor);
      $stmt2->execute();
    }
    $conn->commit();
  } catch(Exception $e){ $conn->rollback(); }
}
header("Location: puntuar_estudiante.php?id=".$estudiante);
exit;
