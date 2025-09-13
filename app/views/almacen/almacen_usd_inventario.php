<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: almacen_usd_inventario.php
 * DESCRIPCIÓN: Gestión de inventario USD del sistema
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php');
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Obtener el ID del almacén desde la URL o sesión
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : 0;
if ($almacen_id > 0) {
    $_SESSION['almacen_actual'] = $almacen_id;
} elseif (isset($_SESSION['almacen_actual'])) {
    $almacen_id = (int)$_SESSION['almacen_actual'];
} else {
    // Redirigir al dashboard si no hay almacén seleccionado
    header("Location: almacen_usd_dashboard.php");
    exit();
}

// Validar que el usuario tenga permisos para este almacén
$stmt = $conn->prepare("SELECT permiso FROM permisos WHERE user_id = ? AND centro_costo_codigo = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$permiso = $result->fetch_assoc()['permiso'] ?? '';
$stmt->close();

// Verificar permisos - si no tiene ninguno, redirigir
if (!in_array($permiso, ['leer', 'escribir', 'tramitar'])) {
    $_SESSION['error_msg'] = "No tiene permisos para acceder a este almacén";
    header("Location: almacen_usd_dashboard.php");
    exit();
}

// Obtener nombre del almacén actual
$stmt = $conn->prepare("SELECT nombre FROM centros_costo WHERE codigo = ?");
$stmt->bind_param("i", $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$almacen_nombre = $result->fetch_assoc()['nombre'] ?? 'Almacén USD';
$stmt->close();

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Creación/Actualización de inventario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_inventario'])) {
    // Verificar permiso
    if ($permiso !== 'escribir') {
        $_SESSION['error_msg'] = "No tiene permisos para realizar esta acción";
        header("Location: almacen_usd_inventario.php?almacen_id=$almacen_id");
        exit();
    }
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $producto = (int)$_POST['producto'];
    $saldo_fisico = 0; // Valor por defecto
    $valor_usd = 0; // Valor por defecto
    $fecha_operacion = date('Y-m-d'); // Fecha actual por defecto

    // Validar campos obligatorios
    if (empty($producto)) {
        $_SESSION['error_msg'] = "⚠️ El producto es obligatorio";
        header("Location: almacen_usd_inventario.php");
        exit();
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Verificar si el producto ya existe en el inventario
        $check_sql = "SELECT COUNT(*) as count FROM almacen_usd_inventario WHERE producto = ? AND almacen_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $producto, $almacen_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result(); // Obtener el resultado
        $row = $check_result->fetch_assoc(); // Usar fetch_assoc() en el resultado
        $check_stmt->close();

        if ($row['count'] > 0) {
            // MODE UPDATE - El producto ya existe, actualizamos
            $action = "actualizado";
            $sql = "UPDATE almacen_usd_inventario SET 
                    saldo_fisico = ?,
                    valor_usd = ?,
                    fecha_operacion = ?
                    WHERE producto = ? AND almacen_id = ?"; // MODIFICACIÓN
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ddsii", // MODIFICACIÓN: cambiar a 5 parámetros
                $saldo_fisico,
                $valor_usd,
                $fecha_operacion,
                $producto,
                $almacen_id // MODIFICACIÓN: agregar parámetro
            );
        } else {
            // MODE CREATE - Insertar nuevo registro
            $action = "creado";
            $sql = "INSERT INTO almacen_usd_inventario 
                   (producto, saldo_fisico, valor_usd, fecha_operacion, almacen_id) 
                   VALUES (?, ?, ?, ?, ?)"; // MODIFICACIÓN
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iddsi", // MODIFICACIÓN: cambiar a 5 parámetros
                $producto,
                $saldo_fisico,
                $valor_usd,
                $fecha_operacion,
                $almacen_id // MODIFICACIÓN: agregar parámetro
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Error al $action inventario: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Inventario $action correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_inventario.php?almacen_id=$almacen_id");
    exit();
}

// 2.2 Eliminación de inventario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_inventario'])) {
    // Verificar permiso
    if ($permiso !== 'escribir') {
        $_SESSION['error_msg'] = "No tiene permisos para realizar esta acción";
        header("Location: almacen_usd_inventario.php?almacen_id=$almacen_id");
        exit();
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $producto = $_POST['producto'];

    try {
        // Verificar si el producto tiene tarjetas asociadas
        $check_sql = "SELECT COUNT(*) as count FROM almacen_usd_tarjetas_estiba WHERE producto = ? AND almacen_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $producto, $almacen_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result(); // Obtener el resultado
        $row = $check_result->fetch_assoc(); // Usar fetch_assoc() en el resultado, no en el statement
        $check_stmt->close();

        if ($row['count'] > 0) {
            $_SESSION['error_msg'] = "⚠️ No se puede eliminar el inventario porque tiene tarjetas asociadas.";
        } else {
            // Consulta preparada para eliminación segura
            $sql = "DELETE FROM almacen_usd_inventario WHERE producto = ? AND almacen_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $producto, $almacen_id);

            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "✅ Inventario eliminado correctamente.";
            } else {
                $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $stmt->error;
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        // Capturar excepción de clave foránea
        if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
            $_SESSION['error_msg'] = "⚠️ No se puede eliminar el inventario porque está siendo utilizado en otros registros.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $e->getMessage();
        }
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: almacen_usd_inventario.php?almacen_id=$almacen_id");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS

// Obtener lista de productos para el select (excluyendo los que ya están en inventario)
$productos = [];
// Solo obtener productos si el usuario tiene permiso de escritura
if ($permiso === 'escribir') {
    $sql_productos = "SELECT p.codigo, p.nombre, p.um 
                      FROM productos p 
                      LEFT JOIN almacen_usd_inventario i ON p.codigo = i.producto AND i.almacen_id = $almacen_id
                      WHERE i.producto IS NULL 
                      ORDER BY p.nombre";
    $result_productos = mysqli_query($conn, $sql_productos);
    while ($row = mysqli_fetch_assoc($result_productos)) {
        $productos[$row['codigo']] = ['nombre' => $row['nombre'], 'um' => $row['um']];
    }
}

// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM almacen_usd_inventario WHERE almacen_id = $almacen_id"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada
$inventarios = [];
$sql = "SELECT i.*, p.nombre as producto_nombre, p.um 
        FROM almacen_usd_inventario i 
        LEFT JOIN productos p ON i.producto = p.codigo 
        WHERE i.almacen_id = $almacen_id
        ORDER BY i.fecha_operacion DESC, p.nombre ASC 
        LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $inventarios[] = $row;
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Inventario USD - <?= htmlspecialchars($almacen_nombre) ?></h2>

    <!-- Tabla de inventarios -->
    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Saldo Físico</th>
                <th>Valor USD</th>
                <th>Fecha Operación</th>
                <?php if ($permiso !== 'leer'): ?>
                    <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventarios as $row): ?>
                <tr>
                    <td data-label="Producto"><?= htmlspecialchars($row['producto_nombre']) ?></td>
                    <td data-label="Unidad"><?= htmlspecialchars($row['um']) ?></td>
                    <td data-label="Saldo Físico"><?= number_format($row['saldo_fisico'], 3) ?></td>
                    <td data-label="Valor USD">$<?= number_format($row['valor_usd'], 2) ?></td>
                    <td data-label="Fecha Operación"><?= htmlspecialchars($row['fecha_operacion']) ?></td>

                    <?php if ($permiso !== 'leer'): ?>
                        <td data-label>
                            <div class="table-action-buttons">
                                <?php if ($permiso === 'escribir'): ?>
                                    <button onclick="showDeleteForm('<?= $row['producto'] ?>', '<?= htmlspecialchars($row['producto_nombre']) ?>')">Eliminar</button>
                                <?php endif; ?>
                                <?php if ($permiso === 'tramitar' || $permiso === 'escribir'): ?>
                                    <button onclick="location.href='almacen_usd_tarjetas_estiba.php?producto=<?= $row['producto'] ?>&almacen_id=<?= $almacen_id ?>'">Tarjetas</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <?php if ($pagina_actual > 1): ?>
            <a href="?almacen_id=<?= $almacen_id ?>&pagina=1">&laquo; Primera</a>
            <a href="?almacen_id=<?= $almacen_id ?>&pagina=<?= $pagina_actual - 1 ?>">&lsaquo; Anterior</a>
        <?php endif; ?>

        <?php
        $inicio_paginas = max(1, $pagina_actual - 2);
        $fin_paginas = min($total_paginas, $pagina_actual + 2);

        if ($inicio_paginas > 1) echo '<span>...</span>';

        for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
            <a href="?almacen_id=<?= $almacen_id ?>&pagina=<?= $i ?>" class="<?= $i === $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;

        if ($fin_paginas < $total_paginas) echo '<span>...</span>';
        ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?almacen_id=<?= $almacen_id ?>&pagina=<?= $pagina_actual + 1 ?>">Siguiente &rsaquo;</a>
            <a href="?almacen_id=<?= $almacen_id ?>&pagina=<?= $total_paginas ?>">Última &raquo;</a>
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

    <!-- Formulario Crear -->
    <!-- Formulario Crear - Solo para usuarios con permiso 'escribir' -->
    <?php if ($permiso === 'escribir'): ?>
        <div id="inventarioFormContainer" class="sub-form" style="display: none;">
            <h3>Crear Inventario</h3>
            <form method="POST" action="almacen_usd_inventario.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="almacen_id" value="<?= $almacen_id ?>">

                <label for="producto">Producto:</label>
                <select id="producto" name="producto" required>
                    <option value="">Seleccione un producto</option>
                    <?php foreach ($productos as $codigo => $producto): ?>
                        <option value="<?= $codigo ?>"><?= htmlspecialchars($producto['nombre']) ?> (<?= htmlspecialchars($producto['um']) ?>)</option>
                    <?php endforeach; ?>
                </select>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_inventario" class="btn-primary">Guardar</button>
                    <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Formulario Eliminar -->
    <!-- Formulario Eliminar - Solo para usuarios con permiso 'escribir' -->
    <?php if ($permiso === 'escribir'): ?>
        <div id="deleteFormContainer" class="sub-form" style="display: none;">
            <h3>¿Eliminar inventario del producto <span id="deleteProductoDisplay"></span>?</h3>
            <p>Esta acción no se puede deshacer.</p>
            <form method="POST" action="almacen_usd_inventario.php?almacen_id=<?= $almacen_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="producto" id="delete_producto">
                <input type="hidden" name="almacen_id" value="<?= $almacen_id ?>">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="delete_inventario" class="btn-danger">Confirmar Eliminación</button>
                    <button type="button" onclick="hideForms()" class="btn-danger">Cancelar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <BR>
    <BR>
    <BR>

    <div id="barra-estado">
        <ul class="secondary-nav-menu">
            <li><button onclick="location.href='almacen_usd_dashboard.php'" class="nav-button">← Almacenes</button></li>
            <?php if ($permiso === 'escribir'): ?>
                <li><button onclick="showCreateForm()" class="nav-button">+ Nuevo Inventario</button></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- SECCIÓN 5: JAVASCRIPT PARA INTERACCIÓN -->
    <script>
        /**
         * Muestra formulario de creación
         * - Restablece campos
         * - Muestra el contenedor
         * - Hace scroll al final
         */
        function showCreateForm() {
            hideForms();
            document.getElementById('inventarioFormContainer').style.display = 'block';
            scrollToBottom();
        }

        /**
         * Muestra formulario de eliminación con confirmación
         * @param {number} producto - ID del producto
         * @param {string} producto_nombre - Nombre del producto a mostrar en confirmación
         */
        function showDeleteForm(producto, producto_nombre) {
            hideForms();
            document.getElementById('delete_producto').value = producto;
            document.getElementById('deleteProductoDisplay').textContent = producto_nombre;
            document.getElementById('deleteFormContainer').style.display = 'block';
            scrollToBottom();
        }

        /**
         * Oculta todos los formularios
         */
        function hideForms() {
            document.getElementById('inventarioFormContainer').style.display = 'none';
            document.getElementById('deleteFormContainer').style.display = 'none';
        }

        /**
         * Hace scroll al final de la página
         */
        function scrollToBottom() {
            window.scrollTo(0, document.body.scrollHeight);
        }
    </script>

    <?php include('../../templates/footer.php'); ?>