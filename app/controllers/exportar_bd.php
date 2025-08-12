<?php
// Verificación de sesión - Debe ser lo primero en el archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Inicia la sesión si no está activa
}

// Incluye el archivo de verificación de autenticación del usuario
require_once('auth_user_check.php');

// Verificación de permisos - Solo administradores pueden acceder
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrador') {
    die("Acceso denegado: solo administradores pueden respaldar la base de datos.");
}

// Incluye la configuración de la base de datos
require_once 'config.php';

// Establece la zona horaria para los registros de fecha/hora
date_default_timezone_set('America/Havana');

// Verificación de conexión a la base de datos
if (!isset($conn) || $conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// ==============================================
// SECCIÓN DE GENERACIÓN DEL RESPALDO SQL
// ==============================================

// Encabezado inicial del archivo SQL con marca de tiempo
$backup = "-- Respaldo generado el " . date('Y-m-d H:i:s') . "\n\n";

// Fuerza la codificación UTF-8 para los caracteres especiales
$conn->query("SET NAMES 'utf8'");

// Obtiene todas las tablas de la base de datos
$resultTables = $conn->query("SHOW TABLES");

// Procesa cada tabla encontrada
while ($row = $resultTables->fetch_row()) {
    $table = $row[0]; // Nombre de la tabla actual

    // ==============================================
    // ESTRUCTURA DE LA TABLA
    // ==============================================

    // Obtiene el SQL de creación de la tabla
    $resultCreate = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $resultCreate->fetch_assoc();

    // Agrega comentario y estructura al backup
    $backup .= "-- Estructura para tabla `$table`\n";
    $backup .= $createRow['Create Table'] . ";\n\n";

    // ==============================================
    // DATOS DE LA TABLA
    // ==============================================

    // Obtiene todos los registros de la tabla
    $resultData = $conn->query("SELECT * FROM `$table`");

    // Procesa cada registro de la tabla
    while ($dataRow = $resultData->fetch_assoc()) {
        // Mapea los nombres de columnas entre backticks
        $columns = array_map(fn($col) => "`$col`", array_keys($dataRow));

        // Mapea los valores escapando caracteres especiales y manejando NULLs
        $values = array_map(function ($val) use ($conn) {
            $val = $conn->real_escape_string($val); // Escapa caracteres especiales
            return isset($val) ? "'$val'" : "NULL";  // Maneja valores NULL
        }, array_values($dataRow));

        // Construye y agrega el INSERT al backup
        $backup .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
    }
    $backup .= "\n"; // Espacio entre tablas
}

// ==============================================
// SECCIÓN DE MANEJO DE ARCHIVOS
// ==============================================

// Genera nombre único para el archivo SQL basado en:
// - Prefijo "respaldo_"
// - Nombre de la base de datos
// - Fecha y hora actual (formato YYYYMMDD_HHMMSS)
$nombre_sql = "respaldo_" . $database . "_" . date("Ymd_His") . ".sql";

// Ruta temporal para guardar el archivo SQL
$ruta_sql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_sql;

// Guarda el contenido SQL en el archivo temporal
file_put_contents($ruta_sql, $backup);

// ==============================================
// SECCIÓN DE COMPRESIÓN ZIP
// ==============================================

// Nombre del archivo ZIP (mismo nombre que SQL pero con extensión .zip)
$nombre_zip = str_replace(".sql", ".zip", $nombre_sql);

// Crea una instancia de ZipArchive
$zip = new ZipArchive();
$ruta_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_zip;

// Intenta crear y abrir el archivo ZIP
if ($zip->open($ruta_zip, ZipArchive::CREATE) === true) {
    // Agrega el archivo SQL al ZIP
    $zip->addFile($ruta_sql, $nombre_sql);
    $zip->close(); // Cierra el archivo ZIP

    // ==============================================
    // SECCIÓN DE DESCARGA
    // ==============================================

    // Configura headers para forzar la descarga
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$nombre_zip\"");

    // Envía el contenido del ZIP al navegador
    readfile($ruta_zip);

    // ==============================================
    // LIMPIEZA DE ARCHIVOS TEMPORALES
    // ==============================================

    // Elimina los archivos temporales (SQL y ZIP)
    unlink($ruta_sql);
    unlink($ruta_zip);

    exit; // Termina la ejecución del script
} else {
    die("No se pudo crear el archivo zip."); // Manejo de error
}
