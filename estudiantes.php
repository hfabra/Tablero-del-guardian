<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id<=0) {
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-exclamation-octagon'></i></div><h4 class='fw-semibold mb-2'>Actividad no válida</h4><p class='text-muted mb-0'>Vuelve a <a href='actividades.php'>Actividades</a> y selecciona una opción disponible.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Actividad
$stmt=$conn->prepare("SELECT id, nombre FROM actividades WHERE id=?");
$stmt->bind_param("i",$actividad_id);
$stmt->execute();
$actividad=$stmt->get_result()->fetch_assoc();
if(!$actividad){
  echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Actividad no encontrada</h4><p class='text-muted mb-0'>Puede que haya sido eliminada. Revisa el listado de actividades disponibles.</p></div></div>";
  include 'includes/footer.php';
  exit;
}

// Agregar/editar estudiante
$estudianteEdit = null;
if (isset($_GET['editar'])) {
  $editarId = (int)$_GET['editar'];
  $stmt = $conn->prepare("SELECT id, nombre, avatar FROM estudiantes WHERE id=? AND actividad_id=?");
  $stmt->bind_param("ii", $editarId, $actividad_id);
  $stmt->execute();
  $estudianteEdit = $stmt->get_result()->fetch_assoc();
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nombre']) && empty($_POST['estudiante_id'])){
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

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['estudiante_id'])){
  $estudianteId = (int)$_POST['estudiante_id'];
  $nombre = trim($_POST['nombre']);
  if($nombre!==''){
    $stmt = $conn->prepare("SELECT avatar FROM estudiantes WHERE id=? AND actividad_id=?");
    $stmt->bind_param("ii", $estudianteId, $actividad_id);
    $stmt->execute();
    if ($actual = $stmt->get_result()->fetch_assoc()) {
      $avatar = $actual['avatar'];
      if(!empty($_FILES['avatar']['name'])){
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $ext = 'png'; }
        $fname = 'avatar_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $dest = 'assets/img/avatars/' . $fname;
        if (is_uploaded_file($_FILES['avatar']['tmp_name'])) {
          @move_uploaded_file($_FILES['avatar']['tmp_name'], $dest);
          if (file_exists($dest)) {
            if ($avatar && $avatar!=='default.png' && file_exists('assets/img/avatars/'.$avatar)) {
              @unlink('assets/img/avatars/'.$avatar);
            }
            $avatar = $fname;
          }
        }
      }

      $stmt = $conn->prepare("UPDATE estudiantes SET nombre=?, avatar=? WHERE id=? AND actividad_id=?");
      $stmt->bind_param("ssii", $nombre, $avatar, $estudianteId, $actividad_id);
      $stmt->execute();
    }
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
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-people-fill"></i> Estudiantes</h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($actividad['nombre']) ?></span>. Gestiona participantes y sus avatares.</p>
    </div>
    <div class="list-actions justify-content-lg-end">
      <a href="actividades.php" class="btn btn-outline-primary btn-icon"><i class="bi bi-arrow-left"></i> Volver a actividades</a>
      <a href="puntuar.php?actividad_id=<?= $actividad_id ?>" class="btn btn-success btn-icon"><i class="bi bi-graph-up-arrow"></i> Ver tablero</a>
    </div>
  </div>
</section>

<?php if ($estudianteEdit): ?>
  <div class="alert alert-info shadow-sm">Editando estudiante <strong><?= htmlspecialchars($estudianteEdit['nombre']) ?></strong>. <a href="estudiantes.php?actividad_id=<?= $actividad_id ?>" class="alert-link">Cancelar</a></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card section-card mb-4">
  <input type="hidden" name="estudiante_id" value="<?= $estudianteEdit['id'] ?? '' ?>">
  <div class="card-body">
    <div class="row g-4 align-items-center">
      <div class="col-md-5">
        <label for="nombre" class="form-label fw-semibold">Nombre del estudiante</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Sofía González" value="<?= htmlspecialchars($estudianteEdit['nombre'] ?? '') ?>" required>
        </div>
      </div>
      <div class="col-md-5">
        <label for="avatar" class="form-label fw-semibold">Avatar (opcional)</label>
        <?php if ($estudianteEdit && $estudianteEdit['avatar'] && $estudianteEdit['avatar']!=='default.png'): ?>
          <div class="form-text mb-2">Sube una imagen para reemplazar el avatar actual.</div>
        <?php endif; ?>
        <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary btn-icon btn-lg">
          <i class="bi <?= $estudianteEdit ? 'bi-arrow-repeat' : 'bi-person-plus' ?>"></i>
          <?= $estudianteEdit ? 'Actualizar' : 'Agregar' ?>
        </button>
      </div>
    </div>
  </div>
</form>

<div class="card table-card mb-4">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th class="text-uppercase">Avatar</th>
          <th class="text-uppercase">Nombre</th>
          <th class="text-uppercase">Puntos</th>
          <th class="text-end text-uppercase">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while($e=$estudiantes->fetch_assoc()): ?>
          <tr>
            <td>
              <img src="assets/img/avatars/<?= htmlspecialchars($e['avatar']) ?>" class="avatar-xl" alt="avatar de <?= htmlspecialchars($e['nombre']) ?>">
            </td>
            <td class="fw-semibold text-dark"><?= htmlspecialchars($e['nombre']) ?></td>
            <td><span class="badge bg-success fs-6"><i class="bi bi-stars me-1"></i><?= $e['total'] ?></span></td>
            <td class="text-end">
              <div class="btn-group" role="group">
                <a class="btn btn-sm btn-success btn-icon" href="puntuar_estudiante.php?id=<?= $e['id'] ?>"><i class="bi bi-plus-circle"></i> Puntuar</a>
                <a class="btn btn-sm btn-outline-secondary btn-icon" href="estudiantes.php?actividad_id=<?= $actividad_id ?>&editar=<?= $e['id'] ?>"><i class="bi bi-pencil"></i> Editar</a>
                <a class="btn btn-sm btn-outline-danger btn-icon" href="estudiantes.php?actividad_id=<?= $actividad_id ?>&eliminar=<?= $e['id'] ?>" onclick="return confirm('¿Eliminar estudiante?');"><i class="bi bi-trash"></i> Quitar</a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
