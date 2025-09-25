<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

function sincronizarEstudiantesActividad(mysqli $conn, int $actividadId, array $idsNuevos): void {
  $idsNuevos = array_values(array_unique(array_filter(array_map('intval', $idsNuevos))));

  $actuales = [];
  $stmtActuales = $conn->prepare('SELECT estudiante_id FROM actividad_estudiante WHERE actividad_id = ?');
  $stmtActuales->bind_param('i', $actividadId);
  $stmtActuales->execute();
  $resActuales = $stmtActuales->get_result();
  while ($fila = $resActuales->fetch_assoc()) {
    $actuales[] = (int)$fila['estudiante_id'];
  }
  $stmtActuales->close();

  $agregar = array_diff($idsNuevos, $actuales);
  $quitar = array_diff($actuales, $idsNuevos);

  if ($agregar) {
    $stmtInsert = $conn->prepare('INSERT INTO actividad_estudiante (actividad_id, estudiante_id) VALUES (?, ?)');
    foreach ($agregar as $estId) {
      $stmtInsert->bind_param('ii', $actividadId, $estId);
      if (!$stmtInsert->execute()) {
        throw new RuntimeException('No se pudo asignar un estudiante a la actividad.');
      }
    }
    $stmtInsert->close();
  }

  if ($quitar) {
    $stmtDeleteRelacion = $conn->prepare('DELETE FROM actividad_estudiante WHERE actividad_id = ? AND estudiante_id = ?');
    $stmtDeletePuntajes = $conn->prepare('DELETE FROM puntuaciones WHERE actividad_id = ? AND estudiante_id = ?');
    $stmtDeleteRetro = $conn->prepare('DELETE r FROM retroalimentaciones r INNER JOIN retos rt ON rt.id = r.reto_id WHERE r.estudiante_id = ? AND rt.actividad_id = ?');

    foreach ($quitar as $estId) {
      $stmtDeleteRelacion->bind_param('ii', $actividadId, $estId);
      if (!$stmtDeleteRelacion->execute()) {
        throw new RuntimeException('No se pudo quitar un estudiante de la actividad.');
      }

      $stmtDeletePuntajes->bind_param('ii', $actividadId, $estId);
      $stmtDeletePuntajes->execute();

      $stmtDeleteRetro->bind_param('ii', $estId, $actividadId);
      $stmtDeleteRetro->execute();
    }

    $stmtDeleteRelacion->close();
    $stmtDeletePuntajes->close();
    $stmtDeleteRetro->close();
  }
}

function obtenerEstudiantesAsignados(mysqli $conn, int $actividadId): array {
  $asignados = [];
  $stmt = $conn->prepare('SELECT estudiante_id FROM actividad_estudiante WHERE actividad_id = ?');
  $stmt->bind_param('i', $actividadId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($fila = $res->fetch_assoc()) {
    $asignados[] = (int)$fila['estudiante_id'];
  }
  $stmt->close();
  return $asignados;
}

$errorActividad = null;

// Crear actividad
$estudiantesSeleccionados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && empty($_POST['actividad_id'])) {
  $nombre = trim($_POST['nombre']);
  $estudiantesSeleccionados = array_map('intval', $_POST['estudiantes_asignados'] ?? []);

  if ($nombre !== '') {
    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("INSERT INTO actividades (nombre) VALUES (?)");
      $stmt->bind_param("s", $nombre);
      if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo crear la actividad.');
      }
      $actividadId = $conn->insert_id;
      $stmt->close();

      sincronizarEstudiantesActividad($conn, $actividadId, $estudiantesSeleccionados);

      $conn->commit();
      header("Location: actividades.php");
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errorActividad = 'No se pudo crear la actividad. Intenta nuevamente.';
    }
  } else {
    $errorActividad = 'Ingresa un nombre para la actividad.';
  }
}

// Editar actividad
$actividadEdit = null;
if (isset($_GET['editar'])) {
  $editarId = (int)$_GET['editar'];
  $stmt = $conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
  $stmt->bind_param("i", $editarId);
  $stmt->execute();
  $actividadEdit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($actividadEdit) {
    $estudiantesSeleccionados = obtenerEstudiantesAsignados($conn, (int)$actividadEdit['id']);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actividad_id']) && !empty($_POST['actividad_id'])) {
  $actividadId = (int)$_POST['actividad_id'];
  $nombre = trim($_POST['nombre']);
  $estudiantesSeleccionados = array_map('intval', $_POST['estudiantes_asignados'] ?? []);

  if ($nombre !== '') {
    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare("UPDATE actividades SET nombre=? WHERE id=?");
      $stmt->bind_param("si", $nombre, $actividadId);
      if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo actualizar la actividad.');
      }
      $stmt->close();

      sincronizarEstudiantesActividad($conn, $actividadId, $estudiantesSeleccionados);

      $conn->commit();
      header("Location: actividades.php");
      exit;
    } catch (Throwable $e) {
      $conn->rollback();
      $errorActividad = 'No se pudo actualizar la actividad. Intenta nuevamente.';
      $actividadEdit = ['id' => $actividadId, 'nombre' => $nombre];
    }
  } else {
    $errorActividad = 'Ingresa un nombre para la actividad.';
    $actividadEdit = ['id' => $actividadId, 'nombre' => $nombre];
  }
}

