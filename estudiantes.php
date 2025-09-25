<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id<=0) { echo "<div class='alert alert-danger'>Actividad no valida.</div>"; include 'includes/footer.php'; exit; }

// Actividad
$stmt=$conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$actividad=$stmt->get_result()->fetch_assoc();
if(!$actividad){ echo "<div class='alert alert-danger'>Actividad no encontrada.</div>"; include 'includes/footer.php'; exit; }

// Agregar estudiante
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear_est'])){
  $nombre = trim($_POST['nombre']);
  $avatar = 'default.png';
  if(!empty($_FILES['avatar']['name'])){
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $ext = 'png'; }
    $fname = 'avatar_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $dest = 'assets/img/avatars/' . $fname;
    if (is_uploaded_file($_FILES['avatar']['tmp_name'])) {
      @move_uploaded_file($_FILES['avatar']['tmp_name'], $dest);
      if (file_exists($dest)) { $avatar = $fname; }
    }
  }
  if($nombre!==''){
    $stmt = $conn->prepare("INSERT INTO estudiantes (nombre, avatar, actividad_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nombre, $avatar, $actividad_id);
    $stmt->execute();
  }
  header("Location: estudiantes.php?actividad_id=".$actividad_id); exit;
}

// Eliminar
if(isset($_GET['eliminar'])){
  $id=(int)$_GET['eliminar'];
  $resAv = $conn->query("SELECT avatar FROM estudiantes WHERE id=$id AND actividad_id=$actividad_id");
  if ($resAv && $row=$resAv->fetch_assoc()) {
    if ($row['avatar'] && $row['avatar']!=='default.png') {
      $path='assets/img/avatars/'.$row['avatar'];
      if (file_exists($path)) { @unlink($path); }
    }
  }
  $conn->query("DELETE FROM estudiantes WHERE id=$id AND actividad_id=$actividad_id");
  header("Location: estudiantes.php?actividad_id=".$actividad_id); exit;
}

// Listado
$q = "SELECT e.id, e.nombre, e.avatar, COALESCE(SUM(p.puntaje),0) AS total "
   . "FROM estudiantes e "
   . "LEFT JOIN puntuaciones p ON p.estudiante_id=e.id "
   . "WHERE e.actividad_id=? "
   . "GROUP BY e.id, e.nombre, e.avatar "
   . "ORDER BY e.id DESC";
$stmt=$conn->prepare($q);
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$estudiantes=$stmt->get_result();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3>Estudiantes - <?= htmlspecialchars($actividad['nombre']) ?></h3>
  <a href="actividades.php" class="btn btn-outline-secondary">Volver</a>
</div>

<form method="post" enctype="multipart/form-data" class="row g-3 mb-4">
  <input type="hidden" name="crear_est" value="1">
  <div class="col-md-5">
    <input type="text" name="nombre" class="form-control" placeholder="Nombre del estudiante" required>
  </div>
  <div class="col-md-5">
    <input type="file" name="avatar" class="form-control" accept="image/*">
  </div>
  <div class="col-md-2 d-grid">
    <button class="btn btn-primary">Agregar</button>
  </div>
</form>

<table class="table table-striped align-middle">
  <thead><tr><th>Avatar</th><th>Nombre</th><th>Puntos</th><th>Acciones</th></tr></thead>
  <tbody>
    <?php while($e=$estudiantes->fetch_assoc()): ?>
      <tr>
        <td><img src="assets/img/avatars/<?= htmlspecialchars($e['avatar']) ?>" width="48" height="48" class="rounded-circle" alt="avatar"></td>
        <td><?= htmlspecialchars($e['nombre']) ?></td>
        <td><span class="badge bg-success fs-6"><?= $e['total'] ?></span></td>
        <td>
          <a class="btn btn-sm btn-success" href="puntuar_estudiante.php?id=<?= $e['id'] ?>">Puntuar</a>
          <a class="btn btn-sm btn-danger" href="estudiantes.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $e['id'] ?>" onclick="return confirm('¿Eliminar estudiante?');">Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<?php include 'includes/footer.php'; ?>
