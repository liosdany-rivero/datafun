<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: almacen_canal_tarjetas_estiba_usd.php
 * DESCRIPCIÓN: Gestión de tarjetas de estiba USD del sistema
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_admin_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener el producto seleccionado (si existe)
$producto_id = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Creación de entrada/salida en tarjeta de estiba
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['save_entrada']) || isset($_POST['save_salida']))) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Determinar tipo de movimiento
    $tipo_movimiento = isset($_POST['save_entrada']) ? 'entrada' : 'salida';

    // Recoger y sanitizar datos del formulario
    $producto = (int)$_POST['producto'];
    $fecha = trim($_POST['fecha']);
    $cantidad_fisica = (float)$_POST['cantidad_fisica'];
    $valor_usd = (float)$_POST['valor_usd'];
    $desde_para = (int)$_POST['desde_para'];
    $observaciones = trim($_POST['observaciones']);

    // Validar campos obligatorios
    if (empty($producto)) {
        $_SESSION['error_msg'] = "⚠️ El producto es obligatorio";
        header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto_id");
        exit();
    }

    if (empty($fecha)) {
        $_SESSION['error_msg'] = "⚠️ La fecha es obligatoria";
        header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto_id");
        exit();
    }

    if (empty($cantidad_fisica) || $cantidad_fisica <= 0) {
        $_SESSION['error_msg'] = "⚠️ La cantidad física debe ser mayor a 0";
        header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto_id");
        exit();
    }

    if (empty($valor_usd) || $valor_usd <= 0) {
        $_SESSION['error_msg'] = "⚠️ El valor USD debe ser mayor a 0";
        header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto_id");
        exit();
    }

    if (empty($desde_para)) {
        $_SESSION['error_msg'] = "⚠️ El centro de costo es obligatorio";
        header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto_id");
        exit();
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Obtener el saldo del inventario para este producto
        $sql_saldo = "SELECT saldo_fisico, valor_usd 
                     FROM almacen_canal_inventario_usd 
                     WHERE producto = ?";
        $stmt_saldo = $conn->prepare($sql_saldo);
        $stmt_saldo->bind_param("i", $producto);
        $stmt_saldo->execute();
        $result_saldo = $stmt_saldo->get_result();
        $ultimo_saldo = $result_saldo->fetch_assoc();
        $stmt_saldo->close();

        // Calcular nuevos saldos
        if ($tipo_movimiento === 'entrada') {
            $nuevo_saldo_fisico = ($ultimo_saldo['saldo_fisico'] ?? 0) + $cantidad_fisica;
            $nuevo_saldo_usd = ($ultimo_saldo['valor_usd'] ?? 0) + $valor_usd;
        } else {
            $nuevo_saldo_fisico = ($ultimo_saldo['saldo_fisico'] ?? 0) - $cantidad_fisica;
            $nuevo_saldo_usd = ($ultimo_saldo['valor_usd'] ?? 0) - $valor_usd;

            // Validar que no haya saldos negativos
            if ($nuevo_saldo_fisico < 0) {
                throw new Exception("No hay suficiente stock físico disponible");
            }

            if ($nuevo_saldo_usd < 0) {
                throw new Exception("No hay suficiente valor USD disponible");
            }
        }

        // Insertar en tarjeta de estiba
        $sql = "INSERT INTO almacen_canal_tarjetas_estiba_usd 
               (producto, fecha, tipo_movimiento, cantidad_fisica, valor_usd, saldo_fisico, saldo_usd, desde_para, observaciones) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issdddiis",
            $producto,
            $fecha,
            $tipo_movimiento,
            $cantidad_fisica,
            $valor_usd,
            $nuevo_saldo_fisico,
            $nuevo_saldo_usd,
            $desde_para,
            $observaciones
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al registrar movimiento: " . $stmt->error);
        }

        // Actualizar inventario
        $sql_inv = "INSERT INTO almacen_canal_inventario_usd 
                   (producto, saldo_fisico, valor_usd, fecha_operacion) 
                   VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE 
                   saldo_fisico = VALUES(saldo_fisico), 
                   valor_usd = VALUES(valor_usd), 
                   fecha_operacion = VALUES(fecha_operacion)";

        $stmt_inv = $conn->prepare($sql_inv);
        $stmt_inv->bind_param(
            "idds",
            $producto,
            $nuevo_saldo_fisico,
            $nuevo_saldo_usd,
            $fecha
        );

        if (!$stmt_inv->execute()) {
            throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
        }

        $stmt->close();
        $stmt_inv->close();
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Movimiento de $tipo_movimiento registrado correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto");
    exit();
}

