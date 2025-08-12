<?php
// Iniciar buffer de salida al principio del script
ob_start();
/**
 * ARCHIVO: contador_dinero.php
 * DESCRIPCIÓN: Calculadora de dinero para sumar diferentes denominaciones
 */

// SECCIÓN 1: INCLUSIONES Y CONFIGURACIÓN
include('../../templates/header.php');          // Cabecera común del sistema
require_once('../../controllers/auth_user_check.php'); // Verificación de permisos

// Obtener parámetros de la URL
$target_field = isset($_GET['target']) ? $_GET['target'] : '';
$source_page = isset($_GET['source']) ? $_GET['source'] : '';
?>

<!-- SECCIÓN 2: INTERFAZ DE USUARIO -->
<div class="form-container" style="max-width: 600px; margin: 20px auto;">
    <h2>Contador de Dinero</h2>

    <table class="table" id="dineroTable">
        <thead>
            <tr>
                <th>Denominación</th>
                <th>Cantidad</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="1" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="1">0.00</td>
            </tr>
            <tr>
                <td>3</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="3" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="3">0.00</td>
            </tr>
            <tr>
                <td>5</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="5" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="5">0.00</td>
            </tr>
            <tr>
                <td>10</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="10" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="10">0.00</td>
            </tr>
            <tr>
                <td>20</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="20" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="20">0.00</td>
            </tr>
            <tr>
                <td>50</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="50" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="50">0.00</td>
            </tr>
            <tr>
                <td>100</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="100" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="100">0.00</td>
            </tr>
            <tr>
                <td>200</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="200" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="200">0.00</td>
            </tr>
            <tr>
                <td>500</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="500" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="500">0.00</td>
            </tr>
            <tr>
                <td>1000</td>
                <td>
                    <input type="number" class="cantidad" data-denominacion="1000" min="0" step="1" oninput="calcularMonto(this)" onchange="calcularMonto(this)">
                </td>
                <td class="monto" data-denominacion="1000">0.00</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td></td>
                <td id="totalMonto">0.00</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Barra de estado con botones -->
<div id="barra-estado">
    <ul class="secondary-nav-menu">
        <li><button onclick="volver()" class="nav-button">Volver</button></li>
        <li><button onclick="limpiar()" class="nav-button">Limpiar</button></li>
        <li><button onclick="asentar()" class="nav-button">Asentar</button></li>
    </ul>
</div>

<!-- SECCIÓN 3: JAVASCRIPT PARA INTERACCIÓN -->


<script>
    // Variable global para almacenar el campo objetivo
    const targetField = '<?= $target_field ?>';
    const sourcePage = '<?= $source_page ?>';
    const formType = '<?= isset($_GET['formType']) ? $_GET['formType'] : '' ?>';

    /**
     * Calcula el monto para una fila específica y actualiza el total
     * @param {object} input - Elemento input que disparó el evento
     */
    function calcularMonto(input) {
        const cantidad = parseInt(input.value) || 0;
        const denominacion = parseInt(input.dataset.denominacion);
        const monto = cantidad * denominacion;

        // Actualizar celda de monto
        const montoCell = document.querySelector(`.monto[data-denominacion="${denominacion}"]`);
        montoCell.textContent = monto.toFixed(2);

        // Calcular total
        calcularTotal();
    }

    /**
     * Calcula el total sumando todos los montos
     */
    function calcularTotal() {
        let total = 0;
        document.querySelectorAll('.monto').forEach(cell => {
            if (cell.id !== 'totalMonto') {
                total += parseFloat(cell.textContent) || 0;
            }
        });

        document.getElementById('totalMonto').textContent = total.toFixed(2);
    }

    /**
     * Limpia todos los campos de la tabla
     */
    function limpiar() {
        document.querySelectorAll('.cantidad').forEach(input => {
            input.value = '';
        });

        document.querySelectorAll('.monto').forEach(cell => {
            cell.textContent = '0.00';
        });

        document.getElementById('totalMonto').textContent = '0.00';
    }

    /**
     * Vuelve a la página anterior
     */
    function volver() {
        if (sourcePage && formType) {
            window.location.href = `${sourcePage}?formType=${formType}`;
        } else {
            window.history.back();
        }
    }

    /**
     * Asienta el total en el campo correspondiente según el origen
     */
    function asentar() {
        const total = parseFloat(document.getElementById('totalMonto').textContent);

        if (sourcePage && targetField) {
            // Guardar en localStorage para que la página padre pueda recuperarlo
            localStorage.setItem('contadorDineroResult', JSON.stringify({
                target: targetField,
                value: total
            }));

            // Volver a la página de origen con el tipo de formulario
            window.location.href = `${sourcePage}?formType=${formType}`;
        } else {
            alert(`Total calculado: ${total.toFixed(2)}`);
        }
    }

    // Configurar event listeners para todos los inputs
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.cantidad');

        // Agregar event listeners para input (tiempo real) y change (por si acaso)
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                calcularMonto(this);
            });
            input.addEventListener('change', function() {
                calcularMonto(this);
            });
        });

        // Enfocar el primer campo
        if (inputs.length > 0) inputs[0].focus();
    });
</script>


<?php include('../../templates/footer.php'); ?>