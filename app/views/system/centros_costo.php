<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: centros_costo.php
 * DESCRIPCIÓN: Gestión de centros de costos del sistema
 * 
 * CAMBIOS REALIZADOS:
 * - Campo código bloqueado en modo edición
 * - Lógica de actualización cambiada a UPDATE
 * - Checkboxes editables directamente en la tabla
 * - Adición del campo Modulo
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_admin_check.php'); // Verificación de permisos
require_once('../../controllers/config.php');   // Configuración de conexión a BD

// Generar token CSRF para protección contra ataques
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECCIÓN 2: PROCESAMIENTO DE FORMULARIOS

// 2.1 Creación/Actualización de centro de costos - VERSIÓN MODIFICADA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_centro_costo'])) {
    // Validación CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    // Recoger y sanitizar datos del formulario
    $codigo = (int)$_POST['codigo'];
    $nombre = trim($_POST['nombre']);

    // Validar campos obligatorios
    if (empty($codigo)) {
        $_SESSION['error_msg'] = "⚠️ El código es obligatorio";
        header("Location: centros_costo.php");
        exit();
    }

    if (empty($nombre)) {
        $_SESSION['error_msg'] = "⚠️ El nombre es obligatorio";
        header("Location: centros_costo.php");
        exit();
    }

    // Asegurar valores para todos los campos booleanos
    $Establecimiento = isset($_POST['Establecimiento']) ? 1 : 0;
    $E_Caja_Princ = isset($_POST['E_Caja_Princ']) ? 1 : 0;
    $S_Caja_Princ = isset($_POST['S_Caja_Princ']) ? 1 : 0;
    $E_Caja_Panad = isset($_POST['E_Caja_Panad']) ? 1 : 0;
    $S_Caja_Panad = isset($_POST['S_Caja_Panad']) ? 1 : 0;
    $E_Caja_Trinid = isset($_POST['E_Caja_Trinid']) ? 1 : 0;
    $S_Caja_Trinid = isset($_POST['S_Caja_Trinid']) ? 1 : 0;
    $E_Caja_Gallet = isset($_POST['E_Caja_Gallet']) ? 1 : 0;
    $S_Caja_Gallet = isset($_POST['S_Caja_Gallet']) ? 1 : 0;
    $E_Caja_Cochi = isset($_POST['E_Caja_Cochi']) ? 1 : 0;
    $S_Caja_Cochi = isset($_POST['S_Caja_Cochi']) ? 1 : 0;
    $Modulo = isset($_POST['Modulo']) ? 1 : 0;
    $E_Almacen_USD = isset($_POST['E_Almacen_USD']) ? 1 : 0;
    $S_Almacen_USD = isset($_POST['S_Almacen_USD']) ? 1 : 0;
    $Almacen_USD = isset($_POST['Almacen_USD']) ? 1 : 0;

    // Verificar si estamos en modo creación o edición
    $is_edit = isset($_POST['edit_mode']) && $_POST['edit_mode'] === 'true';
    $original_codigo = $is_edit ? (int)$_POST['original_codigo'] : null;

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        if ($is_edit) {
            // MODE EDIT - Usamos UPDATE en lugar de REPLACE
            $action = "actualizado";
            $sql = "UPDATE centros_costo SET 
        nombre = ?, 
        Establecimiento = ?, 
        E_Caja_Princ = ?, 
        S_Caja_Princ = ?, 
        E_Caja_Panad = ?, 
        S_Caja_Panad = ?, 
        E_Caja_Trinid = ?, 
        S_Caja_Trinid = ?, 
        E_Caja_Gallet = ?, 
        S_Caja_Gallet = ?, 
        E_Caja_Cochi = ?, 
        S_Caja_Cochi = ?,
        Modulo = ?,
        E_Almacen_USD = ?,
        S_Almacen_USD = ?,
        Almacen_USD = ?
        WHERE codigo = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "siiiiiiiiiiiiiiii",
                $nombre,
                $Establecimiento,
                $E_Caja_Princ,
                $S_Caja_Princ,
                $E_Caja_Panad,
                $S_Caja_Panad,
                $E_Caja_Trinid,
                $S_Caja_Trinid,
                $E_Caja_Gallet,
                $S_Caja_Gallet,
                $E_Caja_Cochi,
                $S_Caja_Cochi,
                $Modulo,
                $E_Almacen_USD,
                $S_Almacen_USD,
                $Almacen_USD,
                $codigo
            );
        } else {
            // MODE CREATE - Verificar duplicados
            $action = "creado";

            $check_sql = "SELECT 1 FROM centros_costo WHERE codigo = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $codigo);
            $check_stmt->execute();

            if ($check_stmt->fetch()) {
                throw new Exception("El código $codigo ya está en uso por otro centro de costo");
            }
            $check_stmt->close();

            $sql = "INSERT INTO centros_costo 
       (codigo, nombre, Establecimiento, E_Caja_Princ, S_Caja_Princ, E_Caja_Panad, S_Caja_Panad, 
       E_Caja_Trinid, S_Caja_Trinid, E_Caja_Gallet, S_Caja_Gallet, E_Caja_Cochi, S_Caja_Cochi, Modulo,
       E_Almacen_USD, S_Almacen_USD, Almacen_USD) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isiiiiiiiiiiii",
                $codigo,
                $nombre,
                $Establecimiento,
                $E_Caja_Princ,
                $S_Caja_Princ,
                $E_Caja_Panad,
                $S_Caja_Panad,
                $E_Caja_Trinid,
                $S_Caja_Trinid,
                $E_Caja_Gallet,
                $S_Caja_Gallet,
                $E_Caja_Cochi,
                $S_Caja_Cochi,
                $Modulo
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Error al $action centro de costo: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Centro de costo $action correctamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "⚠️ " . $e->getMessage();
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: centros_costo.php");
    exit();
}

