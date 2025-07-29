<script>
    document.addEventListener('DOMContentLoaded', function() {
        const submenus = document.querySelectorAll('.submenu > a');

        // Función para cerrar todos los dropdowns
        function closeAllDropdowns(except = null) {
            document.querySelectorAll('.dropdown').forEach(d => {
                if (d !== except) {
                    d.style.display = 'none';
                    d.classList.remove('show');
                }
            });
        }

        // Cerrar todos los dropdowns al cargar la página
        closeAllDropdowns();

        // Para todos los dispositivos
        submenus.forEach(item => {
            // Manejar clic
            item.addEventListener('click', function(e) {
                const dropdown = this.nextElementSibling;
                const isOpen = dropdown.classList.contains('show');

                // Solo prevenir el comportamiento por defecto en móviles
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                // Cerrar todos los demás dropdowns
                closeAllDropdowns(isOpen ? null : dropdown);

                // Abrir/cerrar el actual
                if (!isOpen) {
                    dropdown.style.display = 'flex';
                    dropdown.classList.add('show');
                } else {
                    dropdown.style.display = 'none';
                    dropdown.classList.remove('show');
                }
            });

            // Manejar hover para escritorio
            if (window.innerWidth > 768) {
                item.parentElement.addEventListener('mouseenter', function() {
                    const dropdown = this.querySelector('.dropdown');
                    closeAllDropdowns(dropdown);
                    dropdown.style.display = 'flex';
                });

                item.parentElement.addEventListener('mouseleave', function() {
                    const dropdown = this.querySelector('.dropdown');
                    // Solo cerrar si no está activo por clic (en móvil)
                    if (!dropdown.classList.contains('show')) {
                        dropdown.style.display = 'none';
                    }
                });
            }
        });

        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.submenu')) {
                closeAllDropdowns();
            }
        });

        // Ajustar al cambiar tamaño de pantalla
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                // En escritorio, ocultar todos los dropdowns
                closeAllDropdowns();
            }
        });
    });
</script>




</body>

</html>