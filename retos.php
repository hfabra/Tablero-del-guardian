<?php
require_once 'includes/db.php';
include 'includes/header.php';

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
  if ($nombre !== '') {
    $stmt = $conn->prepare("INSERT INTO retos (actividad_id, nombre, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $actividad_id, $nombre, $descripcion);
    $stmt->execute();
  }
  header("Location: retos.php?actividad_id=".$actividad_id); exit;
}

// Eliminar reto
if (isset($_GET['eliminar'])) {
  $id = (int)$_GET['eliminar'];
  $conn->query("DELETE FROM retos WHERE id=$id AND actividad_id=$actividad_id");
  header("Location: retos.php?actividad_id=".$actividad_id); exit;
}

$res = $conn->prepare("SELECT id, nombre, descripcion FROM retos WHERE actividad_id=? ORDER BY id DESC");
$res->bind_param("i", $actividad_id);
$res->execute();
$retos = $res->get_result();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Retos - <?= htmlspecialchars($actividad['nombre']) ?></h3>
  <a href="actividades.php" class="btn btn-outline-secondary">Volver</a>
</div>

<form method="post" class="row g-3 mb-4">
  <div class="col-md-4">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre del reto" required>
  </div>
  <div class="col-md-6">
    <input type="text" name="descripcion" class="form-control" placeholder="Descripción (opcional)">
  </div>
  <div class="col-md-2 d-grid">
    <button class="btn btn-primary">Agregar reto</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php while($r = $retos->fetch_assoc()): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['nombre']) ?></td>
        <td><?= htmlspecialchars($r['descripcion']) ?></td>
        <td>
          <a class="btn btn-sm btn-danger" href="retos.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $r['id'] ?>" onclick="return confirm('¿Eliminar reto?');">Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
