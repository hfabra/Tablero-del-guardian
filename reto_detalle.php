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

function dominioIframePermitido(string $host, array $permitidos): bool {
    $host = strtolower($host);
    foreach ($permitidos as $permitido) {
        $permitido = strtolower($permitido);
        if ($host === $permitido) {
            return true;
        }
        $sufijo = '.' . $permitido;
        if (substr($host, -strlen($sufijo)) === $sufijo) {
            return true;
        }
    }
    return false;
}

function limpiarContenidoBlog(?string $html): string {
    if ($html === null) {
        return '';
    }
    $permitidos = '<p><a><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><img><figure><figcaption><table><thead><tbody><tr><th><td><span><br><hr><pre><code><div><iframe>';
    $sanitizado = strip_tags($html, $permitidos);
    $sanitizado = preg_replace('/on\w+\s*=\s*("|\').*?\1/i', '', $sanitizado);
    $sanitizado = preg_replace('/(href|src)\s*=\s*"javascript:[^"]*"/i', '$1="#"', $sanitizado);
    $sanitizado = preg_replace("/(href|src)\s*=\s*'javascript:[^']*'/i", "$1='#'", $sanitizado);

    $dominiosIframe = ['genial.ly', 'view.genial.ly', 'youtube.com', 'youtu.be', 'www.youtube.com', 'player.vimeo.com', 'vimeo.com', 'docs.google.com'];
    $sanitizado = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe>/is', static function ($coincidencias) use ($dominiosIframe) {
        $atributosCadena = $coincidencias[1] ?? '';
        if (!preg_match('/\s(src)\s*=\s*(\"|\')(.*?)(\2)/i', $atributosCadena, $srcCoincidencia)) {
            return '';
        }

        $src = trim($srcCoincidencia[3]);
        $url = parse_url($src);
        if (!$url || empty($url['scheme']) || strtolower($url['scheme']) !== 'https' || empty($url['host']) || !dominioIframePermitido($url['host'], $dominiosIframe)) {
            return '';
        }

        $atributosPermitidos = ['src', 'width', 'height', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy', 'style', 'title', 'frameborder'];
        $atributos = [];
        if (preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*(\"|\')(.*?)(\2)/', $atributosCadena, $attrMatches, PREG_SET_ORDER)) {
            foreach ($attrMatches as $attr) {
                $nombre = strtolower($attr[1]);
                if (in_array($nombre, $atributosPermitidos, true)) {
                    $valor = htmlspecialchars($attr[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $atributos[$nombre] = $valor;
                }
            }
        }

        if (preg_match('/\sallowfullscreen\b/i', $atributosCadena)) {
            $atributos['allowfullscreen'] = 'true';
        }

        $atributos['src'] = htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (!isset($atributos['loading'])) {
            $atributos['loading'] = 'lazy';
        }

        $atributosOrdenados = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosOrdenados .= ' ' . $nombre . '="' . $valor . '"';
        }

        return '<iframe' . $atributosOrdenados . '></iframe>';
    });

    return $sanitizado;
}

$reto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reto_id <= 0) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-flag'></i></div><h4 class='fw-semibold mb-2'>Reto no válido</h4><p class='text-muted mb-0'>Regresa al listado de <a href='actividades.php'>actividades</a> para seleccionar uno disponible.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare("SELECT r.id, r.nombre, r.descripcion, r.contenido_blog, r.imagen, r.video_url, r.pdf, a.id AS actividad_id, a.nombre AS actividad_nombre FROM retos r INNER JOIN actividades a ON r.actividad_id = a.id WHERE r.id = ?");
$stmt->bind_param("i", $reto_id);
$stmt->execute();
$reto = $stmt->get_result()->fetch_assoc();

if (!$reto) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Reto no encontrado</h4><p class='text-muted mb-0'>Es posible que haya sido eliminado. Revisa los retos disponibles en la actividad.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$video_embed = obtenerEmbedYoutube($reto['video_url'] ?? null);
$contenido_blog = limpiarContenidoBlog($reto['contenido_blog'] ?? null);

