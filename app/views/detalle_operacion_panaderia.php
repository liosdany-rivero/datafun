<?php
require_once('../controllers/config.php');
include('../templates/header.php');

$numero = isset($_GET['numero']) ? intval($_GET['numero']) : 0;
if (!$numero) {
    echo "<p>⚠️ Operación no especificada.</p>";
    exit();
}

$operacion = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT cp.*, e.nombre as establecimiento_nombre 
     FROM caja_panaderia cp
     JOIN establecimientos e ON cp.desde_para = e.codigo
     WHERE cp.numero_operacion = $numero"
));
?>

<nav class="sub-menu">
    <ul>
        <li><a href="caja_panaderia.php" class="sub-menu-button">← Volver</a></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Detalles de operación #<?= $numero ?></h2>
    <div class="sub-form">
        <table class="table">
            <tr>
                <td><strong>Fecha:</strong></td>
                <td><?= date('d/m/Y', strtotime($operacion['fecha_operacion'])) ?></td>
            </tr>
            <tr>
                <td><strong>Establecimiento:</strong></td>
                <td><?= htmlspecialchars($operacion['establecimiento_nombre']) ?></td>
            </tr>
            <tr>
                <td><strong>Tipo de operación:</strong></td>
                <td><?= $operacion['entrada'] > 0 ? 'Entrada' : 'Salida' ?></td>
            </tr>
            <tr>
                <td><strong><?= $operacion['entrada'] > 0 ? 'Entrada:' : 'Salida:' ?></strong></td>
                <td><?= number_format($operacion['entrada'] > 0 ? $operacion['entrada'] : $operacion['salida'], 2) ?></td>
            </tr>
            <tr>
                <td><strong>Saldo acumulado:</strong></td>
                <td><?= number_format($operacion['saldo'], 2) ?></td>
            </tr>
            <tr>
                <td><strong>Observaciones:</strong></td>
                <td><?= $operacion['observaciones'] ?: '---' ?></td>
            </tr>
        </table>
    </div>
</div>

<?php include('../templates/footer.php'); ?>