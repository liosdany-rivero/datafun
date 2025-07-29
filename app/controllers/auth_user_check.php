<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Redirige si no hay sesión o el rol no es correcto
if (!isset($_SESSION['username'])) {
    header("Location: ../views/login.php");
    exit();
}
