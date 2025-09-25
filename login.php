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
  <div class="col-md-6 col-lg-5">
    <h3 class="mb-3 text-center">Iniciar sesión</h3>
    <p class="text-muted text-center">Gestiona tus actividades ingresando con tu cuenta.</p>

    <?php if ($errores): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errores as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="card card-body shadow-sm">
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Entrar</button>
      </div>
    </form>

    <div class="text-center mt-3">
      <span class="text-muted">¿Aún no tienes cuenta?</span> <a href="registro.php">Regístrate aquí</a>.
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
