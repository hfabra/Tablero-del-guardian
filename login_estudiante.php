<?php
require_once 'includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['estudiante_id'])) {
    header('Location: perfil_estudiante.php');
    exit;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';

    if ($usuario === '' || $clave === '') {
        $errores[] = 'Ingresa tu usuario y clave de acceso.';
    } else {
        $stmt = $conn->prepare('SELECT id, nombre, clave_acceso, password_hash FROM estudiantes WHERE usuario = ? LIMIT 1');
        $stmt->bind_param('s', $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $estudiante = $resultado->fetch_assoc();

        if ($estudiante) {
            $hash = $estudiante['password_hash'] ?? '';
            $clavePlano = $estudiante['clave_acceso'] ?? '';
            $credencialValida = false;

            if ($hash) {
                $credencialValida = password_verify($clave, $hash);
            }

            if (!$credencialValida && $clavePlano !== '') {
                // Compatibilidad con cuentas antiguas sin hash
                $credencialValida = hash_equals($clavePlano, $clave);
            }

            if ($credencialValida) {
                $_SESSION['estudiante_id'] = $estudiante['id'];
                $_SESSION['estudiante_nombre'] = $estudiante['nombre'];
                header('Location: perfil_estudiante.php');
                exit;
            }
        }

        $errores[] = 'Credenciales incorrectas. Verifica tus datos con tu docente.';
    }
}

include 'includes/header_estudiante.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-5">
    <div class="auth-card card border-0 shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <div class="text-center mb-4">
          <div class="auth-icon mb-3 mx-auto">
            <i class="bi bi-person-lines-fill"></i>
          </div>
          <h2 class="fw-semibold mb-1">Acceso de estudiantes</h2>
          <p class="text-muted mb-0">Utiliza los datos entregados por tu docente.</p>
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
            <label for="usuario" class="form-label">Usuario</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
              <input type="text" class="form-control" id="usuario" name="usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label for="clave" class="form-label">Clave de acceso</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
              <input type="password" class="form-control" id="clave" name="clave" required>
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-1"></i> Entrar</button>
          </div>
        </form>

        <div class="text-center mt-4">
          <span class="text-muted">¿Docente?</span> <a class="fw-semibold" href="login.php">Accede aquí</a>.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
