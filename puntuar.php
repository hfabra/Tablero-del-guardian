<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id<=0){ echo "<div class='alert alert-warning'>Selecciona una actividad desde <a href='actividades.php'>Actividades</a>.</div>"; include 'includes/footer.php'; exit; }

// Actividad
$stmt=$conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$actividad=$stmt->get_result()->fetch_assoc();
if(!$actividad){ echo "<div class='alert alert-danger'>Actividad no encontrada.</div>"; include 'includes/footer.php'; exit; }

// Retos de la actividad
$stmt=$conn->prepare("SELECT id, nombre, descripcion FROM retos WHERE actividad_id=? ORDER BY id ASC");
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$retos=$stmt->get_result();

// Estudiantes con totales
$q = "SELECT e.id, e.nombre, e.avatar, COALESCE(SUM(p.puntaje),0) AS total "
   . "FROM estudiantes e "
   . "LEFT JOIN puntuaciones p ON p.estudiante_id=e.id "
   . "WHERE e.actividad_id=? "
   . "GROUP BY e.id, e.nombre, e.avatar "
   . "ORDER BY total DESC, e.nombre ASC";
$stmt=$conn->prepare($q);
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$estudiantes=$stmt->get_result();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Tablero - <?= htmlspecialchars($actividad['nombre']) ?></h3>
  <div>
    <a href="estudiantes.php?actividad_id=<?= $actividad_id ?>" class="btn btn-outline-secondary btn-sm">Gestionar estudiantes</a>
    <a href="retos.php?actividad_id=<?= $actividad_id ?>" class="btn btn-outline-primary btn-sm">Gestionar retos</a>
    <a href="habilidades.php" class="btn btn-outline-secondary btn-sm">Habilidades</a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header">Retos de esta actividad</div>
  <div class="card-body">
    <?php if($retos->num_rows===0): ?>
      <div class="text-muted">No hay retos creados aún. Crea algunos en <a href="retos.php?actividad_id=<?= $actividad_id ?>">Retos</a>.</div>
    <?php else: ?>
      <ul class="list-group">
        <?php while($r=$retos->fetch_assoc()): ?>
          <li class="list-group-item d-flex justify-content-between align-items-start">
            <div class="me-3">
              <strong><a href="reto_detalle.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['nombre']) ?></a></strong>
              <?php if(!empty($r['descripcion'])): ?>
                <div class="small text-muted"><?= htmlspecialchars($r['descripcion']) ?></div>
              <?php endif; ?>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="reto_detalle.php?id=<?= $r['id'] ?>">Ver detalle</a>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <?php while($e=$estudiantes->fetch_assoc()): ?>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex align-items-center">
          <img src="assets/img/avatars/<?= htmlspecialchars($e['avatar']) ?>" class="rounded-circle me-3" width="56" height="56" alt="avatar">
          <div class="flex-grow-1">
            <h5 class="card-title mb-1"><?= htmlspecialchars($e['nombre']) ?></h5>
            <p class="card-text mb-2">Puntos: <span class="badge bg-primary"><?= $e['total'] ?></span></p>
            <a href="puntuar_estudiante.php?id=<?= $e['id'] ?>" class="btn btn-success btn-sm">Puntuar</a>
          </div>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<?php include 'includes/footer.php'; ?>
