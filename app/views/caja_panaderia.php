<?php
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verificar permisos del usuario para la panadería (código 728)
$user_id = $_SESSION['user_id'];
$query = "SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = 728";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$permiso = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

if (!$permiso) {
    echo "⚠️ No tienes acceso al módulo Caja de Panadería.";
    exit();
}

// Mensajes desde sesión
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Paginación
$por_pagina = 1;
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM caja_panaderia"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : ($total_registros > 0 ? $total_paginas : 1);
$inicio = ($pagina_actual - 1) * $por_pagina;

// Obtener registros paginados
$operaciones = [];
if ($total_registros > 0) {
    $query = "SELECT cp.*, e.nombre as establecimiento_nombre 
              FROM caja_panaderia cp
              JOIN establecimientos e ON cp.desde_para = e.codigo
              ORDER BY numero_operacion ASC 
              LIMIT $inicio, $por_pagina";
    $resultados = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($resultados)) {
        $operaciones[] = $row;
    }
}

// Función para obtener saldo anterior
function obtenerSaldoAnterior($conn)
{
    $res = mysqli_query($conn, "SELECT saldo FROM caja_panaderia ORDER BY numero_operacion DESC LIMIT 1");
    return $res && mysqli_num_rows($res) > 0 ? mysqli_fetch_assoc($res)['saldo'] : 0;
}