// 2.2 Eliminación de centro de costos (permanece igual)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_centro_costo'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }

    $codigo = $_POST['codigo'];

    try {
        // Consulta preparada para eliminación segura
        $sql = "DELETE FROM centros_costo WHERE codigo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $codigo);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "✅ Centro de costo eliminado correctamente.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $stmt->error;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Capturar excepción de clave foránea
        if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
            $_SESSION['error_msg'] = "⚠️ No se puede eliminar el centro de costo porque está siendo utilizado en otros registros.";
        } else {
            $_SESSION['error_msg'] = "⚠️ Error al eliminar: " . $e->getMessage();
        }
    }

    // Regenerar token y redirigir
    unset($_SESSION['csrf_token']);
    ob_clean();
    header("Location: centros_costo.php");
    exit();
}

// SECCIÓN 3: OBTENCIÓN DE DATOS (permanece igual)

// Configuración de paginación
$por_pagina = 15; // Mostrar 15 registros por página
$total_registros = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM centros_costo"))['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

// Consulta paginada
$centros_costo = [];
$sql = "SELECT * FROM centros_costo ORDER BY codigo LIMIT $inicio, $por_pagina";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $centros_costo[] = $row;
}
ob_end_flush();
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO MODIFICADA -->

<!-- Contenedor principal -->
<div class="form-container">
    <h2>Centros de Costo</h2>

    <!-- Tabla de centros de costos con checkboxes editables -->
    <!-- Tabla de centros de costos con emojis (solo lectura) -->
    <table class="table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Establecimiento</th>
                <th>E Caja Princ</th>
                <th>S Caja Princ</th>
                <th>E Caja Panad</th>
                <th>S Caja Panad</th>
                <th>E Caja Trinid</th>
                <th>S Caja Trinid</th>
                <th>E Caja Gallet</th>
                <th>S Caja Gallet</th>
                <th>E Caja Cochi</th>
                <th>S Caja Cochi</th>
                <th>Módulo</th>
                <th>E Alm USD</th>
                <th>S Alm USD</th>
                <th>Alm USD</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($centros_costo as $row): ?>
                <tr>
                    <td data-label="Código"><?= htmlspecialchars($row['codigo']) ?></td>
                    <td data-label="Centro costo"><?= htmlspecialchars($row['nombre']) ?></td>

                    <!-- Campos booleanos como emojis (✅/❌) SOLO LECTURA -->
                    <td data-label="Establecimiento"><?= $row['Establecimiento'] ? '✅' : '❌' ?></td>
                    <td data-label="E Caja Princ"><?= $row['E_Caja_Princ'] ? '✅' : '❌' ?></td>
                    <td data-label="S Caja Princ"><?= $row['S_Caja_Princ'] ? '✅' : '❌' ?></td>
                    <td data-label="E Caja Panad"><?= $row['E_Caja_Panad'] ? '✅' : '❌' ?></td>
                    <td data-label="S Caja Panad"><?= $row['S_Caja_Panad'] ? '✅' : '❌' ?></td>
                    <td data-label="E Caja Trinid"><?= $row['E_Caja_Trinid'] ? '✅' : '❌' ?></td>
                    <td data-label="S Caja Trinid"><?= $row['S_Caja_Trinid'] ? '✅' : '❌' ?></td>
                    <td data-label="E Caja Gallet"><?= $row['E_Caja_Gallet'] ? '✅' : '❌' ?></td>
                    <td data-label="S Caja Gallet"><?= $row['S_Caja_Gallet'] ? '✅' : '❌' ?></td>
                    <td data-label="E Caja Cochi"><?= $row['E_Caja_Cochi'] ? '✅' : '❌' ?></td>
                    <td data-label="S Caja Cochi"><?= $row['S_Caja_Cochi'] ? '✅' : '❌' ?></td>
                    <td data-label="Módulo"><?= $row['Modulo'] ? '✅' : '❌' ?></td>
                    <td data-label="E Alm USD"><?= $row['E_Almacen_USD'] ? '✅' : '❌' ?></td>
                    <td data-label="S Alm USD"><?= $row['S_Almacen_USD'] ? '✅' : '❌' ?></td>
                    <td data-label="Alm USD"><?= $row['Almacen_USD'] ? '✅' : '❌' ?></td>

                    <td data-label>
                        <div class="table-action-buttons">
                            <button onclick="showEditForm(
    '<?= $row['codigo'] ?>', 
    '<?= htmlspecialchars($row['nombre']) ?>',
    '<?= $row['Establecimiento'] ?>',
    '<?= $row['E_Caja_Princ'] ?>',
    '<?= $row['S_Caja_Princ'] ?>',
    '<?= $row['E_Caja_Panad'] ?>',
    '<?= $row['S_Caja_Panad'] ?>',
    '<?= $row['E_Caja_Trinid'] ?>',
    '<?= $row['S_Caja_Trinid'] ?>',
    '<?= $row['E_Caja_Gallet'] ?>',
    '<?= $row['S_Caja_Gallet'] ?>',
    '<?= $row['E_Caja_Cochi'] ?>',
    '<?= $row['S_Caja_Cochi'] ?>',
    '<?= $row['Modulo'] ?>',
    '<?= $row['E_Almacen_USD'] ?>',
    '<?= $row['S_Almacen_USD'] ?>',
    '<?= $row['Almacen_USD'] ?>'
)">Editar</button>
                            <button onclick="showDeleteForm('<?= $row['codigo'] ?>', '<?= htmlspecialchars($row['nombre']) ?>')">Eliminar</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
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

    <!-- Formulario Crear/Editar -->
    <div id="centroCostoFormContainer" class="sub-form" style="display: none;">
        <h3 id="formTitle">Crear/Editar Centro de Costo</h3>
        <form method="POST" action="centros_costo.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="edit_mode" name="edit_mode" value="false">
            <input type="hidden" id="original_codigo" name="original_codigo" value="">

            <label for="codigo">Código:</label>
            <input type="number" id="codigo" name="codigo" required <?= isset($_POST['edit_mode']) && $_POST['edit_mode'] === 'true' ? 'readonly' : '' ?> />

            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" maxlength="25" required />

            <label class="checkbox-container">
                <input type="checkbox" id="Establecimiento" name="Establecimiento" value="1" />
                Es Establecimiento
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Caja_Princ" name="E_Caja_Princ" value="1" />
                Entrada Caja Principal
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Caja_Princ" name="S_Caja_Princ" value="1" />
                Salida Caja Principal
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Caja_Panad" name="E_Caja_Panad" value="1" />
                Entrada Caja Panadería
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Caja_Panad" name="S_Caja_Panad" value="1" />
                Salida Caja Panadería
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Caja_Trinid" name="E_Caja_Trinid" value="1" />
                Entrada Caja Trinidad
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Caja_Trinid" name="S_Caja_Trinid" value="1" />
                Salida Caja Trinidad
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Caja_Gallet" name="E_Caja_Gallet" value="1" />
                Entrada Caja Galletera
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Caja_Gallet" name="S_Caja_Gallet" value="1" />
                Salida Caja Galletera
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Caja_Cochi" name="E_Caja_Cochi" value="1" />
                Entrada Caja Cochiquera
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Caja_Cochi" name="S_Caja_Cochi" value="1" />
                Salida Caja Cochiquera
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="Modulo" name="Modulo" value="1" />
                Módulo
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="E_Almacen_USD" name="E_Almacen_USD" value="1" />
                Entrada Almacén USD
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="S_Almacen_USD" name="S_Almacen_USD" value="1" />
                Salida Almacén USD
            </label>

            <label class="checkbox-container">
                <input type="checkbox" id="Almacen_USD" name="Almacen_USD" value="1" />
                Almacén USD
            </label>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="save_centro_costo" class="btn-primary">Guardar</button>
                <button type="button" onclick="hideForms()" class="btn-primary">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Formulario Eliminar -->
    <div id="deleteFormContainer" class="sub-form" style="display: none;">
        <h3>¿Eliminar centro de costo <span id="deleteNombreDisplay"></span>?</h3>
        <p>Esta acción no se puede deshacer.</p>
        <form method="POST" action="centros_costo.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="codigo" id="delete_codigo">
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="delete_centro_costo" class="btn-danger">Confirmar Eliminación</button>
                <button type="button" onclick="hideForms()" class="btn-danger">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<BR>
