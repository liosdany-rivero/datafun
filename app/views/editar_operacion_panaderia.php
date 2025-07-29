<?php
require_once('../controllers/config.php');
include('../templates/header.php');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$numero = isset($_GET['numero']) ? intval($_GET['numero']) : 0;
if (!$numero) {
    echo "<p>⚠️ Operación no especificada.</p>";
    exit();
}

// Verificar permisos
$user_id = $_SESSION['user_id'] ?? 0;
$query = "SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = 728";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$permiso = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

if ($permiso !== 'escritura') {
    $_SESSION['error_msg'] = "⚠️ No tienes permisos de escritura en la Panadería para realizar esta acción";
    header("Location: caja_panaderia.php");
    exit();
}

// Obtener datos de la operación
$operacion = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT cp.*, e.nombre as establecimiento_nombre 
     FROM caja_panaderia cp
     JOIN establecimientos e ON cp.desde_para = e.codigo
     WHERE cp.numero_operacion = $numero"
));

if (!$operacion) {
    echo "<p>⚠️ Operación no encontrada.</p>";
    exit();
}

// Verificar si es una operación automática (no editable)
if ($operacion['tramitado'] == 1) {
    $_SESSION['error_msg'] = "⚠️ No puedes editar una operación automática de transferencia";
    header("Location: caja_panaderia.php");
    exit();
}

// Obtener establecimientos según el tipo de operación
$tipo = $operacion['entrada'] > 0 ? 'entrada' : 'salida';
$establecimientos = mysqli_query(
    $conn,
    "SELECT codigo, nombre FROM establecimientos 
     WHERE " . ($tipo === 'entrada' ? "entrada_caja_panaderia = 1" : "salida_caja_panaderia = 1")
);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_operacion'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    try {
        $desde_para = intval($_POST['desde_para']);
        $monto = floatval($_POST['monto']);
        $observaciones = $_POST['observaciones'];

        // Validación
        if ($monto <= 0) {
            throw new Exception("El monto debe ser un número positivo");
        }

        $conn->begin_transaction();

        // Actualizar registro
        $entrada = $tipo === 'entrada' ? $monto : 0;
        $salida = $tipo === 'salida' ? $monto : 0;

        $stmt = $conn->prepare("UPDATE caja_panaderia 
                              SET desde_para = ?, entrada = ?, salida = ?, observaciones = ?
                              WHERE numero_operacion = ?");
        $stmt->bind_param("iddsi", $desde_para, $entrada, $salida, $observaciones, $numero);
        $stmt->execute();
        $stmt->close();

        // Recalcular saldos
        $res = mysqli_query($conn, "SELECT * FROM caja_panaderia WHERE numero_operacion >= $numero ORDER BY numero_operacion ASC");
        $saldo = $numero > 1 ? mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT saldo FROM caja_panaderia WHERE numero_operacion = " . ($numero - 1)
        ))['saldo'] : 0;

        while ($row = mysqli_fetch_assoc($res)) {
            $nuevo = $saldo + floatval($row['entrada']) - floatval($row['salida']);
            mysqli_query($conn, "UPDATE caja_panaderia SET saldo = $nuevo WHERE numero_operacion = " . $row['numero_operacion']);
            $saldo = $nuevo;
        }

        $conn->commit();
        $_SESSION['success_msg'] = "✅ Operación #$numero actualizada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "❌ Error al actualizar: " . $e->getMessage();
    }

    header("Location: caja_panaderia.php");
    exit();
}
?>

<nav class="sub-menu">
    <ul>
        <li><a href="caja_panaderia.php" class="sub-menu-button">← Volver</a></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Editar operación #<?= $numero ?></h2>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <label for="desde_para"><?= $tipo === 'entrada' ? 'Desde:' : 'Para:' ?></label>
        <select name="desde_para" required>
            <?php mysqli_data_seek($establecimientos, 0); ?>
            <?php while ($e = mysqli_fetch_assoc($establecimientos)): ?>
                <option value="<?= $e['codigo'] ?>" <?= $e['codigo'] == $operacion['desde_para'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['nombre']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="monto">Monto:</label>
        <input type="number" step="0.01" name="monto" value="<?= $tipo === 'entrada' ? $operacion['entrada'] : $operacion['salida'] ?>" required>

        <label for="observaciones">Observaciones:</label>
        <textarea name="observaciones" maxlength="255"><?= $operacion['observaciones'] ?></textarea>

        <button type="submit" name="actualizar_operacion">Actualizar operación</button>
    </form>
</div>

<?php include('../templates/footer.php'); ?>