// Eliminar
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];
  $conn->query("DELETE FROM actividades WHERE id=$id");
  header("Location: actividades.php"); exit;
}

$listaEstudiantes = [];
$resEstudiantes = $conn->query('SELECT id, nombre, usuario FROM estudiantes ORDER BY nombre ASC');
if ($resEstudiantes) {
  while ($fila = $resEstudiantes->fetch_assoc()) {
    $listaEstudiantes[] = $fila;
  }
  $resEstudiantes->close();
}

$res = $conn->query("SELECT id, nombre, fecha_creacion FROM actividades ORDER BY id DESC");
?>
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-kanban"></i> Actividades</h1>
      <p class="text-muted mb-0">Administra los retos, estudiantes y tableros de cada actividad en un solo lugar.</p>
    </div>
    <div class="text-md-end">
      <span class="badge rounded-pill text-bg-primary-subtle text-primary"><i class="bi bi-lightning-charge-fill me-1"></i>Organiza y motiva</span>
    </div>
  </div>
</section>

<?php if ($actividadEdit): ?>
  <div class="alert alert-info shadow-sm">Editando actividad <strong><?= htmlspecialchars($actividadEdit['nombre']) ?></strong>. <a href="actividades.php" class="alert-link">Cancelar</a></div>
<?php endif; ?>

<?php if ($errorActividad): ?>
  <div class="alert alert-danger shadow-sm">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorActividad) ?>
  </div>
<?php endif; ?>

<form method="post" class="card border-0 shadow-sm mb-4">
  <input type="hidden" name="actividad_id" value="<?= $actividadEdit['id'] ?? '' ?>">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <label for="nombre" class="form-label fw-semibold">Nombre de la actividad</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-journal-text"></i></span>
          <input type="text" id="nombre" name="nombre" class="form-control form-control-lg" placeholder="Ej. Reto de Matemáticas" value="<?= htmlspecialchars($actividadEdit['nombre'] ?? '') ?>" required>
        </div>
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-primary btn-lg">
          <i class="bi <?= $actividadEdit ? 'bi-arrow-repeat' : 'bi-plus-circle' ?> me-2"></i>
          <?= $actividadEdit ? 'Actualizar actividad' : 'Crear actividad' ?>
        </button>
      </div>
    </div>
    <div class="row g-3 align-items-center mt-1">
      <div class="col-12">
        <label for="estudiantes_asignados" class="form-label fw-semibold">Asignar estudiantes existentes</label>
        <select id="estudiantes_asignados" name="estudiantes_asignados[]" class="form-select" multiple size="6" <?= $listaEstudiantes ? '' : 'disabled' ?> aria-describedby="ayuda-estudiantes-asignados">
          <?php foreach ($listaEstudiantes as $est): ?>
            <?php $seleccionado = in_array((int)$est['id'], $estudiantesSeleccionados, true) ? 'selected' : ''; ?>
            <option value="<?= $est['id'] ?>" <?= $seleccionado ?>><?= htmlspecialchars($est['nombre']) ?> (<?= htmlspecialchars($est['usuario']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="form-text" id="ayuda-estudiantes-asignados">
          <?php if ($listaEstudiantes): ?>
            Mantén presionadas las teclas Ctrl o Cmd para elegir varios estudiantes. También puedes agregarlos más tarde desde la sección de estudiantes.
          <?php else: ?>
            Aún no hay estudiantes registrados. Podrás asignarlos después desde la sección de estudiantes.
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Estudiantes</th>
            <th>Retos</th>
            <th>Tablero</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while($a = $res->fetch_assoc()): ?>
          <tr>
            <td><span class="badge rounded-pill text-bg-primary-soft">#<?= $a['id'] ?></span></td>
            <td class="fw-medium text-dark"><?= htmlspecialchars($a['nombre']) ?></td>
            <td class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= $a['fecha_creacion'] ?></td>
            <td>
              <a class="btn btn-outline-secondary btn-sm rounded-pill" href="estudiantes.php?actividad_id=<?= $a['id'] ?>">
                <i class="bi bi-people-fill me-1"></i>Gestionar
              </a>
            </td>
            <td>
              <a class="btn btn-outline-primary btn-sm rounded-pill" href="retos.php?actividad_id=<?= $a['id'] ?>">
                <i class="bi bi-flag me-1"></i>Retos
              </a>
            </td>
            <td>
              <a class="btn btn-success btn-sm rounded-pill" href="puntuar.php?actividad_id=<?= $a['id'] ?>">
                <i class="bi bi-graph-up-arrow me-1"></i>Tablero
              </a>
            </td>
            <td class="text-end">
              <div class="btn-group" role="group">
                <a class="btn btn-outline-secondary btn-sm" href="actividades.php?editar=<?= $a['id'] ?>"><i class="bi bi-pencil-square"></i></a>
                <a class="btn btn-outline-danger btn-sm" href="actividades.php?eliminar=<?= $a['id'] ?>" onclick="return confirm('¿Eliminar actividad y todo su contenido?');"><i class="bi bi-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