// 2.2 Cerrar tarjeta de estiba (actualizar inventario con el último saldo)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cerrar_tarjeta'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $producto = (int)$_POST['producto'];

    try {
        // Obtener el último movimiento de la tarjeta de estiba
        $sql_ultimo = "SELECT saldo_fisico, saldo_usd, fecha 
                      FROM almacen_canal_tarjetas_estiba_usd 
                      WHERE producto = ? 
                      ORDER BY fecha DESC, numero_operacion DESC 
                      LIMIT 1";
        $stmt_ultimo = $conn->prepare($sql_ultimo);
        $stmt_ultimo->bind_param("i", $producto);
        $stmt_ultimo->execute();
        $result_ultimo = $stmt_ultimo->get_result();
        $ultimo_movimiento = $result_ultimo->fetch_assoc();
        $stmt_ultimo->close();

        if ($ultimo_movimiento) {
            // Actualizar inventario con los últimos saldos
            $sql_inv = "INSERT INTO almacen_canal_inventario_usd 
                       (producto, saldo_fisico, valor_usd, fecha_operacion) 
                       VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE 
                       saldo_fisico = VALUES(saldo_fisico), 
                       valor_usd = VALUES(valor_usd), 
                       fecha_operacion = VALUES(fecha_operacion)";

            $stmt_inv = $conn->prepare($sql_inv);
            $stmt_inv->bind_param(
                "idds",
                $producto,
                $ultimo_movimiento['saldo_fisico'],
                $ultimo_movimiento['saldo_usd'],
                $ultimo_movimiento['fecha']
            );

            if ($stmt_inv->execute()) {
                $_SESSION['success_msg'] = "✅ Tarjeta de estiba cerrada correctamente. Inventario actualizado.";
            } else {
                throw new Exception("Error al actualizar inventario: " . $stmt_inv->error);
            }
            $stmt_inv->close();
        } else {
            $_SESSION['error_msg'] = "⚠️ No hay movimientos para cerrar la tarjeta de estiba.";
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_canal_tarjetas_estiba_usd.php?producto=$producto");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener información del producto seleccionado
$producto_nombre = "";
$producto_um = "";
if ($producto_id > 0) {
    $sql_producto = "SELECT p.codigo, p.nombre, p.um 
                    FROM productos p 
                    WHERE p.codigo = $producto_id";
    $result_producto = mysqli_query($conn, $sql_producto);
    if ($result_producto && $row = mysqli_fetch_assoc($result_producto)) {
        $producto_nombre = $row['nombre'];
        $producto_um = $row['um'];
    }
}

// Obtener lista de centros de costo para el select
$centros_costo = [];
$sql_centros = "SELECT codigo, nombre FROM centros_costo ORDER BY nombre";
$result_centros = mysqli_query($conn, $sql_centros);
while ($row = mysqli_fetch_assoc($result_centros)) {
    $centros_costo[$row['codigo']] = $row['nombre'];
}

// Obtener saldo actual del inventario
$saldo_actual = ['saldo_fisico' => 0, 'valor_usd' => 0];
if ($producto_id > 0) {
    $sql_saldo = "SELECT saldo_fisico, valor_usd 
                 FROM almacen_canal_inventario_usd 
                 WHERE producto = $producto_id";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    if ($result_saldo && $row = mysqli_fetch_assoc($result_saldo)) {
        $saldo_actual = $row;
    }
}

// Configuración de paginación para movimientos
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = 0;
if ($producto_id > 0) {
    $total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM almacen_canal_tarjetas_estiba_usd WHERE producto = $producto_id"))['total'] ?? 0;
}
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada de movimientos
$movimientos = [];
if ($producto_id > 0) {
    $sql = "SELECT t.*, p.nombre as producto_nombre, c.nombre as centro_nombre 
            FROM almacen_canal_tarjetas_estiba_usd t 
            LEFT JOIN productos p ON t.producto = p.codigo 
            LEFT JOIN centros_costo c ON t.desde_para = c.codigo 
            WHERE t.producto = $producto_id 
            ORDER BY t.fecha DESC, t.numero_operacion DESC 
            LIMIT $inicio, $por_pagina";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $movimientos[] = $row;
    }
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Tarjetas de Estiba USD - <?= $producto_id > 0 ? htmlspecialchars($producto_nombre) : 'Seleccione un Producto' ?></h2>

    <?php if ($producto_id > 0): ?>
        <!-- Mostrar saldo actual -->
        <div class="saldo-info">
            <h3>Saldo Actual</h3>
            <p>Físico: <strong><?= number_format($saldo_actual['saldo_fisico'], 3) ?> <?= htmlspecialchars($producto_um) ?></strong></p>
            <p>Valor USD: <strong>$<?= number_format($saldo_actual['valor_usd'], 2) ?></strong></p>
        </div>

        <!-- Mostrar producto seleccionado (solo lectura) -->
        <div class="product-selector">
            <label for="producto_display">Producto Seleccionado:</label>
            <input type="text" id="producto_display" value="<?= htmlspecialchars($producto_nombre) ?> (<?= htmlspecialchars($producto_um) ?>)" readonly>
        </div>
    <?php else: ?>
        <!-- Si no hay producto seleccionado, mostrar mensaje y botón para volver -->
        <div class="alert alert-info">
            <p>Por favor, seleccione un producto desde la página de inventarios para gestionar sus tarjetas de estiba.</p>
            <button onclick="location.href='almacen_canal_inventario_usd.php'" class="btn-primary">Volver a Inventarios</button>
        </div>
    <?php endif; ?>

    <?php if ($producto_id > 0): ?>
        <!-- Tabla de movimientos -->
        <table class="table">
            <thead>
                <tr>
                    <th>N° Operación</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Cantidad Física</th>
                    <th>Valor USD</th>
                    <th>Saldo Físico</th>
                    <th>Saldo USD</th>
                    <th>Centro Costo</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $row): ?>
                    <tr>
                        <td data-label="N° Operación"><?= htmlspecialchars($row['numero_operacion']) ?></td>
                        <td data-label="Fecha"><?= htmlspecialchars($row['fecha']) ?></td>
                        <td data-label="Tipo"><?= htmlspecialchars($row['tipo_movimiento']) ?></td>
                        <td data-label="Cantidad Física"><?= number_format($row['cantidad_fisica'], 3) ?></td>
                        <td data-label="Valor USD">$<?= number_format($row['valor_usd'], 2) ?></td>
                        <td data-label="Saldo Físico"><?= number_format($row['saldo_fisico'], 3) ?></td>
                        <td data-label="Saldo USD">$<?= number_format($row['saldo_usd'], 2) ?></td>
                        <td data-label="Centro Costo"><?= htmlspecialchars($row['centro_nombre']) ?></td>
                        <td data-label="Observaciones"><?= htmlspecialchars($row['observaciones']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <div class="pagination">
            <?php if ($pagina_actual > 1): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=1">&laquo; Primera</a>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
            <?php endif; ?>

            <?php
            $inicio_paginas = max(1, $pagina_actual - 2);
            $fin_paginas = min($total_paginas, $pagina_actual + 2);

            if ($inicio_paginas > 1) echo '<span>...</span>';

            for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor;

            if ($fin_paginas < $total_paginas) echo '<span>...</span>';
            ?>

            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
                <a href="?producto=<?= $producto_id ?>&pagina=<?= $total_paginas ?>">Última &raquo;</a>
            <?php endif; ?>
        </div>

        <br>

        <!-- Notificaciones flotantes -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div id="floatingNotification" class="floating-notification success">
                <?= $_SESSION['success_msg'] ?>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div id="floatingNotification" class="floating-notification error">
                <?= $_SESSION['error_msg'] ?>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Formulario Entrada -->
        <div id="entradaFormContainer" class="sub-form" style="display: none;">
            <h3>Registrar Entrada</h3>
            <form method="POST" action="almacen_canal_tarjetas_estiba_usd.php?producto=<?= $producto_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">

                <label for="fecha_entrada">Fecha:</label>
                <input type="date" id="fecha_entrada" name="fecha" required />

                <label for="cantidad_fisica_entrada">Cantidad Física (<?= htmlspecialchars($producto_um) ?>):</label>
                <input type="number" id="cantidad_fisica_entrada" name="cantidad_fisica" step="0.001" min="0.001" required />

                <label for="valor_usd_entrada">Valor USD:</label>
                <input type="number" id="valor_usd_entrada" name="valor_usd" step="0.01" min="0.01" required />

                <label for="desde_para_entrada">Desde/Centro Costo:</label>
                <select id="desde_para_entrada" name="desde_para" required>
                    <option value="">Seleccione centro de costo</option>
                    <?php foreach ($centros_costo as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="observaciones_entrada">Observaciones:</label>
                <textarea id="observaciones_entrada" name="observaciones" rows="3"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_entrada" class="btn-primary">Registrar Entrada</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Formulario Salida -->
        <div id="salidaFormContainer" class="sub-form" style="display: none;">
            <h3>Registrar Salida</h3>
            <form method="POST" action="almacen_canal_tarjetas_estiba_usd.php?producto=<?= $producto_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">

                <label for="fecha_salida">Fecha:</label>
                <input type="date" id="fecha_salida" name="fecha" required />

                <label for="cantidad_fisica_salida">Cantidad Física (<?= htmlspecialchars($producto_um) ?>):</label>
                <input type="number" id="cantidad_fisica_salida" name="cantidad_fisica" step="0.001" min="0.001" max="<?= $saldo_actual['saldo_fisico'] ?>" required />

                <label for="valor_usd_salida">Valor USD:</label>
                <input type="number" id="valor_usd_salida" name="valor_usd" step="0.01" min="0.01" max="<?= $saldo_actual['valor_usd'] ?>" required />

                <label for="desde_para_salida">Para/Centro Costo:</label>
                <select id="desde_para_salida" name="desde_para" required>
                    <option value="">Seleccione centro de costo</option>
                    <?php foreach ($centros_costo as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="observaciones_salida">Observaciones:</label>
                <textarea id="observaciones_salida" name="observaciones" rows="3"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_salida" class="btn-primary">Registrar Salida</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Formulario Cerrar Tarjeta -->
        <div id="cerrarFormContainer" class="sub-form" style="display: none;">
            <h3>¿Cerrar Tarjeta de Estiba?</h3>
            <p>Esta acción actualizará el inventario con los saldos actuales.</p>
            <form method="POST" action="almacen_canal_tarjetas_estiba_usd.php?producto=<?= $producto_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" value="<?= $producto_id ?>">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="cerrar_tarjeta" class="btn-primary">Confirmar Cierre</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <?php if ($producto_id > 0): ?>
            <li><button onclick="showEntradaForm()" class="nav-button">+ Nueva Entrada</button></li>
            <li><button onclick="showSalidaForm()" class="nav-button">- Nueva Salida</button></li>
            <li><button onclick="showCerrarForm()" class="nav-button">✓ Cerrar Tarjeta</button></li>
            <li><button onclick="location.href='almacen_canal_inventario_usd.php'" class="nav-button">← Volver a Inventarios</button></li>
        <?php else: ?>
            <li><button onclick="location.href='almacen_canal_inventario_usd.php'" class="nav-button">← Volver a Inventarios</button></li>
        <?php endif; ?>
    </ul>
</div>

<!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
<script>
    /**
     * Muestra formulario de entrada
     */
    function showEntradaForm() {
        hideForms();
        document.getElementById('entradaFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de salida
     */
    function showSalidaForm() {
        hideForms();
        document.getElementById('salidaFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de cierre de tarjeta
     */
    function showCerrarForm() {
        hideForms();
        document.getElementById('cerrarFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Oculta todos los formularios
     */
    function hideForms() {
        document.getElementById('entradaFormContainer').style.display = 'none';
        document.getElementById('salidaFormContainer').style.display = 'none';
        document.getElementById('cerrarFormContainer').style.display = 'none';
    }

    /**
     * Hace scroll al final de la página
     */
    function scrollToBottom() {
        window.scrollTo(0, document.body.scrollHeight);
    }
</script>

<?php include('../../templates/footer.php'); ?>