<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "tablero_puntuaciones";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) { die("Error de conexion: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");
?>