$estudiantesActividad = [];
$stmtEstudiantes = $conn->prepare('SELECT e.id, e.nombre FROM actividad_estudiante ae INNER JOIN estudiantes e ON e.id = ae.estudiante_id WHERE ae.actividad_id = ? ORDER BY e.nombre ASC');
$stmtEstudiantes->bind_param('i', $reto['actividad_id']);
$stmtEstudiantes->execute();
$resEstudiantes = $stmtEstudiantes->get_result();
while ($filaEst = $resEstudiantes->fetch_assoc()) {
    $estudiantesActividad[] = $filaEst;
}
$stmtEstudiantes->close();

$erroresDocente = [];
$exitoDocente = $_SESSION['retro_docente_exito'] ?? null;
unset($_SESSION['retro_docente_exito']);
$mensajeDocentePrevio = '';
$estudianteSeleccionadoForm = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $mensajeDocentePrevio = trim($_POST['mensaje_docente'] ?? '');
    $estudianteSeleccionadoForm = isset($_POST['estudiante_id']) ? (int)$_POST['estudiante_id'] : 0;

    if ($accion === 'responder_docente') {
        $idsPermitidos = array_map(static fn($est) => (int)$est['id'], $estudiantesActividad);
        if (!$estudianteSeleccionadoForm || !in_array($estudianteSeleccionadoForm, $idsPermitidos, true)) {
            $erroresDocente[] = 'Selecciona un estudiante válido para responder.';
        }

        $archivoNombre = null;
        $rutaDestino = null;
        if (!empty($_FILES['adjunto_docente']['name'])) {
            $permitidos = ['jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','zip'];
            $ext = strtolower(pathinfo($_FILES['adjunto_docente']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $permitidos)) {
                $erroresDocente[] = 'El archivo adjunto no es compatible. Usa imágenes, PDF o documentos.';
            } else {
                $directorio = 'assets/retroalimentaciones';
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0775, true);
                }
                $archivoNombre = 'retro_docente_'.$estudianteSeleccionadoForm.'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
                $rutaDestino = $directorio.'/'.$archivoNombre;
                if (!move_uploaded_file($_FILES['adjunto_docente']['tmp_name'], $rutaDestino)) {
                    $erroresDocente[] = 'No se pudo guardar el archivo adjunto.';
                    $archivoNombre = null;
                    $rutaDestino = null;
                }
            }
        }

        if ($mensajeDocentePrevio === '' && !$archivoNombre) {
            $erroresDocente[] = 'Escribe un mensaje o adjunta un archivo para enviar tu respuesta.';
        }

        if (!$erroresDocente) {
            $stmtInsert = $conn->prepare('INSERT INTO retroalimentaciones (estudiante_id, reto_id, mensaje, archivo, autor) VALUES (?, ?, ?, ?, ?)');
            if ($stmtInsert) {
                $autorDoc = 'docente';
                $stmtInsert->bind_param('iisss', $estudianteSeleccionadoForm, $reto_id, $mensajeDocentePrevio, $archivoNombre, $autorDoc);
                if ($stmtInsert->execute()) {
                    $_SESSION['retro_docente_exito'] = 'Respuesta enviada correctamente.';
                    $stmtInsert->close();
                    header('Location: reto_detalle.php?id='.$reto_id);
                    exit;
                }
                $stmtInsert->close();
                $erroresDocente[] = 'No se pudo registrar la respuesta. Intenta nuevamente.';
            } else {
                $erroresDocente[] = 'No se pudo preparar el guardado de la respuesta.';
            }

            if ($erroresDocente && $archivoNombre && $rutaDestino && file_exists($rutaDestino)) {
                @unlink($rutaDestino);
            }
        } elseif ($archivoNombre && $rutaDestino && file_exists($rutaDestino)) {
            @unlink($rutaDestino);
        }
    }
}

