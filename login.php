<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: actividades.php');
    exit;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = 'Por favor ingresa tu correo y contraseña.';
    } else {
        $stmt = $conn->prepare('SELECT id, nombre, password FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $usuario = $resultado->fetch_assoc();

        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre'];
            header('Location: actividades.php');
            exit;
        }

        $errores[] = 'Credenciales incorrectas. Inténtalo nuevamente.';
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="auth-card card border-0 shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <div class="text-center mb-4">
          <div class="auth-icon mb-3 mx-auto">
            <i class="bi bi-shield-lock"></i>
          </div>
          <h2 class="fw-semibold mb-1">Bienvenido de nuevo</h2>
          <p class="text-muted mb-0">Accede para continuar impulsando el progreso de tu grupo.</p>
        </div>

        <?php if ($errores): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errores as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" class="mt-4">
          <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
              <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i> Entrar</button>
          </div>
        </form>

        <div class="text-center mt-4">
          <span class="text-muted">¿Aún no tienes cuenta?</span> <a class="fw-semibold" href="registro.php">Regístrate aquí</a>.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
