<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id<=0){
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-compass'></i></div><h4 class='fw-semibold mb-2'>Selecciona una actividad</h4><p class='text-muted mb-0'>Dirígete a <a href='actividades.php'>Actividades</a> y elige el tablero que deseas visualizar.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Actividad
$stmt=$conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$actividad=$stmt->get_result()->fetch_assoc();
if(!$actividad){
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Actividad no encontrada</h4><p class='text-muted mb-0'>Tal vez fue eliminada. Revisa nuevamente el listado de actividades.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Retos de la actividad
$stmt=$conn->prepare("SELECT id, nombre, descripcion, imagen, video_url, pdf FROM retos WHERE actividad_id=? ORDER BY id ASC");
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
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-graph-up-arrow"></i> Tablero</h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($actividad['nombre']) ?></span>. Monitorea el progreso y entrega puntos en tiempo real.</p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="estudiantes.php?actividad_id=<?= $actividad_id ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-people"></i> Estudiantes</a>
      <a href="retos.php?actividad_id=<?= $actividad_id ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-flag"></i> Retos</a>
      <a href="habilidades.php" class="btn btn-outline-secondary btn-icon"><i class="bi bi-stars"></i> Habilidades</a>
    </div>
  </div>
</section>

<div class="card section-card mb-4">
  <div class="card-header bg-white pb-0">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="fw-semibold mb-0"><i class="bi bi-flag me-2 text-primary"></i>Retos de esta actividad</h5>
      <a href="retos.php?actividad_id=<?= $actividad_id ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-plus-circle"></i> Nuevo reto</a>
    </div>
  </div>
  <div class="card-body">
    <?php if($retos->num_rows===0): ?>
      <div class="empty-state"><i class="bi bi-lightning-charge me-2"></i>No hay retos creados aún. <a href="retos.php?actividad_id=<?= $actividad_id ?>" class="fw-semibold">Crea el primero</a>.</div>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php while($r=$retos->fetch_assoc()): ?>
          <div class="col">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h5 class="fw-semibold mb-0"><a href="reto_detalle.php?id=<?= $r['id'] ?>" class="text-decoration-none text-dark"><i class="bi bi-patch-check-fill text-primary me-1"></i><?= htmlspecialchars($r['nombre'])?></a></h5>
                  <span class="badge text-bg-primary-soft">ID <?= $r['id'] ?></span>
                </div>
                <?php if(!empty($r['descripcion'])): ?>
                  <p class="text-muted flex-grow-1 mb-3"><?= htmlspecialchars($r['descripcion']) ?></p>
                <?php else: ?>
                  <p class="text-muted fst-italic flex-grow-1 mb-3">Sin descripción</p>
                <?php endif; ?>
                <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
                  <div class="d-flex gap-2">
                    <?php if(!empty($r['imagen'])): ?><span class="badge rounded-pill text-bg-primary-subtle"><i class="bi bi-image-fill"></i></span><?php endif; ?>
                    <?php if(!empty($r['video_url'])): ?><span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-camera-video-fill"></i></span><?php endif; ?>
                    <?php if(!empty($r['pdf'])): ?><span class="badge rounded-pill bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-pdf-fill"></i></span><?php endif; ?>
                  </div>
                  <a class="btn btn-outline-primary btn-icon btn-sm" href="reto_detalle.php?id=<?= $r['id'] ?>"><i class="bi bi-box-arrow-up-right"></i> Ver</a>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <?php while($e=$estudiantes->fetch_assoc()): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card section-card h-100">
        <div class="card-body d-flex gap-3 align-items-center">
          <img src="assets/img/avatars/<?= htmlspecialchars($e['avatar']) ?>" class="avatar-xl shadow-sm" alt="avatar de <?= htmlspecialchars($e['nombre']) ?>">
          <div class="flex-grow-1">
            <h5 class="fw-semibold mb-1"><?= htmlspecialchars($e['nombre']) ?></h5>
            <p class="text-muted mb-2">Puntos acumulados</p>
            <span class="badge bg-primary fs-6"><i class="bi bi-stars me-1"></i><?= $e['total'] ?></span>
          </div>
          <a href="puntuar_estudiante.php?id=<?= $e['id'] ?>" class="btn btn-success btn-icon"><i class="bi bi-plus-circle"></i> Puntuar</a>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<?php include 'includes/footer.php'; ?>
