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

<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-stars"></i> Habilidades e insignias</h1>
      <p class="page-subtitle mb-0">Define los comportamientos y logros que se premiarán en cada actividad.</p>
    </div>
    <div class="text-lg-end">
      <span class="badge rounded-pill text-bg-primary-subtle"><i class="bi bi-magic me-1"></i>Personaliza tu tablero</span>
    </div>
  </div>
</section>

<form method="post" class="card section-card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <label for="nombre" class="form-label fw-semibold">Nombre de la habilidad</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-lightbulb"></i></span>
          <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Trabajo en equipo" required>
        </div>
      </div>
      <div class="col-md-4 d-grid">
        <button class="btn btn-primary btn-icon btn-lg">
          <i class="bi bi-plus-circle"></i>
          Agregar habilidad
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
          <th class="text-uppercase">#</th>
          <th class="text-uppercase">Habilidad</th>
          <th class="text-end text-uppercase">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while($h=$res->fetch_assoc()): ?>
          <tr>
            <td><span class="badge text-bg-primary-soft">#<?= $h['id'] ?></span></td>
            <td class="fw-semibold text-dark"><?= htmlspecialchars($h['nombre']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-danger btn-icon" href="habilidades.php?eliminar=<?= $h['id'] ?>" onclick="return confirm('¿Eliminar habilidad?');">
                <i class="bi bi-trash"></i>
                Quitar
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card section-card bg-white">
  <div class="card-body d-flex align-items-start gap-3">
    <div class="auth-icon flex-shrink-0"><i class="bi bi-journal-richtext"></i></div>
    <div>
      <h5 class="fw-semibold mb-1">Inspiración rápida</h5>
      <p class="mb-0 text-muted">Sugeridas: Trabajo en equipo, Asistencia, Ayuda a otros, Hizo la tarea, Participa, Disciplina.</p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
