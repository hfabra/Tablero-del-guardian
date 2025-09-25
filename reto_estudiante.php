<?php
require_once 'includes/db.php';
require_once 'includes/protect_estudiante.php';
include 'includes/header_estudiante.php';

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

function limpiarContenidoBlog(?string $html): string {
    if ($html === null) {
        return '';
    }
    $permitidos = '<p><a><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><img><figure><figcaption><table><thead><tbody><tr><th><td><span><br><hr><pre><code><div>';
    $sanitizado = strip_tags($html, $permitidos);
    $sanitizado = preg_replace('/on\w+\s*=\s*("|\').*?\1/i', '', $sanitizado);
    $sanitizado = preg_replace('/(href|src)\s*=\s*"javascript:[^"]*"/i', '$1="#"', $sanitizado);
    $sanitizado = preg_replace("/(href|src)\s*=\s*'javascript:[^']*'/i", "$1='#'", $sanitizado);
    return $sanitizado;
}

$estudiante_id = (int)($_SESSION['estudiante_id'] ?? 0);
$reto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reto_id <= 0) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-flag'></i></div><h4 class='fw-semibold mb-2'>Reto no válido</h4><p class='text-muted mb-0'>Regresa a tu <a href='perfil_estudiante.php'>panel</a> para seleccionar un reto disponible.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare('SELECT r.id, r.nombre, r.descripcion, r.contenido_blog, r.imagen, r.video_url, r.pdf, a.id AS actividad_id, a.nombre AS actividad_nombre, e.nombre AS estudiante_nombre FROM retos r INNER JOIN actividades a ON r.actividad_id = a.id INNER JOIN estudiantes e ON e.actividad_id = a.id WHERE r.id = ? AND e.id = ?');
$stmt->bind_param('ii', $reto_id, $estudiante_id);
$stmt->execute();
$reto = $stmt->get_result()->fetch_assoc();

if (!$reto) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Reto no encontrado</h4><p class='text-muted mb-0'>Es posible que no pertenezca a tu actividad. Consulta con tu docente.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$errores = [];
$exito = $_SESSION['retro_exito'] ?? null;
unset($_SESSION['retro_exito']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensaje = trim($_POST['mensaje'] ?? '');
    $archivoNombre = null;

    if (!empty($_FILES['adjunto']['name'])) {
        $permitidos = ['jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','zip'];
        $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $permitidos)) {
            $errores[] = 'El archivo adjunto no es compatible. Usa imágenes, PDF o documentos.';
        } else {
            $directorio = 'assets/retroalimentaciones';
            if (!is_dir($directorio)) {
                mkdir($directorio, 0775, true);
            }
            $archivoNombre = 'retro_'.$estudiante_id.'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
            $rutaDestino = $directorio.'/'.$archivoNombre;
            if (!move_uploaded_file($_FILES['adjunto']['tmp_name'], $rutaDestino)) {
                $errores[] = 'No se pudo guardar el archivo adjunto.';
                $archivoNombre = null;
            }
        }
    }

    if ($mensaje === '' && !$archivoNombre) {
        $errores[] = 'Escribe un mensaje o adjunta un archivo para enviar tu retroalimentación.';
    }

    if (!$errores) {
        $stmtInsert = $conn->prepare('INSERT INTO retroalimentaciones (estudiante_id, reto_id, mensaje, archivo, autor) VALUES (?, ?, ?, ?, ?)');

        if (!$stmtInsert) {
            error_log('Error al preparar retroalimentación de estudiante: '.$conn->error);
            $errores[] = 'No se pudo registrar tu retroalimentación. Intenta nuevamente en unos minutos.';
        } else {
            $autorRetro = 'estudiante';
            $stmtInsert->bind_param('iisss', $estudiante_id, $reto_id, $mensaje, $archivoNombre, $autorRetro);
            if ($stmtInsert->execute()) {
                $_SESSION['retro_exito'] = '¡Tu retroalimentación fue enviada!';
                header('Location: reto_estudiante.php?id='.$reto_id);
                exit;
            }

            error_log('Error al guardar retroalimentación de estudiante: '.$stmtInsert->error);
            $errores[] = 'No se pudo registrar tu retroalimentación. Intenta nuevamente en unos minutos.';
        }
    }
}

$video_embed = obtenerEmbedYoutube($reto['video_url'] ?? null);
$contenido_blog = limpiarContenidoBlog($reto['contenido_blog'] ?? null);

$retroStmt = $conn->prepare('SELECT id, mensaje, archivo, autor, creado_en FROM retroalimentaciones WHERE reto_id = ? AND estudiante_id = ? ORDER BY creado_en DESC');
$retroStmt->bind_param('ii', $reto_id, $estudiante_id);
$retroStmt->execute();
$retroalimentaciones = $retroStmt->get_result();
?>

<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-flag"></i> <?= htmlspecialchars($reto['nombre']) ?></h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($reto['actividad_nombre']) ?></span></p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="perfil_estudiante.php" class="btn btn-outline-primary btn-icon"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
  </div>
