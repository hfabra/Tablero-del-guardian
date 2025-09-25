<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

// Crear / actualizar actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
  $nombre = trim($_POST['nombre']);
  $actividad_id = isset($_POST['actividad_id']) ? (int)$_POST['actividad_id'] : 0;

  if ($nombre !== '') {
    if ($actividad_id > 0) {
      $stmt = $conn->prepare("UPDATE actividades SET nombre=? WHERE id=?");
      $stmt->bind_param("si", $nombre, $actividad_id);
      $stmt->execute();
    } else {
      $stmt = $conn->prepare("INSERT INTO actividades (nombre) VALUES (?)");
      $stmt->bind_param("s", $nombre);
      $stmt->execute();
    }
  }

  header("Location: actividades.php"); exit;
}

// Eliminar
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];
  $conn->query("DELETE FROM actividades WHERE id=$id");
  header("Location: actividades.php"); exit;
}

$actividadEditar = null;
if (isset($_GET['editar'])) {
  $editar_id = (int)$_GET['editar'];
  $stmt = $conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
  $stmt->bind_param("i", $editar_id);
  $stmt->execute();
  $actividadEditar = $stmt->get_result()->fetch_assoc();
}

$res = $conn->query("SELECT id, nombre, fecha_creacion FROM actividades ORDER BY id DESC");
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Actividades</h3>
</div>

<form method="post" class="row g-3 mb-4">
  <input type="hidden" name="actividad_id" value="<?= $actividadEditar['id'] ?? 0 ?>">
  <div class="col-md-8">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre de la actividad" value="<?= htmlspecialchars($actividadEditar['nombre'] ?? '') ?>" required>
  </div>
  <div class="col-md-4 d-grid">
    <button class="btn btn-primary"><?= $actividadEditar ? 'Actualizar actividad' : 'Crear actividad' ?></button>
  </div>
</form>

<?php if ($actividadEditar): ?>
  <div class="alert alert-info">Editando la actividad "<?= htmlspecialchars($actividadEditar['nombre']) ?>". <a href="actividades.php">Cancelar edición</a></div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <thead>
    <tr>
      <th>#</th><th>Nombre</th><th>Fecha</th>
      <th>Estudiantes</th>
      <th>Retos</th>
      <th>Tablero</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php while($a = $res->fetch_assoc()): ?>
    <tr>
      <td><?= $a['id'] ?></td>
      <td><?= htmlspecialchars($a['nombre']) ?></td>
      <td><?= $a['fecha_creacion'] ?></td>
      <td><a class="btn btn-outline-secondary btn-sm" href="estudiantes.php?actividad_id=<?= $a['id'] ?>">Gestionar</a></td>
      <td><a class="btn btn-outline-primary btn-sm" href="retos.php?actividad_id=<?= $a['id'] ?>">Retos</a></td>
      <td><a class="btn btn-success btn-sm" href="puntuar.php?actividad_id=<?= $a['id'] ?>">Abrir tablero</a></td>
      <td>
        <a class="btn btn-sm btn-outline-secondary" href="actividades.php?editar=<?= $a['id'] ?>">Editar</a>
        <a class="btn btn-danger btn-sm" href="actividades.php?eliminar=<?= $a['id'] ?>" onclick="return confirm('¿Eliminar actividad y todo su contenido?');">Eliminar</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
