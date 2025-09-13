<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Crear actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
  $nombre = trim($_POST['nombre']);
  if ($nombre !== '') {
    $stmt = $conn->prepare("INSERT INTO actividades (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
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
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Actividades</h3>
</div>

<form method="post" class="row g-3 mb-4">
  <div class="col-md-8">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre de la actividad" required>
  </div>
  <div class="col-md-4 d-grid">
    <button class="btn btn-primary">Crear actividad</button>
  </div>
</form>

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
      <td><a class="btn btn-danger btn-sm" href="actividades.php?eliminar=<?= $a['id'] ?>" onclick="return confirm('¿Eliminar actividad y todo su contenido?');">Eliminar</a></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
