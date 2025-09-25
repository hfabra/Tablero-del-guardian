<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

function obtenerEmbedYoutube(?string $url): ?string {
    if (!$url) {
        return null;
    }
    $patron = '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/|v\/))([A-Za-z0-9_-]{11})/';
    if (preg_match($patron, $url, $coincidencias)) {
        return 'https://www.youtube.com/embed/'.$coincidencias[1];
    }
    return null;
}

$reto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reto_id <= 0) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-flag'></i></div><h4 class='fw-semibold mb-2'>Reto no válido</h4><p class='text-muted mb-0'>Regresa al listado de <a href='actividades.php'>actividades</a> para seleccionar uno disponible.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare("SELECT r.id, r.nombre, r.descripcion, r.imagen, r.video_url, r.pdf, a.id AS actividad_id, a.nombre AS actividad_nombre FROM retos r INNER JOIN actividades a ON r.actividad_id = a.id WHERE r.id = ?");
$stmt->bind_param("i", $reto_id);
$stmt->execute();
$reto = $stmt->get_result()->fetch_assoc();

if (!$reto) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Reto no encontrado</h4><p class='text-muted mb-0'>Es posible que haya sido eliminado. Revisa los retos disponibles en la actividad.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$video_embed = obtenerEmbedYoutube($reto['video_url'] ?? null);
?>
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-flag-fill"></i> <?= htmlspecialchars($reto['nombre']) ?></h1>
      <p class="page-subtitle mb-0">Actividad: <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($reto['actividad_nombre']) ?></a></p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-arrow-left"></i> Volver al listado</a>
      <a href="puntuar.php?actividad_id=<?= $reto['actividad_id'] ?>" class="btn btn-success btn-icon"><i class="bi bi-graph-up-arrow"></i> Ver tablero</a>
    </div>
  </div>
</section>

<div class="card section-card">
  <div class="card-body">
    <div class="d-flex flex-column flex-lg-row gap-4">
      <div class="flex-grow-1">
        <h5 class="fw-semibold mb-3">Descripción</h5>
        <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($reto['descripcion'] ?? '')) ?: '<span class="fst-italic">Sin descripción registrada</span>' ?></p>

        <div class="d-flex flex-wrap gap-2 mb-4">
          <?php if (!empty($reto['imagen'])): ?><span class="badge rounded-pill text-bg-primary-subtle"><i class="bi bi-image-fill me-1"></i>Imagen</span><?php endif; ?>
          <?php if (!empty($reto['video_url'])): ?><span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-camera-video-fill me-1"></i>Video</span><?php endif; ?>
          <?php if (!empty($reto['pdf'])): ?><span class="badge rounded-pill bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF</span><?php endif; ?>
        </div>

        <?php if (!empty($reto['pdf'])): ?>
          <a href="<?= htmlspecialchars($reto['pdf']) ?>" class="btn btn-outline-secondary btn-icon mb-4" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Abrir documento</a>
        <?php endif; ?>
      </div>
      <?php if (!empty($reto['imagen'])): ?>
        <div class="flex-shrink-0 w-100" style="max-width: 420px;">
          <div class="ratio ratio-4x3 rounded overflow-hidden shadow-sm">
            <img src="<?= htmlspecialchars($reto['imagen']) ?>" alt="Imagen del reto" class="w-100 h-100" style="object-fit: cover;">
          </div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($video_embed || !empty($reto['video_url'])): ?>
      <div class="mt-4">
        <h5 class="fw-semibold mb-3">Video</h5>
        <?php if ($video_embed): ?>
          <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
            <iframe src="<?= htmlspecialchars($video_embed) ?>" title="Video del reto" allowfullscreen></iframe>
          </div>
        <?php else: ?>
          <a href="<?= htmlspecialchars($reto['video_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-icon"><i class="bi bi-play-circle"></i> Ver video</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($reto['pdf'])): ?>
      <div class="mt-4">
        <h5 class="fw-semibold mb-3">Documento PDF</h5>
        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
          <iframe src="<?= htmlspecialchars($reto['pdf']) ?>" title="PDF del reto"></iframe>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
