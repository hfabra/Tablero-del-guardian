<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: actividades.php');
    exit;
}

if (isset($_SESSION['estudiante_id'])) {
    header('Location: perfil_estudiante.php');
    exit;
}

include 'includes/header.php';
?>

<div class="d-flex flex-column align-items-center justify-content-center py-5" style="min-height: 70vh;">
  <div class="text-center mb-5">
    <span class="brand-icon d-inline-flex align-items-center justify-content-center mb-3">
      <i class="bi bi-trophy-fill"></i>
    </span>
    <h1 class="fw-bold mb-3">Bienvenido al Tablero del Guardián</h1>
    <p class="text-muted lead mb-0">Selecciona tu rol para continuar impulsando el progreso de tu comunidad educativa.</p>
  </div>

  <div class="row g-4 justify-content-center w-100" style="max-width: 760px;">
    <div class="col-md-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body text-center p-4">
          <div class="display-5 text-primary mb-3"><i class="bi bi-mortarboard-fill"></i></div>
          <h3 class="fw-semibold">Soy docente</h3>
          <p class="text-muted mb-4">Gestiona actividades, monitorea habilidades y celebra logros con tu grupo.</p>
          <a class="btn btn-primary btn-lg" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión</a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body text-center p-4">
          <div class="display-5 text-success mb-3"><i class="bi bi-person-fill"></i></div>
          <h3 class="fw-semibold">Soy estudiante</h3>
          <p class="text-muted mb-4">Revisa tus retos, consulta tus insignias y mantén tu progreso al día.</p>
          <a class="btn btn-outline-secondary btn-lg" href="login_estudiante.php"><i class="bi bi-door-open me-2"></i>Iniciar sesión</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
