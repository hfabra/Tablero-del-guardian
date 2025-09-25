<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

function subirArchivo(string $campo, array $extensionesPermitidas): ?string {
  if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
    return null;
  }

  $archivo = $_FILES[$campo];
  $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($error === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  if ($error !== UPLOAD_ERR_OK) {
    return null;
  }

  $extension = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
  if ($extension === '' || !in_array($extension, $extensionesPermitidas, true)) {
    return null;
  }

  $rutaDirectorio = __DIR__.'/assets/uploads/retos';
  if (!is_dir($rutaDirectorio)) {
    mkdir($rutaDirectorio, 0775, true);
  }

  $nombreArchivo = uniqid('reto_', true).'.'.$extension;
  $rutaDestino = $rutaDirectorio.'/'.$nombreArchivo;

  if (!move_uploaded_file($archivo['tmp_name'] ?? '', $rutaDestino)) {
    return null;
  }

  return 'assets/uploads/retos/'.$nombreArchivo;
}

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id <= 0) {
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-exclamation-diamond'></i></div><h4 class='fw-semibold mb-2'>Actividad no válida</h4><p class='text-muted mb-0'>Selecciona una actividad desde <a href='actividades.php'>Actividades</a> para gestionar sus retos.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Obtener actividad
$stmt = $conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i", $actividad_id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
if (!$actividad) {
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Actividad no encontrada</h4><p class='text-muted mb-0'>Es posible que haya sido eliminada. Revisa el listado de actividades disponibles.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Editar reto
$retoEdit = null;
if (isset($_GET['editar'])) {
  $editarId = (int)$_GET['editar'];
$stmt = $conn->prepare("SELECT id, nombre, descripcion, contenido_blog, imagen, video_url, pdf FROM retos WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $editarId, $actividad_id);
  $stmt->execute();
  $retoEdit = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nombre']) && empty($_POST['reto_id'])) {
  $nombre = trim($_POST['nombre']);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $video_url = trim($_POST['video_url'] ?? '');
  $contenido_blog = trim($_POST['contenido_blog'] ?? '');
  $imagen = subirArchivo('imagen', ['jpg', 'jpeg', 'png', 'gif']);
  $pdf = subirArchivo('pdf', ['pdf']);

  if ($video_url !== '' && !preg_match('/^https?:\/\//i', $video_url)) {
    $video_url = 'https://'.$video_url;
  }
  if ($video_url === '') {
    $video_url = null;
  }
  if ($contenido_blog === '') {
    $contenido_blog = null;
  }

  if ($nombre !== '') {
    $stmt = $conn->prepare("INSERT INTO retos (actividad_id, nombre, descripcion, contenido_blog, imagen, video_url, pdf) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $actividad_id, $nombre, $descripcion, $contenido_blog, $imagen, $video_url, $pdf);
    $stmt->execute();
  }
  header("Location: retos.php?actividad_id=".$actividad_id); exit;
}

// Actualizar reto
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reto_id']) && !empty($_POST['reto_id'])) {
  $retoId = (int)$_POST['reto_id'];
  $nombre = trim($_POST['nombre']);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $video_url = trim($_POST['video_url'] ?? '');
  $contenido_blog = trim($_POST['contenido_blog'] ?? '');
  if ($video_url !== '' && !preg_match('/^https?:\/\//i', $video_url)) {
    $video_url = 'https://'.$video_url;
  }
  if ($video_url === '') {
    $video_url = null;
  }
  if ($contenido_blog === '') {
    $contenido_blog = null;
  }

  $stmt = $conn->prepare("SELECT imagen, pdf FROM retos WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $retoId, $actividad_id);
  $stmt->execute();
  $actual = $stmt->get_result()->fetch_assoc();
  if ($actual) {
    $imagen = $actual['imagen'];
    $pdf = $actual['pdf'];

    $nuevaImagen = subirArchivo('imagen', ['jpg', 'jpeg', 'png', 'gif']);
    if ($nuevaImagen) {
      if (!empty($imagen)) {
        $ruta = __DIR__.'/'.$imagen;
        if (is_file($ruta)) {
          unlink($ruta);
        }
      }
      $imagen = $nuevaImagen;
    }

    $nuevoPdf = subirArchivo('pdf', ['pdf']);
    if ($nuevoPdf) {
      if (!empty($pdf)) {
        $ruta = __DIR__.'/'.$pdf;
        if (is_file($ruta)) {
          unlink($ruta);
        }
      }
      $pdf = $nuevoPdf;
    }

    if ($nombre !== '') {
      $stmt = $conn->prepare("UPDATE retos SET nombre=?, descripcion=?, contenido_blog=?, imagen=?, video_url=?, pdf=? WHERE id=? AND actividad_id=?");
      $stmt->bind_param("ssssssii", $nombre, $descripcion, $contenido_blog, $imagen, $video_url, $pdf, $retoId, $actividad_id);
      $stmt->execute();
    }
  }
  header("Location: retos.php?actividad_id=".$actividad_id); exit;
}

// Eliminar reto
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];
  $stmt = $conn->prepare("SELECT imagen, pdf FROM retos WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $id, $actividad_id);
  $stmt->execute();
  if ($registro = $stmt->get_result()->fetch_assoc()) {
    foreach (['imagen', 'pdf'] as $campo) {
      if (!empty($registro[$campo])) {
        $ruta = __DIR__.'/'.$registro[$campo];
        if (is_file($ruta)) {
          unlink($ruta);
        }
      }
    }
  }

  $stmt = $conn->prepare("DELETE FROM retos WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $id, $actividad_id);
  $stmt->execute();
  header("Location: retos.php?actividad_id=".$actividad_id); exit;
}

$res = $conn->prepare("SELECT id, nombre, descripcion, imagen, video_url, pdf FROM retos WHERE actividad_id=? ORDER BY id DESC");
$res->bind_param("i", $actividad_id);
$res->execute();
$retos = $res->get_result();
?>

<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-flag"></i> Retos</h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($actividad['nombre']) ?></span>. Diseña desafíos motivadores para tu grupo.</p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="actividades.php" class="btn btn-outline-primary btn-icon"><i class="bi bi-arrow-left"></i> Volver a actividades</a>
      <a href="puntuar.php?actividad_id=<?= $actividad_id ?>" class="btn btn-success btn-icon"><i class="bi bi-graph-up"></i> Ver tablero</a>
    </div>
  </div>
</section>

<?php if ($retoEdit): ?>
  <div class="alert alert-info shadow-sm">Editando reto <strong><?= htmlspecialchars($retoEdit['nombre']) ?></strong>. <a href="retos.php?actividad_id=<?= $actividad_id ?>" class="alert-link">Cancelar</a></div>
<?php endif; ?>

<form method="post" class="card section-card mb-4" enctype="multipart/form-data">
  <input type="hidden" name="reto_id" value="<?= $retoEdit['id'] ?? '' ?>">
  <div class="card-body">
    <div class="row g-4 align-items-start">
      <div class="col-lg-4">
        <label for="nombre" class="form-label fw-semibold">Nombre del reto</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-flag-fill"></i></span>
          <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Super misión semanal" value="<?= htmlspecialchars($retoEdit['nombre'] ?? '') ?>" required>
        </div>
      </div>
      <div class="col-lg-4">
        <label for="descripcion" class="form-label fw-semibold">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="1" class="form-control" placeholder="Breve contexto del reto (opcional)"><?= htmlspecialchars($retoEdit['descripcion'] ?? '') ?></textarea>
      </div>
      <div class="col-lg-4">
        <label for="video_url" class="form-label fw-semibold">Video de referencia</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-youtube"></i></span>
          <input type="url" id="video_url" name="video_url" class="form-control" placeholder="https://" value="<?= htmlspecialchars($retoEdit['video_url'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <label for="imagen" class="form-label fw-semibold">Imagen destacada</label>
        <?php if (!empty($retoEdit) && !empty($retoEdit['imagen'])): ?><div class="form-text mb-2">Cargar un archivo reemplazará la imagen actual.</div><?php endif; ?>
        <input type="file" id="imagen" name="imagen" class="form-control" accept="image/*">
      </div>
      <div class="col-md-6 col-lg-4">
        <label for="pdf" class="form-label fw-semibold">Documento PDF</label>
        <?php if (!empty($retoEdit) && !empty($retoEdit['pdf'])): ?><div class="form-text mb-2">Se reemplazará el documento existente.</div><?php endif; ?>
        <input type="file" id="pdf" name="pdf" class="form-control" accept="application/pdf">
      </div>
      <div class="col-12">
        <label for="contenido_blog" class="form-label fw-semibold">Contenido ampliado</label>
        <textarea id="contenido_blog" name="contenido_blog" class="form-control" rows="10" placeholder="Redacta la guía completa del reto, agrega imágenes, encabezados y más."><?= htmlspecialchars($retoEdit['contenido_blog'] ?? '') ?></textarea>
        <div class="form-text">Utiliza el editor para escribir instrucciones largas o recursos adicionales al estilo de un blog.</div>
      </div>
      <div class="col-lg-4 d-grid align-content-end">
        <button class="btn btn-primary btn-icon btn-lg">
          <i class="bi <?= $retoEdit ? 'bi-arrow-repeat' : 'bi-plus-circle' ?>"></i>
          <?= $retoEdit ? 'Actualizar reto' : 'Agregar reto' ?>
        </button>
      </div>
    </div>
  </div>
</form>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th class="text-uppercase">#</th>
          <th class="text-uppercase">Nombre</th>
          <th class="text-uppercase">Descripción</th>
          <th class="text-uppercase">Recursos</th>
          <th class="text-end text-uppercase">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while($r = $retos->fetch_assoc()): ?>
          <tr>
            <td><span class="badge text-bg-primary-soft">#<?= $r['id'] ?></span></td>
            <td class="fw-semibold text-dark"><a href="reto_detalle.php?id=<?= $r['id'] ?>" class="text-decoration-none text-dark"><i class="bi bi-link-45deg me-1"></i><?= htmlspecialchars($r['nombre']) ?></a></td>
            <td class="text-muted"><?= htmlspecialchars($r['descripcion']) ?: '<span class="fst-italic">Sin descripción</span>' ?></td>
            <td>
              <div class="list-actions">
                <?php if(!empty($r['imagen'])): ?><span class="badge rounded-pill text-bg-primary-subtle"><i class="bi bi-image-fill me-1"></i>Imagen</span><?php endif; ?>
                <?php if(!empty($r['video_url'])): ?><span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-camera-video-fill me-1"></i>Video</span><?php endif; ?>
                <?php if(!empty($r['pdf'])): ?><span class="badge rounded-pill bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF</span><?php endif; ?>
              </div>
            </td>
            <td class="text-end">
              <div class="btn-group" role="group">
                <a class="btn btn-sm btn-outline-secondary btn-icon" href="retos.php?actividad_id=<?= $actividad_id ?>&editar=<?= $r['id'] ?>"><i class="bi bi-pencil"></i> Editar</a>
                <a class="btn btn-sm btn-outline-danger btn-icon" href="retos.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar reto?');"><i class="bi bi-trash"></i> Quitar</a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://cdn.tiny.cloud/1/1w1n2wobijwqds7rzeg4st8gsbmxv23nb0y1qbuoyhbcn3kt/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#contenido_blog',
    language: 'es',
    height: 360,
    menubar: false,
    plugins: 'link lists image table media autoresize code',
    toolbar: 'undo redo | blocks | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat code',
    branding: false,
    convert_urls: false,
    image_caption: true,
    image_title: true,
    content_style: "body { font-family: 'Inter', sans-serif; font-size: 16px; }"
  });
</script>

<?php include 'includes/footer.php'; ?>
