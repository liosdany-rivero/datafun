<?php
require_once('../controllers/config.php');
include('../templates/header.php');

$numero = isset($_GET['numero']) ? intval($_GET['numero']) : 0;
if (!$numero) {
    echo "<p>⚠️ Operación no especificada.</p>";
    exit();
}

$operacion = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM caja_principal WHERE numero_operacion = $numero"));
$entrada   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM entradas_caja_principal WHERE numero_operacion = $numero"));
$salida    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM salidas_caja_principal WHERE numero_operacion = $numero"));

function obtenerNombreEstablecimiento($conn, $codigo)
{
    $res = mysqli_query($conn, "SELECT nombre FROM establecimientos WHERE codigo = $codigo");
    return mysqli_fetch_assoc($res)['nombre'] ?? '---';
}
?>

<nav class="sub-menu">
    <ul>
        <li><a href="caja_principal.php" class="sub-menu-button">← Volver</a></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Detalles de operación #<?= $numero ?></h2>
    <div class="sub-form">
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($operacion['fecha_operacion'])) ?></p>
        <p><strong>Entrada registrada:</strong> <?= number_format($operacion['entrada'], 2) ?></p>
        <p><strong>Salida registrada:</strong> <?= number_format($operacion['salida'], 2) ?></p>
        <p><strong>Saldo acumulado:</strong> <?= number_format($operacion['saldo'], 2) ?></p>
    </div>

    <?php if ($entrada): ?>
        <div class="sub-form">
            <h3>Detalle de entrada</h3>
            <table class="table">
                <tr>
                    <td><strong>Tipo de entrada:</strong></td>
                    <td><?= $entrada['tipo_entrada'] ?></td>
                </tr>
                <tr>
                    <td><strong>Establecimiento:</strong></td>
                    <td><?= obtenerNombreEstablecimiento($conn, $entrada['establecimiento_codigo']) ?></td>
                </tr>
                <?php if ($entrada['fecha_documento']): ?>
                    <tr>
                        <td><strong>Fecha del documento:</strong></td>
                        <td><?= (!empty($entrada['fecha_documento']) && $entrada['fecha_documento'] != '0000-00-00') ? date('d/m/Y', strtotime($entrada['fecha_documento'])) : '---' ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Cantidad estimada:</strong></td>
                    <td><?= number_format($entrada['cantidad'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Cantidad que entró:</strong></td>
                    <td><?= number_format($operacion['entrada'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Observaciones:</strong></td>
                    <td><?= $entrada['observaciones'] ?: '---' ?></td>
                </tr>
            </table>
        </div>
    <?php elseif ($salida): ?>
        <div class="sub-form">
            <h3>Detalle de salida</h3>
            <table class="table">
                <tr>
                    <td><strong>Tipo de salida:</strong></td>
                    <td><?= $salida['tipo_salida'] ?></td>
                </tr>
                <tr>
                    <td><strong>Establecimiento:</strong></td>
                    <td><?= obtenerNombreEstablecimiento($conn, $salida['establecimiento_codigo']) ?></td>
                </tr>
                <tr>
                    <td><strong>Cantidad que salió:</strong></td>
                    <td><?= number_format($operacion['salida'], 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Observaciones:</strong></td>
                    <td><?= $salida['observaciones'] ?: '---' ?></td>
                </tr>
            </table>
        </div>
    <?php else: ?>
        <p>No se encontraron detalles específicos.</p>
    <?php endif; ?>
</div>

<?php include('../templates/footer.php'); ?>