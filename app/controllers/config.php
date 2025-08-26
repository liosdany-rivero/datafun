<?php

/**
 * ARCHIVO: config_db.php (o el nombre que corresponda)
 * DESCRIPCIÓN: Configuración de conexión a base de datos para entornos local y producción
 * IMPORTANTE: Este archivo contiene credenciales sensibles - manejar con cuidado
 */

/*
***************************************************************
   CONFIGURACIÓN PARA ENTORNO LOCAL (DESARROLLO)
   Notas para mantenimiento:
   - Estas credenciales son para XAMPP/MAMP/WAMP local
   - La contraseña debe coincidir con tu configuración local
   - La base de datos debe existir en tu servidor local
   - Ideal para desarrollo y testing
*************************************************************** */

$servername = "localhost"; // Servidor local por defecto
$username = "root";        // Usuario root común en entornos locales
$password = "";            // Contraseña vacía por defecto en XAMPP
// Si configuraste contraseña en XAMPP, colócala aquí
$database = "db"; // Nombre de BD local (debe coincidir con tu phpMyAdmin)

// Crear conexión usando MySQLi (orientado a objetos)
$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
  // Registrar error en logs sin mostrar detalles al usuario
  error_log("[" . date('Y-m-d H:i:s') . "] Conexión fallida LOCAL: " . $conn->connect_error);
  // Mensaje genérico para el usuario
  die("Error de conexión con la base de datos. Por favor intente más tarde.");
}

/*

***************************************************************
   CONFIGURACIÓN PARA PRUEBAS (INFINITE FREE)
   Notas para mantenimiento:
   - Esta sección está comentada para trabajar en local
   - Al subir a producción:
     1. Comentar el bloque LOCAL
     2. Descomentar este bloque
     3. Verificar que las credenciales sean las correctas para tu hosting
   - Nunca subir credenciales a repositorios públicos
*************************************************************** *

// Configuración para Infinite Free
$servername = "sql302.infinityfree.com"; // Servidor proporcionado por el hosting
$username = "if0_39447217";             // Tu nombre de usuario en Infinite Free
$password = "datafun1";                 // Contraseña de la base de datos
$database = "if0_39447217_auth_db";     // Nombre exacto de la BD en producción

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  // Registrar error detallado en logs del servidor
  error_log("[" . date('Y-m-d H:i:s') . "] Conexión fallida PRODUCCIÓN: " . $conn->connect_error);
  // Mensaje genérico para el usuario final
  die("Error de conexión con el servidor. Por favor contacte al administrador.");
}

/***************************************************************
   CONFIGURACIÓN PARA PRODUCCIÓN (INFINITE FREE)
   Notas para mantenimiento:
   - Esta sección está comentada para trabajar en local
   - Al subir a producción:
     1. Comentar el bloque LOCAL
     2. Descomentar este bloque
     3. Verificar que las credenciales sean las correctas para tu hosting
   - Nunca subir credenciales a repositorios públicos
 ****************************************************************

// Configuración para Infinite Free
$servername = "sql104.infinityfree.com"; // Servidor proporcionado por el hosting
$username = "if0_39567675";             // Tu nombre de usuario en Infinite Free
$password = "UcQz7mHYcIpPgs";                 // Contraseña de la base de datos
$database = "if0_39567675_auth_db";     // Nombre exacto de la BD en producción

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  // Registrar error detallado en logs del servidor
  error_log("[" . date('Y-m-d H:i:s') . "] Conexión fallida PRODUCCIÓN: " . $conn->connect_error);
  // Mensaje genérico para el usuario final
  die("Error de conexión con el servidor. Por favor contacte al administrador.");
}





/**/
