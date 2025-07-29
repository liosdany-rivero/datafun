<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo permitir a Administrador 
$allowed_roles = ['Administrador'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../views/dashboard.php");
    exit();
}
