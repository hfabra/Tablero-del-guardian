<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_actual = $_SESSION['user_name'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tablero de Puntuaciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="app-body">
<nav class="navbar navbar-expand-lg app-navbar shadow-sm">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand d-flex align-items-center" href="actividades.php">
      <span class="brand-icon d-inline-flex align-items-center justify-content-center me-2">
        <i class="bi bi-trophy-fill"></i>
      </span>
      <span class="fw-semibold">Tablero del Guardián</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Alternar navegación">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if ($usuario_actual): ?>
          <li class="nav-item"><a class="nav-link" href="actividades.php"><i class="bi bi-kanban me-1"></i>Actividades</a></li>
          <li class="nav-item"><a class="nav-link" href="habilidades.php"><i class="bi bi-stars me-1"></i>Habilidades &amp; Insignias</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if ($usuario_actual): ?>
          <li class="nav-item">
            <span class="navbar-text d-flex align-items-center me-lg-3">
              <span class="user-avatar d-inline-flex align-items-center justify-content-center me-2"><i class="bi bi-person-fill"></i></span>
              <span class="fw-medium">Hola, <?= htmlspecialchars($usuario_actual) ?></span>
            </span>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-outline-light rounded-pill px-3" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a></li>
          <li class="nav-item"><a class="btn btn-sm btn-primary ms-lg-2" href="registro.php"><i class="bi bi-pencil-square me-1"></i>Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">
