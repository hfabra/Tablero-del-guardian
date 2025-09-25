<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$estudiante_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($estudiante_id<=0){
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-exclamation-triangle'></i></div><h4 class='fw-semibold mb-2'>Estudiante no válido</h4><p class='text-muted mb-0'>Regresa al <a href='actividades.php'>tablero</a> y selecciona un perfil disponible.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Estudiante + actividad
$stmt=$conn->prepare("SELECT e.id, e.nombre, e.avatar, e.actividad_id, a.nombre AS actividad FROM estudiantes e JOIN actividades a ON a.id=e.actividad_id WHERE e.id=?");
$stmt->bind_param("i",$estudiante_id);
$stmt->execute();
$est=$stmt->get_result()->fetch_assoc();
if(!$est){
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Estudiante no encontrado</h4><p class='text-muted mb-0'>Puede que haya sido eliminado. Revisa el tablero de la actividad.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Habilidades
$hab=$conn->query("SELECT id, nombre FROM habilidades ORDER BY id ASC");
?>
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-award"></i> Puntuar estudiante</h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($est['actividad']) ?></span>. Reconoce los logros por habilidad.</p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="puntuar.php?actividad_id=<?= $est['actividad_id'] ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-graph-up"></i> Volver al tablero</a>
    </div>
  </div>
</section>

<div class="card section-card mb-4">
  <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-4">
    <img src="assets/img/avatars/<?= htmlspecialchars($est['avatar']) ?>" class="avatar-xl shadow-sm" alt="avatar de <?= htmlspecialchars($est['nombre']) ?>">
    <div>
      <h3 class="fw-semibold mb-1"><?= htmlspecialchars($est['nombre']) ?></h3>
      <?php $res=$conn->query("SELECT COALESCE(SUM(puntaje),0) AS total FROM puntuaciones WHERE estudiante_id=".$estudiante_id); $total=$res->fetch_assoc()['total']; ?>
      <p class="text-muted mb-2">Puntos acumulados</p>
      <span class="badge bg-primary fs-5"><i class="bi bi-stars me-1"></i><?= $total ?></span>
    </div>
  </div>
</div>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th class="text-uppercase">Habilidad</th>
          <th class="text-uppercase">Acciones</th>
          <th class="text-uppercase text-end">Puntos actuales</th>
        </tr>
      </thead>
      <tbody>
        <?php while($h=$hab->fetch_assoc()):
          $r=$conn->query("SELECT puntaje FROM puntuaciones WHERE estudiante_id=$estudiante_id AND habilidad_id=".$h['id']);
          $valor = ($r && $r->num_rows) ? (int)$r->fetch_assoc()['puntaje'] : 0;
        ?>
        <tr>
          <td class="fw-semibold text-dark"><i class="bi bi-patch-check-fill text-primary me-2"></i><?= htmlspecialchars($h['nombre']) ?></td>
          <td>
            <div class="btn-group" role="group">
              <a class="btn btn-sm btn-success btn-icon" href="update_puntaje.php?estudiante=<?= $estudiante_id ?>&habilidad=<?= $h['id'] ?>&accion=mas"><i class="bi bi-plus"></i> Añadir</a>
              <a class="btn btn-sm btn-outline-danger btn-icon" href="update_puntaje.php?estudiante=<?= $estudiante_id ?>&habilidad=<?= $h['id'] ?>&accion=menos"><i class="bi bi-dash"></i> Restar</a>
            </div>
          </td>
          <td class="text-end"><span class="badge bg-secondary fs-6"><i class="bi bi-activity me-1"></i><?= $valor ?></span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
