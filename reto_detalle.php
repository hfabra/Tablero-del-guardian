<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$columnaIcono = $conn->query("SHOW COLUMNS FROM retos LIKE 'icono'");
$iconosHabilitados = ($columnaIcono instanceof mysqli_result && $columnaIcono->num_rows > 0);
if ($columnaIcono instanceof mysqli_result) {
    $columnaIcono->free();
}

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
    echo "<div class='alert alert-danger'>Reto no válido.</div>";
    include 'includes/footer.php';
    exit;
}

$selectCols = "r.id, r.nombre, r.descripcion, r.imagen, r.video_url, r.pdf" . ($iconosHabilitados ? ", r.icono" : "");
$stmt = $conn->prepare("SELECT $selectCols, a.id AS actividad_id, a.nombre AS actividad_nombre FROM retos r INNER JOIN actividades a ON r.actividad_id = a.id WHERE r.id = ?");
$stmt->bind_param("i", $reto_id);
$stmt->execute();
$reto = $stmt->get_result()->fetch_assoc();

if (!$reto) {
    echo "<div class='alert alert-danger'>Reto no encontrado.</div>";
    include 'includes/footer.php';
    exit;
}

$video_embed = obtenerEmbedYoutube($reto['video_url'] ?? null);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center">
    <div class="reto-icon me-3"><?= htmlspecialchars($reto['icono'] ?? '🎯') ?></div>
    <div>
      <h3 class="mb-1"><?= htmlspecialchars($reto['nombre']) ?></h3>
      <p class="text-muted mb-0">Actividad: <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>"><?= htmlspecialchars($reto['actividad_nombre']) ?></a></p>
    </div>
  </div>
  <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="card">
  <div class="card-body">
    <h5 class="card-title">Descripción</h5>
    <p class="card-text"><?= nl2br(htmlspecialchars($reto['descripcion'] ?? '')) ?: '<span class="text-muted">Sin descripción</span>' ?></p>

    <?php if (!empty($reto['imagen'])): ?>
      <div class="mt-4">
        <h5>Imagen</h5>
        <img src="<?= htmlspecialchars($reto['imagen']) ?>" alt="Imagen del reto" class="img-fluid rounded border">
      </div>
    <?php endif; ?>

    <?php if ($video_embed || !empty($reto['video_url'])): ?>
      <div class="mt-4">
        <h5>Video</h5>
        <?php if ($video_embed): ?>
          <div class="ratio ratio-16x9">
            <iframe src="<?= htmlspecialchars($video_embed) ?>" title="Video del reto" allowfullscreen></iframe>
          </div>
        <?php else: ?>
          <a href="<?= htmlspecialchars($reto['video_url']) ?>" target="_blank" rel="noopener">Ver video</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($reto['pdf'])): ?>
      <div class="mt-4">
        <h5>Documento PDF</h5>
        <a href="<?= htmlspecialchars($reto['pdf']) ?>" class="btn btn-outline-secondary mb-3" target="_blank" rel="noopener">Abrir PDF</a>
        <div class="ratio ratio-16x9">
          <iframe src="<?= htmlspecialchars($reto['pdf']) ?>" title="PDF del reto"></iframe>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
