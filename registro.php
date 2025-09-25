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
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($nombre === '' || $email === '' || $password === '') {
        $errores[] = 'Todos los campos son obligatorios.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }

    if ($password !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errores[] = 'Ya existe una cuenta con ese correo electrónico.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $conn->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
            $stmtInsert->bind_param('sss', $nombre, $email, $hash);
            if ($stmtInsert->execute()) {
                $_SESSION['user_id'] = $stmtInsert->insert_id;
                $_SESSION['user_name'] = $nombre;
                header('Location: actividades.php');
                exit;
            } else {
                $errores[] = 'Ocurrió un error al registrar la cuenta. Intenta nuevamente.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <h3 class="mb-3 text-center">Crear una cuenta</h3>
    <p class="text-muted text-center">Registra a los administradores del tablero para proteger tus datos.</p>

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
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="mb-3">
        <label for="confirmar" class="form-label">Confirmar contraseña</label>
        <input type="password" class="form-control" id="confirmar" name="confirmar" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-success">Registrarme</button>
      </div>
    </form>

    <div class="text-center mt-3">
      <span class="text-muted">¿Ya tienes una cuenta?</span> <a href="login.php">Inicia sesión</a>.
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