$retroStmt = $conn->prepare('SELECT r.id, r.mensaje, r.archivo, r.autor, r.creado_en, e.nombre AS estudiante_nombre FROM retroalimentaciones r INNER JOIN estudiantes e ON e.id = r.estudiante_id WHERE r.reto_id = ? ORDER BY r.creado_en DESC');
$retroStmt->bind_param('i', $reto_id);
$retroStmt->execute();
$retroalimentaciones = $retroStmt->get_result();
?>
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-journal-richtext"></i> Detalle del reto</h1>
      <p class="page-subtitle mb-0">Actividad: <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($reto['actividad_nombre']) ?></a></p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="retos.php?actividad_id=<?= $reto['actividad_id'] ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-arrow-left"></i> Volver al listado</a>
      <a href="puntuar.php?actividad_id=<?= $reto['actividad_id'] ?>" class="btn btn-success btn-icon"><i class="bi bi-graph-up-arrow"></i> Ver tablero</a>
    </div>
  </div>
</section>

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
    <h1 class="display-6 fw-bold text-dark mb-3"><?= htmlspecialchars($reto['nombre']) ?></h1>
    <p class="lead text-muted mb-4"><?= nl2br(htmlspecialchars($reto['descripcion'] ?? '')) ?: '<span class="fst-italic">Sin descripción registrada</span>' ?></p>
    <div class="d-flex flex-wrap gap-2">
      <?php if (!empty($reto['video_url'])): ?><span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-camera-video-fill me-1"></i>Video de referencia</span><?php endif; ?>
      <?php if (!empty($reto['pdf'])): ?><span class="badge rounded-pill bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-pdf-fill me-1"></i>Documento PDF</span><?php endif; ?>
      <?php if ($contenido_blog !== ''): ?><span class="badge rounded-pill bg-success-subtle text-success"><i class="bi bi-journal-text me-1"></i>Contenido ampliado</span><?php endif; ?>
    </div>
  </div>
</article>

<div class="card section-card">
  <div class="card-body p-4 p-lg-5">
    <?php if ($contenido_blog !== ''): ?>
      <div class="blog-content mb-5">
        <?= $contenido_blog ?>
      </div>
    <?php endif; ?>

    <?php if ($video_embed || !empty($reto['video_url'])): ?>
      <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-camera-video"></i> Video de referencia</h2>
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
        <h2 class="h4 fw-semibold mb-3"><i class="bi bi-file-earmark-pdf"></i> Documento descargable</h2>
        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm mb-3">
          <iframe src="<?= htmlspecialchars($reto['pdf']) ?>" title="PDF del reto"></iframe>
        </div>
        <a href="<?= htmlspecialchars($reto['pdf']) ?>" class="btn btn-outline-secondary btn-icon" target="_blank" rel="noopener"><i class="bi bi-download"></i> Descargar PDF</a>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php if ($exitoDocente): ?>
  <div class="alert alert-success shadow-sm mt-4">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($exitoDocente) ?>
  </div>
<?php endif; ?>

