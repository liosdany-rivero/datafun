/**
 * ARCHIVO: menu.js
 * DESCRIPCIÓN: Controlador global de interfaz y funciones compartidas
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * 1. Gestión del menú responsive (hamburguesa y submenús)
 * 2. Sistema de notificaciones flotantes
 * 3. Funciones utilitarias globales
 * 4. Control de formularios modales
 * 
 * DEPENDENCIAS:
 * - Requiere que las páginas usen las clases CSS estándar:
 *   - 'floating-notification' para notificaciones
 *   - 'sub-form' para formularios modales
 * 
 * USO RECOMENDADO:
 * 1. Incluir este archivo después de jQuery (si se usa) y antes de otros JS específicos
 * 2. Las páginas pueden extender funcionalidad añadiendo sus propios scripts después
 */

document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // SECCIÓN 1: CONTROL DEL MENÚ PRINCIPAL (RESPONSIVE)
    // =============================================
    
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNavigation');
    const submenuParents = document.querySelectorAll('.menu-item-has-children');

    if (menuToggle && mainNav) {
        /**
         * Cierra todos los submenús excepto el especificado
         * @param {HTMLElement} except - Submenú que no debe cerrarse
         */
        const closeAllSubmenus = (except = null) => {
            submenuParents.forEach(item => {
                const submenu = item.querySelector('.sub-menu');
                if (submenu && submenu !== except) {
                    submenu.style.maxHeight = '0';
                    item.classList.remove('submenu-open');
                }
            });
        };

        /**
         * Alterna la visibilidad del menú móvil
         */
        const toggleMobileMenu = () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !isExpanded);
            mainNav.classList.toggle('nav-active');
            if (!isExpanded) closeAllSubmenus();
        };

        // Evento para el botón hamburguesa
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });

        // Control de submenús
        submenuParents.forEach(parent => {
            const trigger = parent.querySelector('a');
            const submenu = parent.querySelector('.sub-menu');

            if (trigger && submenu) {
                // Comportamiento en móviles
                trigger.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        const isOpen = parent.classList.contains('submenu-open');
                        closeAllSubmenus(isOpen ? null : submenu);
                        
                        if (!isOpen) {
                            submenu.style.maxHeight = submenu.scrollHeight + 'px';
                            parent.classList.add('submenu-open');
                        } else {
                            submenu.style.maxHeight = '0';
                            parent.classList.remove('submenu-open');
                        }
                    }
                });

                // Comportamiento en desktop (hover)
                if (window.innerWidth > 768) {
                    parent.addEventListener('mouseenter', () => {
                        closeAllSubmenus(submenu);
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                    });

                    parent.addEventListener('mouseleave', () => {
                        submenu.style.maxHeight = '0';
                    });
                }
            }
        });

        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.main-navigation') && !e.target.closest('.menu-toggle')) {
                closeAllSubmenus();
                if (window.innerWidth <= 768) {
                    menuToggle.setAttribute('aria-expanded', 'false');
                    mainNav.classList.remove('nav-active');
                }
            }
        });

        // Reconfigurar al cambiar tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeAllSubmenus();
                menuToggle.setAttribute('aria-expanded', 'false');
                mainNav.classList.remove('nav-active');
                document.querySelectorAll('.sub-menu').forEach(submenu => {
                    submenu.style.maxHeight = '';
                });
            }
        });
    }

    // =============================================
    // SECCIÓN 2: SISTEMA DE NOTIFICACIONES FLOTANTES
    // =============================================
    
    /**
     * Inicializa y gestiona las notificaciones emergentes
     * - Muestra automáticamente las notificaciones existentes al cargar
     * - Las oculta después de 5 segundos con animación
     * - Compatible con múltiples tipos (success, error, warning)
     */
    const initNotifications = () => {
        const notification = document.getElementById('floatingNotification');
        if (notification) {
            // Mostrar con pequeño retraso para permitir renderizado
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            // Ocultar después de 5 segundos
            setTimeout(() => {
                notification.classList.remove('show');
                // Eliminar del DOM después de la animación
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);

            // Opcional: Permitir cerrar manualmente
            notification.addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
        }
    };
    
    // Inicializar notificaciones al cargar la página
    initNotifications();

    // =============================================
    // SECCIÓN 3: FUNCIONES UTILITARIAS GLOBALES
    // =============================================
    
    /**
     * Hace scroll suave al final de la página
     * @param {boolean} smooth - Si true, hace scroll animado
     */
    window.scrollToBottom = function(smooth = true) {
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    };

    /**
     * Oculta todos los formularios modales de la página
     * (Identificados por la clase 'sub-form')
     */
    window.hideForms = function() {
        document.querySelectorAll('.sub-form').forEach(form => {
            form.style.display = 'none';
        });
    };

    /**
     * Muestra un formulario modal específico
     * @param {string} formId - ID del formulario a mostrar
     */
    window.showForm = function(formId) {
        hideForms();
        const form = document.getElementById(formId);
        if (form) {
            form.style.display = 'block';
            scrollToBottom();
        }
    };

    /**
     * Reinicia los campos de un formulario
     * @param {string} formId - ID del formulario a resetear
     */
    window.resetForm = function(formId) {
        const form = document.getElementById(formId);
        if (form) form.reset();
    };

    // =============================================
    // SECCIÓN 4: CONFIGURACIÓN INICIAL
    // =============================================
    
    // Configuración inicial para menús
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.sub-menu').forEach(submenu => {
            submenu.style.maxHeight = '0';
        });
    }
});