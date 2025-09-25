<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

// Crear actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && empty($_POST['actividad_id'])) {
  $nombre = trim($_POST['nombre']);
  if ($nombre !== '') {
    $stmt = $conn->prepare("INSERT INTO actividades (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
  }
  header("Location: actividades.php"); exit;
}

// Editar actividad
$actividadEdit = null;
if (isset($_GET['editar'])) {
  $editarId = (int)$_GET['editar'];
  $stmt = $conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
  $stmt->bind_param("i", $editarId);
  $stmt->execute();
  $actividadEdit = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actividad_id']) && !empty($_POST['actividad_id'])) {
  $actividadId = (int)$_POST['actividad_id'];
  $nombre = trim($_POST['nombre']);
  if ($nombre !== '') {
    $stmt = $conn->prepare("UPDATE actividades SET nombre=? WHERE id=?");
    $stmt->bind_param("si", $nombre, $actividadId);
    $stmt->execute();
  }
  header("Location: actividades.php"); exit;
}

// Eliminar
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];
  $conn->query("DELETE FROM actividades WHERE id=$id");
  header("Location: actividades.php"); exit;
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
