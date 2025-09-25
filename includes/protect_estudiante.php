<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['estudiante_id'])) {
    header('Location: login_estudiante.php');
    exit;
}
