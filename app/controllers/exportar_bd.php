<?php

/**
 * Proyecto: Datafun
 * Desarrollador: liosdany-rivero (GitHub)
 * Fecha: Noviembre 2025
 */

//================================================================================================
// 1. Configuración y Seguridad
//================================================================================================

// 1.2 Control de secciones
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1.2. Verificación de Autenticación
require_once('auth_admin_check.php');

// 1.3. Control de Permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrador') {
    die("Acceso denegado: solo administradores pueden respaldar la base de datos.");
}

// 1.4. Configuración de Base de Datos
require_once 'config.php';

// 1.5 Configuración Regional
date_default_timezone_set('America/Havana');


//================================================================================================
// 2. Verificación de Conexión a BD
//================================================================================================

if (!isset($conn) || $conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

//================================================================================================
// 3. Generación del Respaldo SQL
//================================================================================================

// 3.1. Encabezado y Configuración
$backup = "-- Respaldo generado el " . date('Y-m-d H:i:s') . "\n\n";
$conn->query("SET NAMES 'utf8'");

// 3.2. Obtención de Tablas
$resultTables = $conn->query("SHOW TABLES");
while ($row = $resultTables->fetch_row()) {
    $table = $row[0]; // Nombre de la tabla actual

    // 3.3. Estructura de Tablas
    $resultCreate = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $resultCreate->fetch_assoc();
    $backup .= "-- Estructura para tabla `$table`\n";
    $backup .= $createRow['Create Table'] . ";\n\n";

    // 3.4 Extracción de Datos
    $resultData = $conn->query("SELECT * FROM `$table`");
    while ($dataRow = $resultData->fetch_assoc()) {

        // 3.5. Construcción de Inserts
        $columns = array_map(fn($col) => "`$col`", array_keys($dataRow));
        $values = array_map(function ($val) use ($conn) {
            $val = $conn->real_escape_string($val);
            return isset($val) ? "'$val'" : "NULL";
        }, array_values($dataRow));
        $backup .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
    }
    $backup .= "\n";
}

//================================================================================================
// 4. Manejo de Archivos Temporales
//================================================================================================

// 4.1. Nomenclatura de Archivos
$nombre_sql = "datefun_respaldo_db_" . $database . "_" . date("Ymd_His") . ".sql";

// 4.2. Ruta Temporal
$ruta_sql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_sql;

// 4.3. Escritura de Archivo
file_put_contents($ruta_sql, $backup);

//================================================================================================
// 5. Sistema de Compresión ZIP
//================================================================================================

// 5.1. Configuración ZIP
$nombre_zip = str_replace(".sql", ".zip", $nombre_sql);
$zip = new ZipArchive();
$ruta_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_zip;

// 5.2. Creación de Archivo ZIP
if ($zip->open($ruta_zip, ZipArchive::CREATE) === true) {
    $zip->addFile($ruta_sql, $nombre_sql);
    $zip->close();

    //================================================================================================
    // 6. Descarga al Cliente
    //================================================================================================

    // 6.1. Headers HTTP
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$nombre_zip\"");

    // E6.2. Envío de Contenido
    readfile($ruta_zip);

    //================================================================================================
    // 7. Limpieza de Recursos
    //================================================================================================

    // 7.1. Eliminación de Temporales
    unlink($ruta_sql);
    unlink($ruta_zip);
    exit;
}
//================================================================================================
// 8. Manejo de Errores
//================================================================================================

// 8.1. Error de Compresión
else {
    die("No se pudo crear el archivo zip."); // Manejo de error
}
