<?php

/**
 * VERIFICACIÓN DE SESIÓN
 * 
 * Inicia la sesión PHP si no está activa.
 * Esto es necesario para acceder a $_SESSION.
 * 
 * Nota de mantenimiento:
 * - session_start() debe llamarse antes de cualquier output
 * - PHP_SESSION_NONE verifica estado sin generar warning
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * CONTROL DE ACCESO POR ROLES
 * 
 * Define qué roles tienen permiso para acceder:
 * - Solo el rol 'Administrador' está autorizado
 * - Redirige al dashboard si el rol no coincide
 * 
 * Variables clave:
 * - $_SESSION['role']: Debe estar definido en el login
 * - $allowed_roles: Array configurable para escalabilidad
 * 
 * Flujo:
 * 1. Verifica si el rol del usuario está en $allowed_roles
 * 2. Si no coincide, redirige y termina ejecución
 * 
 * Mejoras potenciales:
 * - Registrar intentos de acceso no autorizados
 * - Soporte para múltiples roles autorizados
 */
$allowed_roles = ['Administrador']; // Roles permitidos
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: ../views/dashboard.php"); // Redirección segura
    exit(); // Detiene inmediatamente la ejecución
}