<BR>
<BR>

<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="showCreateForm()" class="nav-button">+ Nuevo Centro de Costo</button></li>
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
        document.getElementById('formTitle').textContent = 'Crear Centro de Costo';
        document.getElementById('edit_mode').value = 'false';
        document.getElementById('original_codigo').value = '';
        resetFormFields();
        document.getElementById('centroCostoFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de edición con datos existentes
     * @param {number} codigo - ID del centro de costo
     * @param {string} nombre - Nombre del centro
     * @param {boolean} Establecimiento - Estado checkbox
     * @param {boolean} E_Caja_Princ - Estado checkbox
     * @param {boolean} S_Caja_Princ - Estado checkbox
     * @param {boolean} E_Caja_Panad - Estado checkbox
     * @param {boolean} S_Caja_Panad - Estado checkbox
     * @param {boolean} E_Caja_Trinid - Estado checkbox
     * @param {boolean} S_Caja_Trinid - Estado checkbox
     * @param {boolean} E_Caja_Gallet - Estado checkbox
     * @param {boolean} S_Caja_Gallet - Estado checkbox
     * @param {boolean} E_Caja_Cochi - Estado checkbox
     * @param {boolean} S_Caja_Cochi - Estado checkbox
     * @param {boolean} Modulo - Estado checkbox
     */
    function showEditForm(codigo, nombre, Establecimiento, E_Caja_Princ, S_Caja_Princ, E_Caja_Panad, S_Caja_Panad,
        E_Caja_Trinid, S_Caja_Trinid, E_Caja_Gallet, S_Caja_Gallet, E_Caja_Cochi, S_Caja_Cochi, Modulo,
        E_Almacen_USD, S_Almacen_USD, Almacen_USD) {
        hideForms();
        document.getElementById('formTitle').textContent = 'Editar Centro de Costo';
        document.getElementById('edit_mode').value = 'true';
        document.getElementById('original_codigo').value = codigo;
        document.getElementById('codigo').value = codigo;
        document.getElementById('nombre').value = nombre;
        document.getElementById('Establecimiento').checked = Establecimiento == '1';
        document.getElementById('E_Caja_Princ').checked = E_Caja_Princ == '1';
        document.getElementById('S_Caja_Princ').checked = S_Caja_Princ == '1';
        document.getElementById('E_Caja_Panad').checked = E_Caja_Panad == '1';
        document.getElementById('S_Caja_Panad').checked = S_Caja_Panad == '1';
        document.getElementById('E_Caja_Trinid').checked = E_Caja_Trinid == '1';
        document.getElementById('S_Caja_Trinid').checked = S_Caja_Trinid == '1';
        document.getElementById('E_Caja_Gallet').checked = E_Caja_Gallet == '1';
        document.getElementById('S_Caja_Gallet').checked = S_Caja_Gallet == '1';
        document.getElementById('E_Caja_Cochi').checked = E_Caja_Cochi == '1';
        document.getElementById('S_Caja_Cochi').checked = S_Caja_Cochi == '1';
        document.getElementById('Modulo').checked = Modulo == '1';
        document.getElementById('E_Almacen_USD').checked = E_Almacen_USD == '1';
        document.getElementById('S_Almacen_USD').checked = S_Almacen_USD == '1';
        document.getElementById('Almacen_USD').checked = Almacen_USD == '1';
        document.getElementById('centroCostoFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Muestra formulario de eliminación con confirmación
     * @param {number} codigo - ID del centro
     * @param {string} nombre - Nombre a mostrar en confirmación
     */
    function showDeleteForm(codigo, nombre) {
        hideForms();
        document.getElementById('delete_codigo').value = codigo;
        document.getElementById('deleteNombreDisplay').textContent = nombre;
        document.getElementById('deleteFormContainer').style.display = 'block';
        scrollToBottom();
    }

    /**
     * Reinicia campos del formulario a valores por defecto
     */
    function resetFormFields() {
        const form = document.getElementById('centroCostoFormContainer').querySelector('form');
        if (form) form.reset();
    }

    /**
     * Oculta todos los formularios
     */
    function hideForms() {
        document.getElementById('centroCostoFormContainer').style.display = 'none';
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