// Procesar operación (entrada o salida)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_operacion']) && $permiso === 'escritura') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $conn->begin_transaction();
    try {
        $tipo = $_POST['tipo_operacion']; // entrada o salida
        $fecha = $_POST['fecha_operacion'];
        $desde_para = intval($_POST['desde_para']);
        $monto = floatval($_POST['monto']);
        $observaciones = $_POST['observaciones'];
        $saldo_anterior = obtenerSaldoAnterior($conn);

        // Verificar si es una operación automática (no permitida desde la interfaz)
        if (isset($_POST['tramitado'])) {
            throw new Exception("No se pueden crear operaciones automáticas manualmente");
        }


        if ($tipo === 'entrada') {
            $entrada = $monto;
            $salida = 0;
            $saldo_nuevo = $saldo_anterior + $entrada;
        } else {
            $entrada = 0;
            $salida = $monto;
            $saldo_nuevo = $saldo_anterior - $salida;
        }

        $stmt = $conn->prepare("INSERT INTO caja_panaderia 
                               (fecha_operacion, entrada, salida, saldo, desde_para, observaciones, tramitado) 
                               VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sdddds", $fecha, $entrada, $salida, $saldo_nuevo, $desde_para, $observaciones);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success_msg'] = "✅ Operación registrada correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en operación de caja panadería: " . $e->getMessage());
        $_SESSION['error_msg'] = "❌ Error al registrar la operación: " . $e->getMessage();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: caja_panaderia.php");
    exit();
}

// Procesar eliminación del último registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_ultimo']) && $permiso === 'escritura') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $conn->begin_transaction();
    try {
        // Obtener el último número de operación
        $res = mysqli_query($conn, "SELECT numero_operacion, tramitado FROM caja_panaderia ORDER BY numero_operacion DESC LIMIT 1");
        if (!$res || mysqli_num_rows($res) === 0) {
            throw new Exception("No hay registros para eliminar");
        }

        $ultimo = mysqli_fetch_assoc($res);
        $numero_operacion = $ultimo['numero_operacion'];

        // Verificar si es una operación automática (tramitado = 1)
        if ($ultimo['tramitado'] == 1) {
            throw new Exception("No se puede eliminar una operación automática de transferencia");
        }

        // Eliminar el registro
        mysqli_query($conn, "DELETE FROM caja_panaderia WHERE numero_operacion = $numero_operacion");

        // Recalcular saldos posteriores
        $res = mysqli_query($conn, "SELECT * FROM caja_panaderia WHERE numero_operacion > $numero_operacion ORDER BY numero_operacion ASC");
        $saldo = $numero_operacion > 1 ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT saldo FROM caja_panaderia WHERE numero_operacion = " . ($numero_operacion - 1)))['saldo'] : 0;

        while ($row = mysqli_fetch_assoc($res)) {
            $nuevo = $saldo + floatval($row['entrada']) - floatval($row['salida']);
            mysqli_query($conn, "UPDATE caja_panaderia SET saldo = $nuevo WHERE numero_operacion = " . $row['numero_operacion']);
            $saldo = $nuevo;
        }

        $conn->commit();
        $_SESSION['success_msg'] = "✅ Último registro eliminado correctamente";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "❌ Error al eliminar el registro: " . $e->getMessage();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: caja_panaderia.php");
    exit();
}

// Obtener establecimientos para los formularios
$establecimientos_entrada = mysqli_query($conn, "SELECT codigo, nombre FROM establecimientos WHERE entrada_caja_panaderia = 1");
$establecimientos_salida = mysqli_query($conn, "SELECT codigo, nombre FROM establecimientos WHERE salida_caja_panaderia = 1");
?>

<?php if ($permiso === 'escritura'): ?>
    <nav class="sub-menu">
        <ul>
            <li><button onclick="mostrarFormulario('entrada')">Entrada</button></li>
            <li><button onclick="mostrarFormulario('salida')">Salida</button></li>
            <li><button onclick="mostrarEliminarForm()">Eliminar último</button></li>
        </ul>
    </nav>
<?php endif; ?>

<div class="form-container">
    <h2>Caja de Panadería</h2>

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
            <?php if (!empty($operaciones)): ?>
                <?php foreach ($operaciones as $row): ?>
                    <tr>
                        <td data-label="#"><?= $row['numero_operacion'] ?></td>
                        <td data-label="Fecha"><?= date('d/m/Y', strtotime($row['fecha_operacion'])) ?></td>
                        <td data-label="Establecimiento"><?= htmlspecialchars($row['establecimiento_nombre']) ?></td>
                        <td data-label="Entrada"><?= number_format($row['entrada'], 2) ?></td>
                        <td data-label="Salida"><?= number_format($row['salida'], 2) ?></td>
                        <td data-label="Saldo"><?= number_format($row['saldo'], 2) ?></td>
                        <td data-label="Acciones" class="table-action-buttons">
                            <a href="detalle_operacion_panaderia.php?numero=<?= $row['numero_operacion'] ?>" class="btn-preview">Detalles</a>
                            <?php if ($permiso === 'escritura' && $row['tramitado'] == 0): ?>
                                <a href="editar_operacion_panaderia.php?numero=<?= $row['numero_operacion'] ?>" class="btn-preview">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
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

    <?php if (isset($success_msg)): ?>
        <div class="alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert-error"><?= $error_msg ?></div>
    <?php endif; ?>

    <?php if ($permiso === 'escritura'): ?>
        <div id="formularioOperacion" class="sub-form" style="display: none;">
            <h3 id="tituloFormulario">Registrar operación</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="tipo_operacion" id="tipo_operacion">
                <input type="hidden" name="fecha_operacion" value="<?= date('Y-m-d') ?>">

                <label id="label_desde_para">Desde/Para:</label>
                <select name="desde_para" id="desde_para" required>
                    <option value="" selected disabled>-- Seleccione --</option>
                    <?php
                    mysqli_data_seek($establecimientos_entrada, 0);
                    while ($e = mysqli_fetch_assoc($establecimientos_entrada)): ?>
                        <option value="<?= $e['codigo'] ?>" data-tipo="entrada"><?= htmlspecialchars($e['nombre']) ?></option>
                    <?php endwhile;
                    mysqli_data_seek($establecimientos_salida, 0);
                    while ($e = mysqli_fetch_assoc($establecimientos_salida)): ?>
                        <option value="<?= $e['codigo'] ?>" data-tipo="salida"><?= htmlspecialchars($e['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>

                <label for="monto">Monto:</label>
                <input type="number" step="0.01" name="monto" required>

                <label for="observaciones">Observaciones:</label>
                <textarea name="observaciones" maxlength="255"></textarea>

                <button type="submit" name="registrar_operacion">Guardar operación</button>
                <button type="button" onclick="ocultarFormulario()">Cancelar</button>
            </form>
        </div>

        <div id="eliminarFormContainer" class="sub-form" style="display: none;">
            <h3>¿Eliminar último registro de caja?</h3>
            <p>Esta acción no se puede deshacer.</p>
            <form method="POST" action="caja_panaderia.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="eliminar_ultimo" class="delete-btn">Confirmar Eliminación</button>
                <button type="button" onclick="ocultarEliminarForm()">Cancelar</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function mostrarFormulario(tipo) {
        const formContainer = document.getElementById('formularioOperacion');
        const tituloForm = document.getElementById('tituloFormulario');
        const labelDesdePara = document.getElementById('label_desde_para');
        const desdeParaSelect = document.getElementById('desde_para');

        // Configurar el formulario según el tipo
        document.getElementById('tipo_operacion').value = tipo;
        tituloForm.innerText = tipo === 'entrada' ? 'Registrar entrada' : 'Registrar salida';
        labelDesdePara.innerText = tipo === 'entrada' ? 'Desde:' : 'Para:';

        // Mostrar solo las opciones relevantes
        const options = desdeParaSelect.querySelectorAll('option');
        options.forEach(option => {
            if (option.value === "") {
                option.style.display = 'block';
            } else {
                const optionTipo = option.getAttribute('data-tipo');
                option.style.display = (optionTipo === tipo) ? 'block' : 'none';
            }
        });

        // Seleccionar la opción "-- Seleccione --" por defecto
        desdeParaSelect.value = "";

        // Mostrar el formulario
        formContainer.style.display = 'block';
        document.getElementById('eliminarFormContainer').style.display = 'none';

        // Desplazar a la vista
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    }

    function ocultarFormulario() {
        document.getElementById('formularioOperacion').style.display = 'none';
        document.getElementById('eliminarFormContainer').style.display = 'none';
    }

    function mostrarEliminarForm() {
        ocultarFormulario();
        document.getElementById('eliminarFormContainer').style.display = 'block';
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    }

    function ocultarEliminarForm() {
        document.getElementById('eliminarFormContainer').style.display = 'none';
    }
</script>

<?php include('../templates/footer.php'); ?>