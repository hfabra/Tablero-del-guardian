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
if ($actividad_id <= 0) { echo "<div class='alert alert-danger'>Actividad no valida.</div>"; include 'includes/footer.php'; exit; }

// Obtener actividad
$stmt = $conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i", $actividad_id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
if (!$actividad) { echo "<div class='alert alert-danger'>Actividad no encontrada.</div>"; include 'includes/footer.php'; exit; }

// Agregar reto
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nombre'])) {
  $nombre = trim($_POST['nombre']);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $video_url = trim($_POST['video_url'] ?? '');
  $imagen = subirArchivo('imagen', ['jpg', 'jpeg', 'png', 'gif']);
  $pdf = subirArchivo('pdf', ['pdf']);

  if ($video_url !== '' && !preg_match('/^https?:\/\//i', $video_url)) {
    $video_url = 'https://'.$video_url;
  }
  if ($video_url === '') {
    $video_url = null;
  }

  if ($nombre !== '') {
    $stmt = $conn->prepare("INSERT INTO retos (actividad_id, nombre, descripcion, imagen, video_url, pdf) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $actividad_id, $nombre, $descripcion, $imagen, $video_url, $pdf);
    $stmt->execute();
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
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Retos - <?= htmlspecialchars($actividad['nombre']) ?></h3>
  <a href="actividades.php" class="btn btn-outline-secondary">Volver</a>
</div>

<form method="post" class="row g-3 mb-4" enctype="multipart/form-data">
  <div class="col-md-4">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre del reto" required>
  </div>
  <div class="col-md-4">
    <input type="text" name="descripcion" class="form-control" placeholder="Descripción (opcional)">
  </div>
  <div class="col-md-4">
    <input type="url" name="video_url" class="form-control" placeholder="URL de video de YouTube (opcional)">
  </div>
  <div class="col-md-4">
    <label class="form-label">Imagen (opcional)</label>
    <input type="file" name="imagen" class="form-control" accept="image/*">
  </div>
  <div class="col-md-4">
    <label class="form-label">Archivo PDF (opcional)</label>
    <input type="file" name="pdf" class="form-control" accept="application/pdf">
  </div>
  <div class="col-md-4 d-grid align-content-end">
    <button class="btn btn-primary">Agregar reto</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Adjuntos</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php while($r = $retos->fetch_assoc()): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><a href="reto_detalle.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['nombre']) ?></a></td>
        <td><?= htmlspecialchars($r['descripcion']) ?></td>
        <td>
          <?php if(!empty($r['imagen'])): ?><span class="badge bg-info me-1">Imagen</span><?php endif; ?>
          <?php if(!empty($r['video_url'])): ?><span class="badge bg-danger me-1">Video</span><?php endif; ?>
          <?php if(!empty($r['pdf'])): ?><span class="badge bg-secondary">PDF</span><?php endif; ?>
        </td>
        <td>
          <a class="btn btn-sm btn-danger" href="retos.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar reto?');">Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
