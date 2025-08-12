<?php

/**
 * ARCHIVO: [Nombre del archivo actual]
 * DESCRIPCIÓN: Control de acceso y gestión de sesiones
 * VERSIÓN: 1.0
 * 
 * NOTAS PARA MANTENIMIENTO:
 * - Este código debe incluirse al inicio de cualquier página que requiera autenticación
 * - Verifica dos aspectos fundamentales:
 *   1. Que exista una sesión activa
 *   2. Que el usuario esté correctamente autenticado
 */

// 1. INICIO DE SESIÓN ========================================================

/*
 * Verifica el estado actual de la sesión PHP
 * PHP_SESSION_NONE indica que no hay sesión activa
 * session_start() inicia una nueva sesión o reanuda una existente
 * 
 * IMPORTANTE: 
 * - Esta función debe llamarse antes de cualquier output HTML
 * - Si la sesión ya está iniciada, no se vuelve a iniciar
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. CONTROL DE ACCESO ======================================================

/*
 * Verifica si existe la variable de sesión 'username'
 * que debería haberse establecido durante el login exitoso
 * 
 * Flujo:
 * - Si NO existe 'username' (usuario no autenticado):
 *   1. Redirige a la página de login
 *   2. Termina la ejecución del script (exit)
 * 
 * NOTAS:
 * - La ruta del login es relativa a la ubicación del archivo actual
 * - El exit() es CRUCIAL para prevenir ejecución de código restringido
 * - Considerar agregar registro (log) de accesos no autorizados
 */
if (!isset($_SESSION['username'])) {
    header("Location: ../system/login.php");
    exit(); // Detiene inmediatamente la ejecución del script
}
