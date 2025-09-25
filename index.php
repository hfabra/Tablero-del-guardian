<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: actividades.php");
} else {
    header("Location: login.php");
}
exit;