<div class="card section-card mt-4">
  <div class="card-body">
    <h2 class="h4 fw-semibold mb-3"><i class="bi bi-reply-fill"></i> Responder a estudiantes</h2>
    <?php if ($erroresDocente): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($erroresDocente as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="accion" value="responder_docente">
      <div class="col-lg-4">
        <label for="estudiante_id" class="form-label">Estudiante</label>
        <select id="estudiante_id" name="estudiante_id" class="form-select" <?= $estudiantesActividad ? '' : 'disabled' ?>>
          <option value="" disabled <?= $estudianteSeleccionadoForm === 0 ? 'selected' : '' ?>>Selecciona un estudiante</option>
          <?php foreach ($estudiantesActividad as $estAct): ?>
            <option value="<?= $estAct['id'] ?>" <?= $estudianteSeleccionadoForm === (int)$estAct['id'] ? 'selected' : '' ?>><?= htmlspecialchars($estAct['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">
          <?php if ($estudiantesActividad): ?>
            Elige a quién deseas enviar una respuesta personalizada.
          <?php else: ?>
            No hay estudiantes inscritos en esta actividad todavía.
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-8">
        <label for="mensaje_docente" class="form-label">Mensaje</label>
        <textarea id="mensaje_docente" name="mensaje_docente" class="form-control" rows="4" placeholder="Comparte orientaciones, comentarios o recordatorios" <?= $estudiantesActividad ? '' : 'disabled' ?>><?= htmlspecialchars($mensajeDocentePrevio) ?></textarea>
        <div class="form-text">El mensaje se enviará al estudiante seleccionado y quedará registrado en la línea de tiempo.</div>
      </div>
      <div class="col-lg-6">
        <label for="adjunto_docente" class="form-label">Archivo adjunto (opcional)</label>
        <input type="file" id="adjunto_docente" name="adjunto_docente" class="form-control" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.zip" <?= $estudiantesActividad ? '' : 'disabled' ?>>
        <div class="form-text">Puedes compartir material de apoyo, retroalimentaciones grabadas o guías.</div>
      </div>
      <div class="col-lg-6 d-grid align-self-end">
        <button class="btn btn-primary btn-icon" <?= $estudiantesActividad ? '' : 'disabled' ?>>
          <i class="bi bi-send-check"></i> Enviar respuesta
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($retroalimentaciones->num_rows > 0): ?>
  <div class="card section-card mt-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 fw-semibold mb-0"><i class="bi bi-chat-square-text"></i> Retroalimentaciones de estudiantes</h2>
        <span class="badge rounded-pill bg-primary-subtle text-primary"><i class="bi bi-people"></i> <?= $retroalimentaciones->num_rows ?> registros</span>
      </div>
      <div class="timeline-feedback">
        <?php while ($retro = $retroalimentaciones->fetch_assoc()): ?>
          <div class="timeline-item">
            <div class="timeline-dot <?= $retro['autor'] === 'docente' ? 'bg-primary' : '' ?>"></div>
            <div class="timeline-content">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold text-dark">
                  <?php if ($retro['autor'] === 'docente'): ?>
                    <i class="bi bi-mortarboard-fill me-1"></i>Docente <span class="badge text-bg-light ms-2"><i class="bi bi-person"></i> <?= htmlspecialchars($retro['estudiante_nombre']) ?></span>
                  <?php else: ?>
                    <i class="bi bi-person-badge-fill me-1"></i><?= htmlspecialchars($retro['estudiante_nombre']) ?>
                  <?php endif; ?>
                </span>
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
    </div>
  </div>
<?php else: ?>
  <div class="alert alert-secondary mt-4"><i class="bi bi-chat-square-dots me-2"></i>Aún no hay retroalimentaciones registradas para este reto.</div>
<?php endif; ?>

<style>
  .badge.text-bg-primary-soft {
    background-color: rgba(99, 102, 241, 0.12);
    color: #3730a3;
  }
  .blog-content p {
    line-height: 1.8;
    margin-bottom: 1rem;
    color: #495057;
  }
  .blog-content h2,
  .blog-content h3,
  .blog-content h4 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 600;
    color: #212529;
  }
  .blog-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
    box-shadow: 0 0.5rem 1.5rem rgba(15, 23, 42, 0.15);
  }
  .blog-content ul,
  .blog-content ol {
    padding-left: 1.25rem;
    margin-bottom: 1.25rem;
  }
  .blog-content blockquote {
    border-left: 4px solid rgba(99, 102, 241, 0.4);
    padding-left: 1rem;
    color: #4c51bf;
    font-style: italic;
    background-color: rgba(99, 102, 241, 0.08);
    border-radius: 0.5rem;
  }
</style>

<?php include 'includes/footer.php'; ?>