</section>

<?php if ($exito): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($exito) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<?php if ($errores): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errores as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<article class="card border-0 shadow-sm overflow-hidden mb-4">
  <?php if (!empty($reto['imagen'])): ?>
    <div class="ratio ratio-21x9 bg-dark">
      <img src="<?= htmlspecialchars($reto['imagen']) ?>" alt="Imagen destacada del reto" class="w-100 h-100" style="object-fit: cover;">
    </div>
  <?php else: ?>
    <div class="bg-gradient p-5 text-center text-white" style="background: linear-gradient(135deg, #6366f1, #22d3ee);">
      <i class="bi bi-stars display-4"></i>
    </div>
  <?php endif; ?>
  <div class="card-body p-4 p-lg-5">
    <span class="badge text-bg-primary-soft text-uppercase mb-3">Reto académico</span>
    <h2 class="fw-bold text-dark mb-3">Descripción</h2>
    <p class="lead text-muted mb-4"><?= nl2br(htmlspecialchars($reto['descripcion'] ?? '')) ?: '<span class="fst-italic">Sin descripción registrada</span>' ?></p>
    <?php if ($contenido_blog !== ''): ?>
      <div class="blog-content mb-4">
        <?= $contenido_blog ?>
      </div>
    <?php endif; ?>

    <?php if ($video_embed || !empty($reto['video_url'])): ?>
      <section class="mb-4">
        <h3 class="h5 fw-semibold mb-3"><i class="bi bi-camera-video"></i> Video de referencia</h3>
        <?php if ($video_embed): ?>
          <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
            <iframe src="<?= htmlspecialchars($video_embed) ?>" title="Video del reto" allowfullscreen></iframe>
          </div>
        <?php else: ?>
          <a href="<?= htmlspecialchars($reto['video_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-icon"><i class="bi bi-play-circle"></i> Ver video</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($reto['pdf'])): ?>
      <section class="mb-4">
        <h3 class="h5 fw-semibold mb-3"><i class="bi bi-file-earmark-pdf"></i> Documento descargable</h3>
        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm mb-3">
          <iframe src="<?= htmlspecialchars($reto['pdf']) ?>" title="PDF del reto"></iframe>
        </div>
        <a href="<?= htmlspecialchars($reto['pdf']) ?>" class="btn btn-outline-secondary btn-icon" target="_blank" rel="noopener"><i class="bi bi-download"></i> Descargar PDF</a>
      </section>
    <?php endif; ?>
  </div>
</article>

<div class="row g-4 mb-5">
  <div class="col-lg-6">
    <div class="card section-card h-100">
      <div class="card-body">
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-chat-dots"></i> Enviar retroalimentación</h2>
        <form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-3">
          <div>
            <label for="mensaje" class="form-label">Mensaje</label>
            <textarea id="mensaje" name="mensaje" class="form-control" rows="5" placeholder="Cuéntale a tu docente cómo avanzaste o qué dudas tienes."><?= htmlspecialchars($_POST['mensaje'] ?? '') ?></textarea>
          </div>
          <div>
            <label for="adjunto" class="form-label">Archivo adjunto (opcional)</label>
            <input type="file" id="adjunto" name="adjunto" class="form-control" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.zip">
            <div class="form-text">Adjunta evidencias, fotos o documentos de tu trabajo.</div>
          </div>
          <div class="d-grid">
            <button class="btn btn-primary btn-icon"><i class="bi bi-send"></i> Enviar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card section-card h-100">
      <div class="card-body">
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-archive"></i> Historial</h2>
        <?php if ($retroalimentaciones->num_rows === 0): ?>
          <div class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <p class="mb-0">Aún no envías comentarios. ¡Comparte tus avances!</p>
          </div>
        <?php else: ?>
          <div class="timeline-feedback">
            <?php while ($retro = $retroalimentaciones->fetch_assoc()): ?>
              <div class="timeline-item">
                <div class="timeline-dot <?= $retro['autor'] === 'docente' ? 'bg-primary' : 'bg-success' ?>"></div>
                <div class="timeline-content">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold text-dark"><i class="bi <?= $retro['autor'] === 'docente' ? 'bi-mortarboard-fill' : 'bi-person-check-fill' ?> me-1"></i><?= $retro['autor'] === 'docente' ? 'Docente' : 'Tú' ?></span>
                    <span class="text-muted small"><i class="bi bi-clock-history me-1"></i><?= $retro['creado_en'] ?></span>
                  </div>
                  <?php if (!empty($retro['mensaje'])): ?>
                    <p class="mb-2"><?= nl2br(htmlspecialchars($retro['mensaje'])) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($retro['archivo'])): ?>
                    <a class="btn btn-sm btn-outline-secondary btn-icon" href="assets/retroalimentaciones/<?= htmlspecialchars($retro['archivo']) ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Ver adjunto</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
