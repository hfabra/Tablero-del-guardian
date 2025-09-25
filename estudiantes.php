<?php
require_once 'includes/db.php';
require_once 'includes/protect.php';
include 'includes/header.php';

$mensajeCredenciales = $_SESSION['credenciales_generadas'] ?? null;
unset($_SESSION['credenciales_generadas']);

$errorEstudiantes = null;

function normalizarUsuario(string $texto): string {
    $usuario = strtolower(trim($texto));
    $usuario = preg_replace('/[^a-z0-9]/', '', $usuario);
    if ($usuario === '') {
        $usuario = 'estudiante';
    }
    return substr($usuario, 0, 30);
}

function generarClaveAcceso(int $longitud = 8): string {
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $clave = '';
    for ($i = 0; $i < $longitud; $i++) {
        $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $clave;
}

function generarUsuarioUnico(mysqli $conn, string $textoBase, ?int $excluirId = null): string {
    $base = normalizarUsuario($textoBase);
    $usuario = $base;
    $contador = 1;

    while (true) {
        if ($excluirId) {
            $stmt = $conn->prepare('SELECT id FROM estudiantes WHERE usuario = ? AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $usuario, $excluirId);
        } else {
            $stmt = $conn->prepare('SELECT id FROM estudiantes WHERE usuario = ? LIMIT 1');
            $stmt->bind_param('s', $usuario);
        }
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        if (!$existe) {
            return $usuario;
        }
        $usuario = substr($base, 0, 25) . $contador;
        $contador++;
    }
}

$actividad_id = isset($_GET['actividad_id']) ? (int)$_GET['actividad_id'] : 0;
if ($actividad_id <= 0) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-exclamation-octagon'></i></div><h4 class='fw-semibold mb-2'>Actividad no válida</h4><p class='text-muted mb-0'>Vuelve a <a href='actividades.php'>Actividades</a> y selecciona una opción disponible.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare('SELECT id, nombre FROM actividades WHERE id = ?');
$stmt->bind_param('i', $actividad_id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
if (!$actividad) {
    echo "<div class='card section-card border-0 bg-white text-center p-4'><div class='card-body'><div class='auth-icon mx-auto mb-3'><i class='bi bi-search'></i></div><h4 class='fw-semibold mb-2'>Actividad no encontrada</h4><p class='text-muted mb-0'>Puede que haya sido eliminada. Revisa el listado de actividades disponibles.</p></div></div>";
    include 'includes/footer.php';
    exit;
}

$estudianteEdit = null;
if (isset($_GET['editar'])) {
    $editarId = (int)$_GET['editar'];
    $stmt = $conn->prepare('SELECT e.id, e.nombre, e.avatar, e.usuario, e.clave_acceso FROM estudiantes e INNER JOIN actividad_estudiante ae ON ae.estudiante_id = e.id WHERE e.id = ? AND ae.actividad_id = ?');
    $stmt->bind_param('ii', $editarId, $actividad_id);
    $stmt->execute();
    $estudianteEdit = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && empty($_POST['estudiante_id'])) {
    $nombre = trim($_POST['nombre']);
    $usuarioIngresado = trim($_POST['usuario'] ?? '');
    $claveIngresada = trim($_POST['clave_acceso'] ?? '');
    $avatar = 'default.png';

    if (!empty($_FILES['avatar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $ext = 'png'; }
        $fname = 'avatar_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $dest = 'assets/img/avatars/' . $fname;
        if (is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            @move_uploaded_file($_FILES['avatar']['tmp_name'], $dest);
            if (file_exists($dest)) { $avatar = $fname; }
        }
    }

    if ($nombre !== '') {
        $conn->begin_transaction();
        try {
            $usuarioFinal = generarUsuarioUnico($conn, $usuarioIngresado !== '' ? $usuarioIngresado : $nombre);
            $claveFinal = $claveIngresada !== '' ? $claveIngresada : generarClaveAcceso();
            $hash = password_hash($claveFinal, PASSWORD_DEFAULT);

            $stmt = $conn->prepare('INSERT INTO estudiantes (nombre, avatar, usuario, clave_acceso, password_hash) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $nombre, $avatar, $usuarioFinal, $claveFinal, $hash);
            if (!$stmt->execute()) {
                throw new RuntimeException('No se pudo registrar el estudiante.');
            }
            $nuevoId = $conn->insert_id;
            $stmt->close();

            $stmtRelacion = $conn->prepare('INSERT INTO actividad_estudiante (actividad_id, estudiante_id) VALUES (?, ?)');
            $stmtRelacion->bind_param('ii', $actividad_id, $nuevoId);
            if (!$stmtRelacion->execute()) {
                throw new RuntimeException('No se pudo vincular el estudiante con la actividad.');
            }
            $stmtRelacion->close();

            $conn->commit();

            $_SESSION['credenciales_generadas'] = [
                'tipo' => 'creado',
                'nombre' => $nombre,
                'usuario' => $usuarioFinal,
                'clave' => $claveFinal
            ];

            header('Location: estudiantes.php?actividad_id='.$actividad_id);
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errorEstudiantes = 'No se pudo registrar el estudiante. Intenta nuevamente.';
        }
    } else {
        $errorEstudiantes = 'Ingresa el nombre del estudiante.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['estudiante_id'])) {
    $estudianteId = (int)$_POST['estudiante_id'];
    $nombre = trim($_POST['nombre']);
    $usuarioIngresado = trim($_POST['usuario'] ?? '');
    $claveIngresada = trim($_POST['clave_acceso'] ?? '');
    $actualizado = false;

    if ($nombre !== '') {
        $stmt = $conn->prepare('SELECT e.avatar, e.usuario, e.clave_acceso, e.password_hash FROM estudiantes e INNER JOIN actividad_estudiante ae ON ae.estudiante_id = e.id WHERE e.id = ? AND ae.actividad_id = ?');
        $stmt->bind_param('ii', $estudianteId, $actividad_id);
        $stmt->execute();
        if ($actual = $stmt->get_result()->fetch_assoc()) {
            $avatar = $actual['avatar'];

            if (!empty($_FILES['avatar']['name'])) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $ext = 'png'; }
                $fname = 'avatar_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $dest = 'assets/img/avatars/' . $fname;
                if (is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                    @move_uploaded_file($_FILES['avatar']['tmp_name'], $dest);
                    if (file_exists($dest)) {
                        if ($avatar && $avatar !== 'default.png' && file_exists('assets/img/avatars/'.$avatar)) {
                            @unlink('assets/img/avatars/'.$avatar);
                        }
                        $avatar = $fname;
                    }
                }
            }

            $usuarioFinal = generarUsuarioUnico($conn, $usuarioIngresado !== '' ? $usuarioIngresado : $actual['usuario'], $estudianteId);
            $claveFinal = $claveIngresada !== '' ? $claveIngresada : $actual['clave_acceso'];
            $hash = ($claveIngresada !== '') ? password_hash($claveFinal, PASSWORD_DEFAULT) : ($actual['password_hash'] ?: password_hash($claveFinal, PASSWORD_DEFAULT));

            $stmt = $conn->prepare('UPDATE estudiantes SET nombre = ?, avatar = ?, usuario = ?, clave_acceso = ?, password_hash = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $nombre, $avatar, $usuarioFinal, $claveFinal, $hash, $estudianteId);
            $stmt->execute();

            $_SESSION['credenciales_generadas'] = [
                'tipo' => 'actualizado',
                'nombre' => $nombre,
                'usuario' => $usuarioFinal,
                'clave' => $claveFinal
            ];
            $actualizado = true;
        } else {
            $errorEstudiantes = 'El estudiante ya no está asociado a esta actividad.';
        }
        $stmt->close();
    } else {
        $errorEstudiantes = 'Ingresa el nombre del estudiante.';
    }

    if ($actualizado) {
        header('Location: estudiantes.php?actividad_id='.$actividad_id);
        exit;
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $eliminarAvatar = false;
    $avatarActual = null;

    $conn->begin_transaction();
    try {
        $stmtInfo = $conn->prepare('SELECT e.avatar FROM estudiantes e INNER JOIN actividad_estudiante ae ON ae.estudiante_id = e.id WHERE e.id = ? AND ae.actividad_id = ?');
        $stmtInfo->bind_param('ii', $id, $actividad_id);
        $stmtInfo->execute();
        if ($info = $stmtInfo->get_result()->fetch_assoc()) {
            $avatarActual = $info['avatar'];
            $stmtInfo->close();

            $stmtRelacion = $conn->prepare('DELETE FROM actividad_estudiante WHERE actividad_id = ? AND estudiante_id = ?');
            $stmtRelacion->bind_param('ii', $actividad_id, $id);
            $stmtRelacion->execute();
            $stmtRelacion->close();

            $stmtPuntajes = $conn->prepare('DELETE FROM puntuaciones WHERE actividad_id = ? AND estudiante_id = ?');
            $stmtPuntajes->bind_param('ii', $actividad_id, $id);
            $stmtPuntajes->execute();
            $stmtPuntajes->close();

            $stmtRetro = $conn->prepare('DELETE r FROM retroalimentaciones r INNER JOIN retos rt ON rt.id = r.reto_id WHERE r.estudiante_id = ? AND rt.actividad_id = ?');
            $stmtRetro->bind_param('ii', $id, $actividad_id);
            $stmtRetro->execute();
            $stmtRetro->close();

            $stmtCuenta = $conn->prepare('SELECT COUNT(*) AS total FROM actividad_estudiante WHERE estudiante_id = ?');
            $stmtCuenta->bind_param('i', $id);
            $stmtCuenta->execute();
            $total = (int)($stmtCuenta->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtCuenta->close();

            if ($total === 0) {
                $stmtEliminar = $conn->prepare('DELETE FROM estudiantes WHERE id = ?');
                $stmtEliminar->bind_param('i', $id);
                $stmtEliminar->execute();
                $stmtEliminar->close();
                $eliminarAvatar = true;
            }

            $conn->commit();

            if ($eliminarAvatar && $avatarActual && $avatarActual !== 'default.png') {
                $path = 'assets/img/avatars/'.$avatarActual;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        } else {
            $stmtInfo->close();
            $conn->rollback();
        }
    } catch (Throwable $e) {
        $conn->rollback();
    }

    header('Location: estudiantes.php?actividad_id='.$actividad_id);
    exit;
}

$q = "SELECT e.id, e.nombre, e.avatar, e.usuario, e.clave_acceso, COALESCE(SUM(p.puntaje),0) AS total "
   . "FROM actividad_estudiante ae "
   . "INNER JOIN estudiantes e ON e.id = ae.estudiante_id "
   . "LEFT JOIN puntuaciones p ON p.estudiante_id = e.id AND p.actividad_id = ae.actividad_id "
   . "WHERE ae.actividad_id = ? "
   . "GROUP BY e.id, e.nombre, e.avatar, e.usuario, e.clave_acceso "
   . "ORDER BY e.nombre ASC";
$stmt = $conn->prepare($q);
$stmt->bind_param('i', $actividad_id);
$stmt->execute();
$estudiantes = $stmt->get_result();

$usuarioPorDefecto = $estudianteEdit['usuario'] ?? ($_POST['usuario'] ?? generarUsuarioUnico($conn, $actividad['nombre'] ?? 'estudiante'));
$clavePorDefecto = $estudianteEdit['clave_acceso'] ?? ($_POST['clave_acceso'] ?? generarClaveAcceso());
?>
<section class="page-header card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title mb-1"><i class="bi bi-people-fill"></i> Estudiantes</h1>
      <p class="page-subtitle mb-0">Actividad: <span class="fw-semibold text-dark"><?= htmlspecialchars($actividad['nombre']) ?></span>. Gestiona participantes, accesos y sus avatares.</p>
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

<?php if ($mensajeCredenciales): ?>
  <div class="alert alert-success shadow-sm">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-key-fill me-2"></i>Credenciales <?= $mensajeCredenciales['tipo'] === 'creado' ? 'generadas' : 'actualizadas' ?> correctamente</h5>
        <p class="mb-0">Comparte estos datos con <strong><?= htmlspecialchars($mensajeCredenciales['nombre']) ?></strong> para que pueda iniciar sesión como estudiante.</p>
      </div>
      <div class="d-flex flex-column flex-sm-row gap-2">
        <span class="credential-chip"><strong>Usuario:</strong> <?= htmlspecialchars($mensajeCredenciales['usuario']) ?></span>
        <span class="credential-chip"><strong>Clave:</strong> <?= htmlspecialchars($mensajeCredenciales['clave']) ?></span>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($errorEstudiantes): ?>
  <div class="alert alert-danger shadow-sm">
    <i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($errorEstudiantes) ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card section-card mb-4">
  <input type="hidden" name="estudiante_id" value="<?= $estudianteEdit['id'] ?? '' ?>">
  <div class="card-body">
    <div class="row g-4 align-items-center">
      <div class="col-md-5">
        <label for="nombre" class="form-label fw-semibold">Nombre del estudiante</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Sofía González" value="<?= htmlspecialchars($estudianteEdit['nombre'] ?? ($_POST['nombre'] ?? '')) ?>" required>
        </div>
      </div>
      <div class="col-md-5">
        <label for="avatar" class="form-label fw-semibold">Avatar (opcional)</label>
        <?php if ($estudianteEdit && $estudianteEdit['avatar'] && $estudianteEdit['avatar'] !== 'default.png'): ?>
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
    <div class="row g-4 align-items-center mt-1">
      <div class="col-md-4">
        <label for="usuario" class="form-label fw-semibold">Usuario de acceso</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-at"></i></span>
          <input type="text" id="usuario" name="usuario" class="form-control" value="<?= htmlspecialchars($usuarioPorDefecto) ?>" required>
        </div>
        <div class="form-text">Puedes personalizarlo; se garantiza que no se repita.</div>
      </div>
      <div class="col-md-4">
        <label for="clave_acceso" class="form-label fw-semibold">Clave de acceso</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-key"></i></span>
          <input type="text" id="clave_acceso" name="clave_acceso" class="form-control" value="<?= htmlspecialchars($clavePorDefecto) ?>" <?= $estudianteEdit ? '' : 'required' ?>>
        </div>
        <div class="form-text">Entrega esta clave al estudiante. Puedes cambiarla cuando lo necesites.</div>
      </div>
      <div class="col-md-4">
        <div class="alert alert-secondary mb-0">
          <div class="d-flex gap-2">
            <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
            <div>
              <strong>Zona estudiantes</strong>
              <p class="mb-0">Pueden ingresar en <a href="login_estudiante.php" class="alert-link">login_estudiante.php</a> para revisar retos y enviar evidencias.</p>
            </div>
          </div>
        </div>
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
          <th class="text-uppercase">Acceso</th>
          <th class="text-uppercase">Puntos</th>
          <th class="text-end text-uppercase">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($e = $estudiantes->fetch_assoc()): ?>
          <tr>
            <td>
              <img src="assets/img/avatars/<?= htmlspecialchars($e['avatar']) ?>" class="avatar-xl" alt="avatar de <?= htmlspecialchars($e['nombre']) ?>">
            </td>
            <td class="fw-semibold text-dark"><?= htmlspecialchars($e['nombre']) ?></td>
            <td>
              <div class="d-flex flex-column gap-1 small">
                <span class="credential-chip"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($e['usuario']) ?></span>
                <span class="credential-chip"><i class="bi bi-key"></i> <?= htmlspecialchars($e['clave_acceso']) ?></span>
              </div>
            </td>
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
