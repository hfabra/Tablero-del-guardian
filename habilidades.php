<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nombre'])) {
  $nombre = trim($_POST['nombre']);
  if ($nombre!=='') {
    $stmt=$conn->prepare("INSERT INTO habilidades (nombre) VALUES (?)");
    $stmt->bind_param("s",$nombre);
    $stmt->execute();
  }
  header("Location: habilidades.php"); exit;
}

if (isset($_GET['eliminar'])) {
  $id=(int)$_GET['eliminar'];
  $conn->query("DELETE FROM habilidades WHERE id=$id");
  header("Location: habilidades.php"); exit;
}

$res=$conn->query("SELECT id, nombre FROM habilidades ORDER BY id ASC");
?>
<h3>Habilidades</h3>

<form method="post" class="row g-3 mb-4">
  <div class="col-md-8">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre de la habilidad" required>
  </div>
  <div class="col-md-4 d-grid">
    <button class="btn btn-primary">Agregar</button>
  </div>
</form>

<table class="table table-striped align-middle">
  <thead><tr><th>#</th><th>Habilidad</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php while($h=$res->fetch_assoc()): ?>
      <tr>
        <td><?= $h['id'] ?></td>
        <td><?= htmlspecialchars($h['nombre']) ?></td>
        <td><a class="btn btn-sm btn-danger" href="habilidades.php?eliminar=<?= $h['id'] ?>" onclick="return confirm('¿Eliminar habilidad?');">Eliminar</a></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<div class="alert alert-info">Sugeridas: Trabajo en equipo, Asistencia, Ayuda a otros, Hizo la tarea, Participa, Disciplina.</div>

<?php include 'includes/footer.php'; ?>
