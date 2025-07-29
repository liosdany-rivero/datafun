<?php
// operaciones_panaderia.php

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include('../templates/header.php');
require_once('../controllers/config.php');

// Validar usuario logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar permisos para la panadería (establecimiento 721)
$user_id = $_SESSION['user_id'];
$query = "SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = 728";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$permiso = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

if (!in_array($permiso, ['lectura', 'escritura'])) {
    echo "⚠️ No tienes acceso a esta página.";
    exit();
}

// Mensajes desde sesión
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_panaderia"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));

// Detectar página actual; si no hay registros, mostrar página 1
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

// Calcular índice de inicio
$inicio = ($pagina_actual - 1) * $por_pagina;

// Cargar los registros paginados (orden descendente) con nombre de establecimiento
$operaciones = [];
if ($total_registros > 0) {
    $query = "SELECT cp.*, e.nombre as establecimiento_nombre 
              FROM caja_panaderia cp
              JOIN establecimientos e ON cp.desde_para = e.codigo
              ORDER BY numero_operacion DESC 
              LIMIT $inicio, $por_pagina";
    $resultados = mysqli_query($conn, $query);
} else {
    $resultados = false; // No hay registros
}

// Detectar solicitud de detalles en PHP
$detalles_operacion = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ver_detalles'])) {
    $numero = intval($_POST['ver_detalles']);

    // Buscar la operación principal
    $res = mysqli_query($conn, "SELECT * FROM caja_panaderia WHERE numero_operacion = $numero");
    $base = mysqli_fetch_assoc($res);

    $detalles_operacion = [
        'numero' => $numero,
        'fecha' => $base['fecha_operacion'],
        'entrada' => $base['entrada'],
        'salida' => $base['salida'],
        'saldo' => $base['saldo'],
        'desde_para' => $base['desde_para'],
        'observaciones' => $base['observaciones'],
        'tramitado' => $base['tramitado']
    ];
}
?>

<div class="form-container">
    <h2>Operaciones de Caja Panadería</h2>

    <?php if (isset($success_msg)): ?>
        <div class="alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert-error"><?= $error_msg ?></div>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Establecimiento</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Saldo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultados && mysqli_num_rows($resultados) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($resultados)): ?>
                    <tr>
                        <td data-label="#"><?= $row['numero_operacion'] ?></td>
                        <td data-label="Fecha"><?= date('d/m/Y', strtotime($row['fecha_operacion'])) ?></td>
                        <td data-label="Establecimiento"><?= htmlspecialchars($row['establecimiento_nombre']) ?></td>
                        <td data-label="Entrada"><?= number_format($row['entrada'], 2) ?></td>
                        <td data-label="Salida"><?= number_format($row['salida'], 2) ?></td>
                        <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                        <td data-label="Acciones" class="table-action-buttons">
                            <a href="detalle_operacion_panaderia.php?numero=<?= $row['numero_operacion'] ?>" class="btn-preview">Detalles</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No hay operaciones registradas</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if ($pagina_actual > 1): ?>
            <a href="?pagina=1">&laquo; Primera</a>
            <a href="?pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
        <?php endif; ?>

        <?php
        // Mostrar solo 5 páginas alrededor de la actual
        $inicio_paginas = max(1, $pagina_actual - 2);
        $fin_paginas = min($total_paginas, $pagina_actual + 2);

        if ($inicio_paginas > 1) echo '<span>...</span>';

        for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;

        if ($fin_paginas < $total_paginas) echo '<span>...</span>';
        ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
            <a href="?pagina=<?= $total_paginas ?>">Última &raquo;</a>
        <?php endif; ?>
    </div>
</div>

<?php include('../templates/footer.php'); ?>