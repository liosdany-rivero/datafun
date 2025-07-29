<?php
// Debe ser lo PRIMERO en el archivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('auth_user_check.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrador') {
    die("Acceso denegado: solo administradores pueden respaldar la base de datos.");
}

require_once 'config.php';
date_default_timezone_set('America/Havana');

// Verificar conexión
if (!isset($conn) || $conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Iniciar respaldo
$backup = "-- Respaldo generado el " . date('Y-m-d H:i:s') . "\n\n";
$conn->query("SET NAMES 'utf8'");

$resultTables = $conn->query("SHOW TABLES");
while ($row = $resultTables->fetch_row()) {
    $table = $row[0];

    // Estructura
    $resultCreate = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $resultCreate->fetch_assoc();
    $backup .= "-- Estructura para tabla `$table`\n";
    $backup .= $createRow['Create Table'] . ";\n\n";

    // Datos
    $resultData = $conn->query("SELECT * FROM `$table`");
    while ($dataRow = $resultData->fetch_assoc()) {
        $columns = array_map(fn($col) => "`$col`", array_keys($dataRow));
        $values = array_map(function ($val) use ($conn) {
            $val = $conn->real_escape_string($val);
            return isset($val) ? "'$val'" : "NULL";
        }, array_values($dataRow));
        $backup .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
    }
    $backup .= "\n";
}

// Guardar .sql temporal
$nombre_sql = "respaldo_" . $database . "_" . date("Ymd_His") . ".sql";
$ruta_sql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_sql;
file_put_contents($ruta_sql, $backup);

// Crear archivo zip
$nombre_zip = str_replace(".sql", ".zip", $nombre_sql);
$zip = new ZipArchive();
$ruta_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_zip;

if ($zip->open($ruta_zip, ZipArchive::CREATE) === true) {
    $zip->addFile($ruta_sql, $nombre_sql);
    $zip->close();

    // Descargar ZIP
    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$nombre_zip\"");
    readfile($ruta_zip);

    // Limpiar archivos temporales
    unlink($ruta_sql);
    unlink($ruta_zip);
    exit;
} else {
    die("No se pudo crear el archivo zip.");
}
