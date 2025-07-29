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

// Obtener permisos del usuario
$user_id = $_SESSION['user_id'] ?? 0;
function obtenerPermiso($conn, $user_id, $codigo_establecimiento)
{
    $stmt = $conn->prepare("SELECT permiso FROM permisos_establecimientos WHERE user_id = ? AND establecimiento_codigo = ?");
    $stmt->bind_param("ii", $user_id, $codigo_establecimiento);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['permiso'] ?? '';
}
// Verificar permiso de escritura en el establecimiento 800 (Caja principal)
$permiso_caja = obtenerPermiso($conn, $user_id, 800);
if ($permiso_caja !== 'escritura') {
    $_SESSION['error_msg'] = "⚠️ No tienes permisos de escritura en la Caja Principal para realizar esta acción";
    header("Location: caja_principal.php");
    exit();
}

// Función para actualizar transferencia a panadería
function actualizarTransferenciaPanaderia($conn, $numero_operacion, $nuevo_valor)
{
    $conn->begin_transaction();
    try {
        // Verificar si es transferencia a panadería y obtener operación relacionada
        $query = "SELECT r.operacion_panaderia 
                 FROM relacion_cajas r
                 JOIN salidas_caja_principal s ON r.operacion_principal = s.numero_operacion
                 WHERE s.numero_operacion = ? AND s.establecimiento_codigo = 728";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $numero_operacion);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !isset($row['operacion_panaderia'])) {
            $conn->commit();
            return true; // No es transferencia o no tiene operación relacionada
        }

        $operacion_panaderia = $row['operacion_panaderia'];

        // Obtener saldo anterior en panadería
        $stmt = $conn->prepare("SELECT IFNULL(MAX(saldo), 0) as saldo 
                              FROM caja_panaderia 
                              WHERE numero_operacion < ?");
        $stmt->bind_param("i", $operacion_panaderia);
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_anterior = $result->fetch_assoc()['saldo'];
        $stmt->close();

        // Actualizar operación en panadería (siempre es entrada)
        $stmt = $conn->prepare("UPDATE caja_panaderia SET 
                              entrada = ?, 
                              saldo = ? 
                              WHERE numero_operacion = ?");
        $nuevo_saldo = $saldo_anterior + $nuevo_valor;

        $stmt->bind_param("ddi", $nuevo_valor, $nuevo_saldo, $operacion_panaderia);
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar caja panadería: " . $stmt->error);
        }
        $stmt->close();

        // Recalcular saldos posteriores en panadería
        $res = mysqli_query($conn, "SELECT numero_operacion, entrada, salida 
                                  FROM caja_panaderia 
                                  WHERE numero_operacion > $operacion_panaderia 
                                  ORDER BY numero_operacion ASC");
        if (!$res) {
            throw new Exception("Error al obtener operaciones para recalcular: " . mysqli_error($conn));
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $nuevo_saldo = $nuevo_saldo + floatval($row['entrada']) - floatval($row['salida']);
            $update = mysqli_query($conn, "UPDATE caja_panaderia SET saldo = $nuevo_saldo 
                                         WHERE numero_operacion = " . $row['numero_operacion']);
            if (!$update) {
                throw new Exception("Error al actualizar saldo posterior: " . mysqli_error($conn));
            }
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en actualizarTransferenciaPanaderia: " . $e->getMessage());
        return false;
    }
}

// Función para recalcular saldos en caja_principal
function recalcularSaldosCajaPrincipal($conn, $desde_operacion)
{
    $conn->begin_transaction();
    try {
        // Obtener saldo anterior
        $query = "SELECT IFNULL(MAX(saldo), 0) as saldo 
                 FROM caja_principal 
                 WHERE numero_operacion < ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $desde_operacion);
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo = $result->fetch_assoc()['saldo'];
        $stmt->close();

        // Obtener operaciones a recalcular
        $res = mysqli_query($conn, "SELECT numero_operacion, entrada, salida 
                                  FROM caja_principal 
                                  WHERE numero_operacion >= $desde_operacion 
                                  ORDER BY numero_operacion ASC");
        if (!$res) {
            throw new Exception("Error al obtener operaciones para recalcular: " . mysqli_error($conn));
        }

        // Recalcular cada operación
        while ($row = mysqli_fetch_assoc($res)) {
            $saldo = $saldo + floatval($row['entrada']) - floatval($row['salida']);

            $stmt = $conn->prepare("UPDATE caja_principal SET saldo = ? 
                                  WHERE numero_operacion = ?");
            $stmt->bind_param("di", $saldo, $row['numero_operacion']);
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar saldo: " . $stmt->error);
            }
            $stmt->close();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en recalcularSaldosCajaPrincipal: " . $e->getMessage());
        return false;
    }
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_operacion'])) {
    try {
        $codigo        = intval($_POST['codigo']);
        $observaciones = $_POST['observaciones'];
        $entrada_nueva = isset($_POST['valor_contado_entrada']) ? floatval($_POST['valor_contado_entrada']) : 0;
        $salida_nueva  = isset($_POST['valor_entregado_salida']) ? floatval($_POST['valor_entregado_salida']) : 0;

        // Validación adicional
        if ($entrada_nueva < 0 || $salida_nueva < 0) {
            throw new Exception("Los valores no pueden ser negativos");
        }

        // Iniciar transacción principal
        $conn->begin_transaction();

        // 1. Actualizar caja_principal
        $stmt = $conn->prepare("UPDATE caja_principal SET entrada = ?, salida = ? 
                              WHERE numero_operacion = ?");
        $stmt->bind_param("ddi", $entrada_nueva, $salida_nueva, $numero);
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar caja_principal: " . $stmt->error);
        }
        $stmt->close();

        // 2. Actualizar detalles
        if (isset($_POST['tipo_detalle'])) {
            $tipo = $_POST['tipo_detalle'];
            if (isset($_POST['valor_contado_entrada'])) {
                $fecha_doc = $_POST['fecha_documento'] ?: null;
                $cantidad  = $_POST['cantidad'] !== '' ? floatval($_POST['cantidad']) : 0;

                $stmt = $conn->prepare("UPDATE entradas_caja_principal SET tipo_entrada = ?, establecimiento_codigo = ?, fecha_documento = ?, cantidad = ?, observaciones = ? WHERE numero_operacion = ?");
                $stmt->bind_param("sisdsi", $tipo, $codigo, $fecha_doc, $cantidad, $observaciones, $numero);
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar entradas: " . $stmt->error);
                }
                $stmt->close();
            } elseif (isset($_POST['valor_entregado_salida'])) {
                $stmt = $conn->prepare("UPDATE salidas_caja_principal SET tipo_salida = ?, establecimiento_codigo = ?, observaciones = ? WHERE numero_operacion = ?");
                $stmt->bind_param("sisi", $tipo, $codigo, $observaciones, $numero);
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar salidas: " . $stmt->error);
                }
                $stmt->close();

                // 3. Si es transferencia a panadería, actualizar caja_panaderia
                if ($codigo == 728) {
                    if (!actualizarTransferenciaPanaderia($conn, $numero, $salida_nueva)) {
                        throw new Exception("Error al actualizar transferencia en panadería");
                    }
                }
            }
        }

        // 4. Recalcular saldos en caja_principal
        if (!recalcularSaldosCajaPrincipal($conn, $numero)) {
            throw new Exception("Error al recalcular saldos en caja principal");
        }

        // Confirmar transacción principal
        $conn->commit();

        $_SESSION['success_msg'] = "✅ Operación #$numero actualizada correctamente.";
        header("Location: caja_principal.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error al actualizar operación: " . $e->getMessage());
        $_SESSION['error_msg'] = "❌ Error al actualizar la operación: " . $e->getMessage();
        header("Location: editar_operacion_caja_principal.php?numero=$numero");
        exit();
    }
}

// Cargar datos actualizados después de redirigir
$base = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM caja_principal WHERE numero_operacion = $numero"));
if (!$base) {
    echo "<p>⚠️ Operación no encontrada.</p>";
    exit();
}

$entrada = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM entradas_caja_principal WHERE numero_operacion = $numero"));
$salida  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM salidas_caja_principal WHERE numero_operacion = $numero"));
$establecimientos = mysqli_query($conn, "SELECT codigo, nombre FROM establecimientos WHERE mostrar_en_caja = 1");
?>

<nav class="sub-menu">
    <ul>
        <li><a href="caja_principal.php" class="sub-menu-button">← Volver</a></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Editar operación #<?= $numero ?></h2>

    <form method="POST" id="formEditarOperacion" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?numero=<?= $numero ?>">
        <label for="codigo">Establecimiento:</label>
        <select name="codigo" required>
            <?php mysqli_data_seek($establecimientos, 0);
            while ($e = mysqli_fetch_assoc($establecimientos)): ?>
                <?php $selected = ($entrada && $entrada['establecimiento_codigo'] == $e['codigo']) || ($salida && $salida['establecimiento_codigo'] == $e['codigo']); ?>
                <option value="<?= $e['codigo'] ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
            <?php endwhile; ?>
        </select>

        <label for="tipo_detalle"><?= $entrada ? 'Tipo de entrada:' : 'Tipo de salida:' ?></label>
        <select name="tipo_detalle" id="tipo_salida_select">
            <?php
            $tipos = $entrada
                ? ['Dinero de tranferencias', 'Dinero de IPV', 'Pago de deudas', 'Dinero de Tonito', 'Otras entradas', 'Ajustes']
                : ['Gasto', 'Compra de productos', 'Compra de divisas', 'Inversiones', 'Prestamos', 'Utilidad socio', 'Dinero para Tonito', 'Otras salidas', 'Transferencia',  'Ajustes'];

            // Agregar la opción de transferencia si es salida
            if (!$entrada) {
                array_splice($tipos, 8, 0, 'Transferencia');
            }

            $actual = $entrada ? $entrada['tipo_entrada'] : $salida['tipo_salida'];
            foreach ($tipos as $tipo):
                $style = ($tipo === 'Transferencia') ? 'style="display:none;"' : '';
            ?>
                <option value="<?= $tipo ?>" <?= $tipo === $actual ? 'selected' : '' ?> <?= $style ?> id="<?= $tipo === 'Transferencia' ? 'transferencia_opcion' : '' ?>"><?= $tipo ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($entrada): ?>
            <div id="fechaDocumentoGroup" style="<?= ($entrada['tipo_entrada'] === 'Dinero de IPV') ? '' : 'display: none;' ?>">
                <label for="fecha_documento">Fecha del documento:</label>
                <input type="date" name="fecha_documento" value="<?= $entrada['fecha_documento'] ?? '' ?>">
            </div>

            <label for="cantidad">Cantidad estimada:</label>
            <input type="number" step="0.01" name="cantidad" value="<?= $entrada['cantidad'] ?>">

            <div class="input-group">
                <label for="valor_contado_entrada">Cantidad que entró:</label>
                <div class="input-with-button" style="display: flex; gap: 8px;">
                    <input type="number" step="0.01" name="valor_contado_entrada" value="<?= $base['entrada'] ?>" required style="flex: 1; min-width: 120px;">
                    <button type="button" class="btn-contador" data-modo="entrada" style="width: 50%; white-space: nowrap;">🧮 Contador de dinero</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($salida): ?>
            <div class="input-group">
                <label for="valor_entregado_salida">Cantidad que salió:</label>
                <div class="input-with-button" style="display: flex; gap: 8px;">
                    <input type="number" step="0.01" name="valor_entregado_salida" value="<?= $base['salida'] ?>" required style="flex: 1; min-width: 120px;">
                    <button type="button" class="btn-contador" data-modo="salida" style="width: 50%; white-space: nowrap;">🧮 Contador de dinero</button>
                </div>
            </div>
        <?php endif; ?>

        <label for="observaciones">Observaciones:</label>
        <textarea name="observaciones" maxlength="250"><?= $entrada['observaciones'] ?? $salida['observaciones'] ?? '' ?></textarea>

        <div id="formularioErrorContainer" class="alert-error" style="display: none;"></div>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_operacion'])): ?>
            <div class="alert-success" id="mensajeExito">✅ Operación actualizada correctamente.</div>
        <?php endif; ?>

        <div style="text-align: center;">
            <button type="submit" name="actualizar_operacion" style="width: 220px;">Actualizar operación</button>
            <br>
            <a href="caja_principal.php" class="btn-preview" style="display: inline-block; width: 190px; margin-top: 10px; text-align: center;">← Volver</a>
        </div>
    </form>

    <!-- Modal para el contador de dinero -->
    <div id="contadorModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <iframe id="contadorIframe" src="" frameborder="0" style="width:100%; height:80vh;"></iframe>
        </div>
    </div>
</div>

<script>
    document.getElementById('formEditarOperacion').addEventListener('submit', function(e) {
        const entrada = document.querySelector('[name="valor_contado_entrada"]');
        const salida = document.querySelector('[name="valor_entregado_salida"]');
        const cantidadRaw = document.querySelector('[name="cantidad"]');
        const tipo = entrada ? 'entrada' : 'salida';
        let valido = true;
        let mensaje = '';

        if (tipo === 'entrada') {
            if (cantidadRaw && cantidadRaw.value !== '') {
                const cantidad = parseFloat(cantidadRaw.value);
                if (isNaN(cantidad) || cantidad < 0) {
                    valido = false;
                    mensaje = '⚠️ La cantidad estimada debe ser mayor o igual a cero.';
                }
            }

            const valorContado = parseFloat(entrada.value);
            if (isNaN(valorContado) || valorContado <= 0) {
                valido = false;
                mensaje = '⚠️ La cantidad que entró debe ser un número positivo.';
            }
        }

        if (tipo === 'salida') {
            const valorSalio = parseFloat(salida.value);
            if (isNaN(valorSalio) || valorSalio <= 0) {
                valido = false;
                mensaje = '⚠️ La cantidad que salió debe ser un número positivo.';
            }
        }

        if (!valido) {
            e.preventDefault();
            const errorBox = document.getElementById('formularioErrorContainer');
            errorBox.style.display = 'block';
            errorBox.textContent = mensaje;
        }
    });

    window.addEventListener('DOMContentLoaded', function() {
        const mensaje = document.getElementById('mensajeExito');
        if (mensaje) {
            setTimeout(() => {
                mensaje.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, 200);
        }

        // Manejar visibilidad del campo fecha documento
        const tipoEntradaSelect = document.querySelector('[name="tipo_detalle"]');
        const fechaDocumentoGroup = document.getElementById('fechaDocumentoGroup');

        function actualizarVisibilidadFecha() {
            if (tipoEntradaSelect && tipoEntradaSelect.value === 'Dinero de IPV') {
                if (fechaDocumentoGroup) fechaDocumentoGroup.style.display = 'block';
            } else {
                if (fechaDocumentoGroup) fechaDocumentoGroup.style.display = 'none';
                const fechaInput = fechaDocumentoGroup.querySelector('input[name="fecha_documento"]');
                if (fechaInput) fechaInput.value = '';
            }
        }

        if (tipoEntradaSelect) {
            tipoEntradaSelect.addEventListener('change', actualizarVisibilidadFecha);
            actualizarVisibilidadFecha();
        }
    });

    // Funciones para el contador de dinero
    function abrirContadorModal(modo) {
        const modal = document.getElementById('contadorModal');
        const iframe = document.getElementById('contadorIframe');

        iframe.src = `contador_dinero.php?modo=${modo}`;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function cerrarContadorModal() {
        const modal = document.getElementById('contadorModal');
        const iframe = document.getElementById('contadorIframe');

        modal.style.display = 'none';
        iframe.src = '';
        document.body.style.overflow = 'auto';
    }

    document.querySelectorAll('.btn-contador').forEach(btn => {
        btn.addEventListener('click', function() {
            const modo = this.getAttribute('data-modo');
            abrirContadorModal(modo);
        });
    });

    document.querySelector('.close-modal').addEventListener('click', cerrarContadorModal);

    document.getElementById('contadorModal').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarContadorModal();
        }
    });

    function recibirValorContador(valor, modo) {
        const campo = document.querySelector(`[name="valor_${modo === 'entrada' ? 'contado_entrada' : 'entregado_salida'}"]`);
        if (campo) {
            campo.value = valor;
        }
        cerrarContadorModal();
    }

    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin) return;

        const data = event.data;

        if (data.action === 'aplicarTotalContador') {
            const campo = document.querySelector(
                `[name="${data.modo === 'entrada' ? 'valor_contado_entrada' : 'valor_entregado_salida'}"]`
            );

            if (campo) {
                campo.value = data.total;
            }
        }

        if (data.action === 'cerrarModalContador') {
            cerrarContadorModal();
        }
    });

    // Manejar cambio de establecimiento para transferencias a panadería
    /*   document.querySelector('[name="codigo"]').addEventListener('change', function() {
           const codigo = this.value;
           const tipoSalidaSelect = document.getElementById('tipo_salida_select');
           const transferenciaOpcion = document.getElementById('transferencia_opcion');

           if (!tipoSalidaSelect || !transferenciaOpcion) return;

           if (codigo == '728') {
               // Mostrar y seleccionar automáticamente Transferencia entre cajas
               transferenciaOpcion.style.display = 'block';
               tipoSalidaSelect.value = 'Transferencia';
               tipoSalidaSelect.disabled = true;
           } else {
               // Ocultar y habilitar el select
               transferenciaOpcion.style.display = 'none';
               tipoSalidaSelect.disabled = false;
               if (tipoSalidaSelect.value === 'Transferencia') {
                   tipoSalidaSelect.value = '';
               }
           }
       }); */

    // Ejecutar al cargar para establecer estado inicial si ya está seleccionado 728
    /*   document.addEventListener('DOMContentLoaded', function() {
           const codigoSelect = document.querySelector('[name="codigo"]');
           if (codigoSelect && codigoSelect.value == '728') {
               codigoSelect.dispatchEvent(new Event('change'));
           }
       }); */
</script>

<?php include('../templates/footer.php'); ?>