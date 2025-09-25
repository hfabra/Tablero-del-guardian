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

header('Location: login.php');
exit;
