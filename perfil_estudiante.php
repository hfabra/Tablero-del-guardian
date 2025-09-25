<?php
require_once 'includes/db.php';
require_once 'includes/protect_estudiante.php';
include 'includes/header_estudiante.php';

$estudiante_id = (int)($_SESSION['estudiante_id'] ?? 0);

$stmt = $conn->prepare('SELECT e.id, e.nombre, e.avatar, e.usuario, e.clave_acceso, e.actividad_id, a.nombre AS actividad_nombre FROM estudiantes e INNER JOIN actividades a ON a.id = e.actividad_id WHERE e.id = ? LIMIT 1');
$stmt->bind_param('i', $estudiante_id);
$stmt->execute();
$estudiante = $stmt->get_result()->fetch_assoc();

if (!$estudiante) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-emoji-frown'></i></div><h4 class='fw-semibold mb-2'>Perfil no disponible</h4><p class='text-muted mb-0'>No encontramos tu registro. Por favor, contacta a tu docente.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$resPuntaje = $conn->query('SELECT COALESCE(SUM(puntaje),0) AS total FROM puntuaciones WHERE estudiante_id='.$estudiante_id);
$puntajeTotal = $resPuntaje ? (int)$resPuntaje->fetch_assoc()['total'] : 0;

$retosStmt = $conn->prepare('SELECT id, nombre, descripcion FROM retos WHERE actividad_id = ? ORDER BY id DESC');
$retosStmt->bind_param('i', $estudiante['actividad_id']);
$retosStmt->execute();
$retos = $retosStmt->get_result();

$retroConteo = [];
$retroRes = $conn->prepare('SELECT reto_id, COUNT(*) AS total FROM retroalimentaciones WHERE estudiante_id = ? GROUP BY reto_id');
$retroRes->bind_param('i', $estudiante_id);
$retroRes->execute();
$retroData = $retroRes->get_result();
while ($fila = $retroData->fetch_assoc()) {
    $retroConteo[(int)$fila['reto_id']] = (int)$fila['total'];
}
?>

<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-person-video3"></i> Mi espacio de aprendizaje</h1>
      <p class="page-subtitle mb-0">Actividad asignada: <span class="fw-semibold text-dark"><?= htmlspecialchars($estudiante['actividad_nombre']) ?></span>.</p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="logout_estudiante.php" class="btn btn-outline-light btn-icon"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>
</section>

<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card section-card h-100">
      <div class="card-body text-center">
        <img src="assets/img/avatars/<?= htmlspecialchars($estudiante['avatar']) ?>" class="avatar-xl shadow-sm mb-3" alt="Avatar de <?= htmlspecialchars($estudiante['nombre']) ?>">
        <h3 class="fw-semibold mb-1"><?= htmlspecialchars($estudiante['nombre']) ?></h3>
        <p class="text-muted mb-3">Grupo: <?= htmlspecialchars($estudiante['actividad_nombre']) ?></p>
        <div class="badge rounded-pill text-bg-primary-subtle px-3 py-2 mb-3"><i class="bi bi-stars me-1"></i> <?= $puntajeTotal ?> puntos</div>
        <div class="credential-box p-3 rounded">
          <p class="text-muted text-uppercase small mb-2">Mis credenciales</p>
          <div class="d-flex flex-column gap-2">
            <span class="credential-chip"><i class="bi bi-person-circle me-2"></i><strong>Usuario:</strong> <?= htmlspecialchars($estudiante['usuario']) ?></span>
            <span class="credential-chip"><i class="bi bi-key me-2"></i><strong>Clave:</strong> <?= htmlspecialchars($estudiante['clave_acceso']) ?></span>
          </div>
          <p class="form-text mt-2 mb-0">Si olvidas tus datos, solicita ayuda a tu docente.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card section-card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h4 fw-semibold mb-0"><i class="bi bi-flag"></i> Retos disponibles</h2>
          <span class="badge rounded-pill bg-primary-subtle text-primary"><i class="bi bi-lightbulb me-1"></i>Aprendizaje activo</span>
        </div>
        <?php if ($retos->num_rows === 0): ?>
          <div class="empty-state">
            <i class="bi bi-emoji-smile"></i>
            <p class="mb-0">Tu docente aún no ha publicado retos. ¡Mantente atento!</p>
          </div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php while ($reto = $retos->fetch_assoc()): ?>
              <a class="list-group-item list-group-item-action py-3 reto-list-item" href="reto_estudiante.php?id=<?= $reto['id'] ?>">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h3 class="h5 fw-semibold mb-1 text-dark"><?= htmlspecialchars($reto['nombre']) ?></h3>
                    <p class="text-muted mb-0 small"><?= htmlspecialchars(mb_strimwidth($reto['descripcion'] ?? 'Sin descripción', 0, 160, '…')) ?></p>
                  </div>
                  <div class="text-end ms-3">
                    <span class="badge rounded-pill bg-secondary-subtle text-secondary"><i class="bi bi-chat-dots me-1"></i><?= $retroConteo[$reto['id']] ?? 0 ?> mensajes</span>
                  </div>
                </div>
              </a>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
