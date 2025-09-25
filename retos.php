<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$iconosDisponibles = [
  '🎯' => 'Objetivo',
  '🚀' => 'Despegue',
  '🧠' => 'Ingenio',
  '⚙️' => 'Construcción',
  '📚' => 'Aprendizaje',
  '🌟' => 'Destacado',
  '🧩' => 'Rompecabezas'
];

// Confirmar que la tabla tiene soporte para iconos sin alterar el esquema en tiempo de ejecución.
$columnaIcono = $conn->query("SHOW COLUMNS FROM retos LIKE 'icono'");
$iconosHabilitados = ($columnaIcono instanceof mysqli_result && $columnaIcono->num_rows > 0);
if ($columnaIcono instanceof mysqli_result) {
  $columnaIcono->free();
}

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

// Crear / editar reto
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_reto'])) {
  $nombre = trim($_POST['nombre']);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $video_url = trim($_POST['video_url'] ?? '');
  $icono = $iconosHabilitados ? ($_POST['icono'] ?? '') : '🎯';
  $reto_id = isset($_POST['reto_id']) ? (int)$_POST['reto_id'] : 0;

  if ($iconosHabilitados && !array_key_exists($icono, $iconosDisponibles)) {
    $claves = array_keys($iconosDisponibles);
    $icono = $claves[0];
  }

  $imagenSubida = subirArchivo('imagen', ['jpg', 'jpeg', 'png', 'gif']);
  $pdfSubido = subirArchivo('pdf', ['pdf']);

  if ($video_url !== '' && !preg_match('/^https?:\/\//i', $video_url)) {
    $video_url = 'https://'.$video_url;
  }
  if ($video_url === '') {
    $video_url = null;
  }

  if ($nombre !== '') {
    if ($reto_id > 0) {
      $stmt = $conn->prepare("SELECT imagen, pdf FROM retos WHERE id=? AND actividad_id=?");
      $stmt->bind_param("ii", $reto_id, $actividad_id);
      $stmt->execute();
      $actual = $stmt->get_result()->fetch_assoc();
      if (!$actual) {
        header("Location: retos.php?actividad_id=".$actividad_id); exit;
      }
      $imagenActual = $actual['imagen'] ?? null;
      $pdfActual = $actual['pdf'] ?? null;

      if ($imagenSubida) {
        if ($imagenActual && is_file(__DIR__.'/'.$imagenActual)) {
          @unlink(__DIR__.'/'.$imagenActual);
        }
        $imagenActual = $imagenSubida;
      }

      if ($pdfSubido) {
        if ($pdfActual && is_file(__DIR__.'/'.$pdfActual)) {
          @unlink(__DIR__.'/'.$pdfActual);
        }
        $pdfActual = $pdfSubido;
      }

      if ($iconosHabilitados) {
        $stmt = $conn->prepare("UPDATE retos SET nombre=?, descripcion=?, imagen=?, video_url=?, pdf=?, icono=? WHERE id=? AND actividad_id=?");
        $stmt->bind_param("ssssssii", $nombre, $descripcion, $imagenActual, $video_url, $pdfActual, $icono, $reto_id, $actividad_id);
      } else {
        $stmt = $conn->prepare("UPDATE retos SET nombre=?, descripcion=?, imagen=?, video_url=?, pdf=? WHERE id=? AND actividad_id=?");
        $stmt->bind_param("sssssii", $nombre, $descripcion, $imagenActual, $video_url, $pdfActual, $reto_id, $actividad_id);
      }
      $stmt->execute();
    } else {
      if ($iconosHabilitados) {
        $stmt = $conn->prepare("INSERT INTO retos (actividad_id, nombre, descripcion, imagen, video_url, pdf, icono) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $actividad_id, $nombre, $descripcion, $imagenSubida, $video_url, $pdfSubido, $icono);
      } else {
        $stmt = $conn->prepare("INSERT INTO retos (actividad_id, nombre, descripcion, imagen, video_url, pdf) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $actividad_id, $nombre, $descripcion, $imagenSubida, $video_url, $pdfSubido);
      }
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

$selectCols = "id, nombre, descripcion, imagen, video_url, pdf" . ($iconosHabilitados ? ", icono" : "");
$res = $conn->prepare("SELECT $selectCols FROM retos WHERE actividad_id=? ORDER BY id DESC");
$res->bind_param("i", $actividad_id);
$res->execute();
$retos = $res->get_result();

$retoEditar = null;
if (isset($_GET['editar'])) {
  $editar_id = (int)$_GET['editar'];
  $selectCols = "id, nombre, descripcion, imagen, video_url, pdf" . ($iconosHabilitados ? ", icono" : "");
  $stmt = $conn->prepare("SELECT $selectCols FROM retos WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $editar_id, $actividad_id);
  $stmt->execute();
  $retoEditar = $stmt->get_result()->fetch_assoc();
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Retos - <?= htmlspecialchars($actividad['nombre']) ?></h3>
  <a href="actividades.php" class="btn btn-outline-secondary">Volver</a>
</div>

<?php if (!$iconosHabilitados): ?>
  <div class="alert alert-warning">El esquema actual no admite iconos personalizados. Actualiza la base de datos con la última versión del archivo <code>sql/tablero_puntuaciones.sql</code> para habilitarlos.</div>
<?php endif; ?>

<form method="post" class="row g-3 mb-4" enctype="multipart/form-data">
  <input type="hidden" name="guardar_reto" value="1">
  <input type="hidden" name="reto_id" value="<?= $retoEditar['id'] ?? 0 ?>">
  <div class="col-md-4">
    <label class="form-label" for="nombre-reto">Nombre del reto</label>
    <input id="nombre-reto" type="text" name="nombre" class="form-control" placeholder="Nombre del reto" value="<?= htmlspecialchars($retoEditar['nombre'] ?? '') ?>" required>
  </div>
  <div class="col-md-4">
    <label class="form-label" for="descripcion-reto">Descripción</label>
    <textarea id="descripcion-reto" name="descripcion" class="form-control" rows="2" placeholder="Descripción breve del reto (opcional)"><?= htmlspecialchars($retoEditar['descripcion'] ?? '') ?></textarea>
  </div>
  <div class="col-md-4">
    <label class="form-label" for="video-reto">Video de YouTube</label>
    <input id="video-reto" type="url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?= htmlspecialchars($retoEditar['video_url'] ?? '') ?>">
    <div class="form-text">Pega el enlace completo del video (opcional).</div>
  </div>
  <?php if ($iconosHabilitados): ?>
    <div class="col-md-4">
      <label class="form-label" for="icono-reto">Icono del reto</label>
      <select id="icono-reto" name="icono" class="form-select" required>
        <?php foreach ($iconosDisponibles as $iconoValor => $iconoNombre): ?>
          <option value="<?= htmlspecialchars($iconoValor) ?>" <?= (($retoEditar['icono'] ?? '') === $iconoValor) ? 'selected' : '' ?>><?= $iconoValor ?> - <?= htmlspecialchars($iconoNombre) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="col-md-4">
    <label class="form-label">Imagen (opcional)</label>
    <input type="file" name="imagen" class="form-control" accept="image/*">
    <?php if (!empty($retoEditar['imagen'])): ?>
      <div class="form-text">Imagen actual:</div>
      <img src="<?= htmlspecialchars($retoEditar['imagen']) ?>" alt="Imagen del reto" class="img-thumbnail" style="max-width: 140px;">
    <?php endif; ?>
  </div>
  <div class="col-md-4">
    <label class="form-label">Archivo PDF (opcional)</label>
    <input type="file" name="pdf" class="form-control" accept="application/pdf">
    <?php if (!empty($retoEditar['pdf'])): ?>
      <div class="form-text">Documento cargado: <a href="<?= htmlspecialchars($retoEditar['pdf']) ?>" target="_blank" rel="noopener">Abrir PDF actual</a></div>
    <?php endif; ?>
  </div>
  <div class="col-md-4 d-grid align-content-end">
    <button class="btn btn-primary"><?= $retoEditar ? 'Actualizar reto' : 'Agregar reto' ?></button>
  </div>
</form>

<?php if ($retoEditar): ?>
  <div class="alert alert-info">Editando el reto "<?= htmlspecialchars($retoEditar['nombre']) ?>". <a href="retos.php?actividad_id=<?= $actividad_id ?>">Cancelar edición</a></div>
<?php endif; ?>

<?php if ($retos->num_rows === 0): ?>
  <div class="alert alert-secondary">Aún no hay retos registrados para esta actividad.</div>
<?php else: ?>
  <div class="row g-3">
    <?php while($r = $retos->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center mb-3">
              <div class="reto-icon me-3"><?= htmlspecialchars($r['icono'] ?? '🎯') ?></div>
              <div>
                <h5 class="card-title mb-0"><a href="reto_detalle.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['nombre']) ?></a></h5>
                <small class="text-muted">Reto #<?= $r['id'] ?></small>
              </div>
            </div>
            <p class="card-text flex-grow-1"><?= $r['descripcion'] ? htmlspecialchars($r['descripcion']) : '<span class="text-muted">Sin descripción</span>' ?></p>
            <div class="mb-3">
              <?php if(!empty($r['imagen'])): ?><span class="badge bg-info me-1">Imagen</span><?php endif; ?>
              <?php if(!empty($r['video_url'])): ?><span class="badge bg-danger me-1">Video</span><?php endif; ?>
              <?php if(!empty($r['pdf'])): ?><span class="badge bg-secondary me-1">PDF</span><?php endif; ?>
            </div>
            <div class="mt-auto">
              <a class="btn btn-sm btn-outline-primary me-2" href="reto_detalle.php?id=<?= $r['id'] ?>">Ver detalle</a>
              <a class="btn btn-sm btn-outline-secondary me-2" href="retos.php?actividad_id=<?= $actividad_id ?>&editar=<?= $r['id'] ?>">Editar</a>
              <a class="btn btn-sm btn-danger" href="retos.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar reto?');">Eliminar</a>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
