<?php
require_once 'includes/db.php';
include 'includes/header.php';

$estudiante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($estudiante_id<=0){ echo "<div class='alert alert-danger'>Estudiante no valido.</div>"; include 'includes/footer.php'; exit; }

// Estudiante + actividad
$stmt=$conn->prepare("SELECT e.id, e.nombre, e.avatar, e.actividad_id, a.nombre AS actividad FROM estudiantes e JOIN actividades a ON a.id=e.actividad_id WHERE e.id=?");
$stmt->bind_param("i",$estudiante_id);
$stmt->execute();
$est=$stmt->get_result()->fetch_assoc();
if(!$est){ echo "<div class='alert alert-danger'>Estudiante no encontrado.</div>"; include 'includes/footer.php'; exit; }

// Habilidades
$hab=$conn->query("SELECT id, nombre FROM habilidades ORDER BY id ASC");
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Puntuar: <?= htmlspecialchars($est['nombre']) ?> <small class="text-muted">[<?= htmlspecialchars($est['actividad']) ?>]</small></h3>
  <a href="puntuar.php?actividad_id=<?= $est['actividad_id'] ?>" class="btn btn-outline-secondary">Volver al tablero</a>
</div>

<div class="card mb-3">
  <div class="card-body d-flex align-items-center">
    <img src="assets/img/avatars/<?= htmlspecialchars($est['avatar']) ?>" class="rounded-circle me-3" width="72" height="72" alt="avatar">
    <div>
      <?php $res=$conn->query("SELECT COALESCE(SUM(puntaje),0) AS total FROM puntuaciones WHERE estudiante_id=".$estudiante_id); $total=$res->fetch_assoc()['total']; ?>
      <div>Puntos totales: <span class="badge bg-primary fs-6"><?= $total ?></span></div>
    </div>
  </div>
</div>

<table class="table table-hover align-middle">
  <thead><tr><th>Habilidad</th><th>Acciones</th><th>Puntos actuales</th></tr></thead>
  <tbody>
    <?php while($h=$hab->fetch_assoc()): 
      $r=$conn->query("SELECT puntaje FROM puntuaciones WHERE estudiante_id=$estudiante_id AND habilidad_id=".$h['id']);
      $valor = ($r && $r->num_rows) ? (int)$r->fetch_assoc()['puntaje'] : 0;
    ?>
    <tr>
      <td><?= htmlspecialchars($h['nombre']) ?></td>
      <td>
        <a class="btn btn-sm btn-success" href="update_puntaje.php?estudiante=<?= $estudiante_id ?>&habilidad=<?= $h['id'] ?>&accion=mas">+1</a>
        <a class="btn btn-sm btn-danger" href="update_puntaje.php?estudiante=<?= $estudiante_id ?>&habilidad=<?= $h['id'] ?>&accion=menos">-1</a>
      </td>
      <td><span class="badge bg-secondary"><?= $valor ?></span></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<?php include 'includes/footer.php'; ?>
