<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers anti-caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar autenticación
require_once('../controllers/auth_user_check.php');

// Conexión a la base de datos
require_once('../controllers/config.php');

// Mostrar errores solo en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verificar permisos
$permisoCaja = '';
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $query = "SELECT permiso FROM permisos_establecimientos 
              WHERE user_id = ? AND establecimiento_codigo = 800";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $permisoCaja = $row['permiso'];
    }
    $stmt->close();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$modo = isset($_GET['modo']) ? $_GET['modo'] : 'entrada';
$esCalculadora = $modo === 'calculadora';
$denominaciones = [1, 3, 5, 10, 20, 50, 100, 200, 500, 1000];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esCalculadora ? 'Calculadora de Dinero' : 'Contador de Dinero' ?></title>
    <link rel="stylesheet" href="../../asset/css/style.css">
    <style>
        .input-cantidad {
            width: 80px;
            text-align: right;
        }

        .monto-celda {
            text-align: right;
            padding-right: 15px;
        }

        #totalFinal {
            font-size: 1.2em;
            color: #2c3e50;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-preview {
            padding: 10px 20px;
            min-width: 120px;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h2><?= $esCalculadora ? 'Calculadora de dinero' : 'Contador de dinero - ' . ucfirst($modo) ?></h2>

        <table class="table" id="tablaContador">
            <thead>
                <tr>
                    <th>Denominación</th>
                    <th>Cantidad</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($denominaciones as $den): ?>
                    <tr>
                        <td data-label="Denominación">$<?= number_format($den, 2) ?></td>
                        <td data-label="Cantidad">
                            <input type="number" step="1" min="0" class="input-cantidad"
                                data-den="<?= $den ?>" placeholder="0" value="0">
                        </td>
                        <td data-label="Monto" class="monto-celda">$0.00</td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong>Total</strong></td>
                    <td></td>
                    <td><strong id="totalFinal">$0.00</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="button-group">
            <?php if (!$esCalculadora): ?>
                <button type="button" class="btn-preview" id="btnAplicar">Aplicar Total</button>
            <?php endif; ?>
            <button type="button" class="btn-preview" id="btnVolver">Volver</button>
            <button type="button" class="btn-preview" id="btnLimpiar">Limpiar</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const campos = document.querySelectorAll('.input-cantidad');
            const btnAplicar = document.getElementById('btnAplicar');
            const btnVolver = document.getElementById('btnVolver');
            const btnLimpiar = document.getElementById('btnLimpiar');
            let totalCalculado = 0;

            // Función para manejar el foco en los inputs
            function manejarFocoInput(e) {
                if (e.target.value === '0') {
                    e.target.value = '';
                }
            }

            // Función para manejar cuando se pierde el foco
            function manejarBlurInput(e) {
                if (e.target.value === '') {
                    e.target.value = '0';
                    actualizarMontos();
                }
            }

            // Función para actualizar los montos
            function actualizarMontos() {
                totalCalculado = 0;
                campos.forEach(input => {
                    const den = parseFloat(input.dataset.den);
                    const cant = parseInt(input.value) || 0;
                    const monto = den * cant;
                    input.closest('tr').querySelector('.monto-celda').textContent = '$' + monto.toFixed(2);
                    totalCalculado += monto;
                });
                document.getElementById('totalFinal').textContent = '$' + totalCalculado.toFixed(2);
            }

            // Función para limpiar todos los campos
            function limpiarCampos() {
                campos.forEach(input => {
                    input.value = '0';
                });
                actualizarMontos();
            }

            // Event listeners para los campos de cantidad
            campos.forEach(input => {
                input.addEventListener('focus', manejarFocoInput);
                input.addEventListener('blur', manejarBlurInput);

                input.addEventListener('input', function() {
                    if (parseInt(this.value) < 0) this.value = 0;
                    actualizarMontos();
                });

                input.addEventListener('keydown', function(e) {
                    if (['e', 'E', '+', '-'].includes(e.key)) {
                        e.preventDefault();
                    }
                });
            });

            // Botón Aplicar - Solo visible cuando no es calculadora
            if (btnAplicar) {
                btnAplicar.addEventListener('click', function() {
                    if (totalCalculado <= 0) {
                        alert('Por favor ingrese al menos una cantidad válida');
                        return;
                    }

                    // Comunicación con la ventana padre (modal)
                    if (window.parent !== window) {
                        // Enviar el total y cerrar el modal
                        window.parent.postMessage({
                            action: 'aplicarTotalContador',
                            total: totalCalculado.toFixed(2),
                            modo: '<?= $modo ?>'
                        }, '*');

                        // Cerrar el modal inmediatamente después de enviar
                        window.parent.postMessage({
                            action: 'cerrarModalContador'
                        }, '*');
                    } else {
                        // Fallback si no está en modal
                        alert(`Total calculado: $${totalCalculado.toFixed(2)}`);
                    }
                });
            }

            // Botón Volver
            btnVolver.addEventListener('click', function() {
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'cerrarModalContador'
                    }, '*');
                } else {
                    window.location.href = 'caja_principal.php';
                }
            });

            // Botón Limpiar
            btnLimpiar.addEventListener('click', limpiarCampos);

            // Enfocar el primer campo al cargar
            if (campos.length > 0) {
                campos[0].focus();
            }

            // Manejar tecla Enter para aplicar (solo en modo no calculadora)
            if (!<?= $esCalculadora ? 'true' : 'false' ?>) {
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && btnAplicar) {
                        e.preventDefault();
                        btnAplicar.click();
                    }
                });
            }
        });
    </script>
</body>

</html>