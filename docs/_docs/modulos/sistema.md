---
layout: default
title: "Sistema"
parent: "Módulos"
nav_order: 1
---

# ⚙️ Sistema

El módulo **Sistema** contiene todas las funciones de administración de la aplicación.
- 📁 Ubicacion: `datafun\app\views\system`
- ☰ Menú: **Inicio** y **sistema**
- 🔑 Nivel de acceso: **🛠️ Administrador**

## 📄 Contenido

[1. 🔑 Login ](#1--login)  
[2. 🏠 Inicio](#2--inicio)  
[3. 📦 Respaldar BD](#3--respaldar-bd)    
[4. 👥 Usuarios](#4--usuarios)  
[5. 💰 Centros de costos](#5--centros-de-costos)  
[6. 🔐 Permisoss](#6--permisos)  
[7. ❌ IPs Bloqueadas](#7--ips-bloqueadas)  
[8. ⚠️ Inicios Fallidos](#8-️-inicios-fallidos)  

## 1. 🔑 Login

- 📋 Descripción: Script de autenticación de usuarios que forma parte de un sistema web. Su función principal es verificar la identidad de los usuarios mediante nombre de usuario y contraseña, proporcionando seguridad contra accesos no autorizados.
- 📁 Ubicacion: Pantalla inicial al acceder al sitio.
- 🎨 Templates: No utiliza templates.
- ⚙️ Controladores: `datafun\app\controllers\config.php` -> Configuración de base de datos
- ☰ Menú: No tiene.
- 🔑 Nivel de acceso: Página de cara a internet. Todos tienen acceso. 
- 🛢️ Tablas: 
  1. `users`
     - `id`: Identificador único del usuario
     - `username`: Nombre de usuario
     - `password`: Contraseña encriptada (hash)
     - `role`: Rol o tipo de usuario
  2. `intentos_login`
     - `direccion_ip`: Dirección IP del usuario
     - `username`: Nombre de usuario intentado
     - `hora_intento`: Fecha y hora del intento (automático)
  3. `ips_bloqueadas` 
     - `direccion_ip`: IP bloqueada
     - `intentos`: Número de intentos que causaron el bloqueo
     - `bloqueado_por`: Quién bloqueó la IP (opcional)
- ⚠️ Manejo de Errores:
  1. No muestra errores al usuario 
  ``` php
    (ini_set('display_errors', 0);)
  ```
  2. Registra errores en archivo
  ``` php
    ini_set('log_errors', 1); 
    ini_set('error_log', '../../logs/php_login_errors.log');
  ```
- 📝 logs:
   1. Ubicación `datafun\logs\php_login_errors.php`
   2. Contenido: Errores del sistema, problemas de base de datos
   3. Rotación: Debe configurarse para evitar que crezcan demasiado
  
- 🛡️ Seguridad: 
  1. Validación de Entrada
  2. Consultas Preparadas
  3. Almacenamiento encriptado de Contraseñas (hashes)
  4. Headers de Seguridad
   

- 🔄 Flujo Visual del Proceso

```
Usuario ingresa credenciales
         ↓
Verifica si IP está bloqueada → Si está bloqueada → Muestra error
         ↓ (No bloqueada)
Verifica usuario y contraseña
         ↓
   ¿Credenciales correctas?
         ↓
     Sí          No
     ↓           ↓
 Login exitoso  Registra intento
     ↓           ↓
 Limpia intentos Cuenta intentos recientes
     ↓           ↓
 Crea sesión     ¿Más de 5 intentos?
     ↓           ↓
 Redirige        Sí          No
     ↓           ↓           ↓
 Dashboard    Bloquea IP  Muestra intentos restantes

```

## 2. 🏠 Inicio
- 📋 Descripción: Esta página PHP funciona como un dashboard de usuario que muestra información personalizada según el rol del usuario autenticado. Se utiliza como pantalla de Bienvenida al sistema.
- 📁 Ubicacion: `datafun\app\views\system\dashboard.php`
- 🎨 Templates: 
  1. `datafun\app\templates\header.php` 
  2. `datafun\app\templates\footer.php`
- ⚙️ Controladores: No tiene.
- ☰ Menú: **Inicio** 
- 🔑 Nivel de acceso:
  1. **🛠️ Administrador** 
  2. **👤 Usuario**
- 🛢️ Tablas: No tiene.
- 🛡️ Seguridad: 
  1. Protección XSS (Cross-Site Scripting)
  2. Manejo de Sesiones
- 🔣 Variables:
``` php
    $_SESSION['username']: // Nombre del usuario
    $_SESSION['role']: // Rol del usuario
```
- 💻 Funciones:
  1. Muestra la información del usuario logueado (usuario y rol).
  2. Muestra contenido diferente según el rol.

- 🚀 Escalabilidad
  1. Desarrollar un sistema de noticias y novedades.
  2. Que esta pagina sirva para que el usuario pueda maantenerse informado.

## 3. 📦 Respaldar BD
- 📋 Descripción: Script PHP para generación automática de respaldos completos de base de datos MySQL/MariaDB, incluyendo estructura, datos y compresión en formato ZIP.
- 📁 Ubicacion: No tiene view.
- 🎨 Templates: No utiliza templates.
- ⚙️ Controladores: 
  1. `datafun\app\controllers\exportar_bd.php` -> Contiene la logica del respaldo y descarga de la base de datos.
  2. `datafun\app\controllers\auth_admin_check.php` -> Contiene la logica para confirmar que un administrador este logueado.
  3. `datafun\app\controllers\config.php` -> Configuración de base de datos
- ☰ Menú: **Sistema > Respaldar BD** 
- 🔑 Nivel de acceso: **🛠️ Administrador** 
- 🛢️ Tablas: Involucra todas las tablas de la base de datos.
- 🛡️ Seguridad: 
  1. Manejo de Sesiones
  2. Control de Permisos
- 📋 Requisitos: 
  1. Extensión: zip (ZipArchive class)
  2. Versión: PHP 7.4+ (para arrow functions)
  3. MySQLi: Extensión activa
- 🔄 Flujo de Ejecución:
  1. Inicio sesión → Verifica/incia sesión PHP
  2. Autenticación → Valida usuario administrador
  3. Conexión BD → Establece y verifica conexión
  4. Extracción → Genera SQL de estructura y datos
  5. Archivo temporal → Guarda SQL en disco
  6. Compresión → Crea archivo ZIP
  7. Descarga → Envía ZIP al navegador
  8. Limpieza → Elimina archivos temporales
  9. Terminación → Finaliza script exitosamente
- ⛔ Limitaciones 
  1. Tamaño de BD: Puede agotar memoria con bases muy grandes
  2. Tiempo de ejecución: Script puede timeout con muchas tablas
  3. Caracteres especiales: Depende de configuración UTF-8
  4. Espacio en disco: Requiere espacio temporal para SQL + ZIP

## 4. 👥 Usuarios

- 📋 Descripción: Es una página de administración que permite gestionar todos los usuarios del sistema. Es como el "panel de control" donde los administradores pueden crear, modificar y eliminar cuentas de usuario.
- 📁 Ubicacion: `datafun\app\views\admin\usuarios.php`
- 🎨 Templates: 
  1. `datafun\app\templates\header.php` - Cabecera del sistema
  2. `datafun\app\templates\footer.php` - Pie de página del sistema
- ⚙️ Controladores: 
  1. `datafun\app\controllers\auth_admin_check.php` - Verificación de permisos de administrador
  2. `datafun\app\controllers\controllers\config.php` - Configuración de base de datos y conexión
  3. `datafun\app\controllers\controllers\check_username.php` - Validación asíncrona de nombre de usuario
- ☰ Menú: **Sistema > Usuarios** 
- 🔑 Nivel de acceso: 
  1. Rol: **🛠️ Administrador** 
  2. Permisos: CRUD completo de usuarios
- 3. Restricción: No puede auto-eliminarse
- 🛢️ Tablas: 
  1. `users`
    - `id` - Identificador único
    - `username` - Nombre de usuario (único)
    - `password` - Contraseña hasheada
    - `role` - Rol del usuario (Usuario/Administrador)
- ⚠️ Manejo de Errores:
  1. Mensajes de éxito para operaciones completadas.
  2. Mensajes de error para operaciones fallidas.
  3. Validación de nombre de usuario único.
  4. Prevención de auto-eliminación.
  5. Control de token CSRF inválidO.
- 🛡️ Seguridad: 
  1. Protección CSRF (Cross-Site Request Forgery).
  2. Autenticación y Autorización.
  3. Validación de Entrada.
  4. Protección contra Inyección SQL.
  5. Escape de Salida HTML.
  6. Control de Acceso Basado en Roles.
- 🎯 Funcionalidades CRUD Completas:
  1. CREATE (Crear)
    - Registro de nuevos usuarios
    - Asignación de roles
    - Validación de unicidad
  2. READ (Leer)
    - Listado paginado de usuarios
    - Visualización de roles
    - Búsqueda y filtrado
  3. UPDATE (Actualizar)
    - Cambio de contraseñas
    - Modificación de roles (implícito)
  4. DELETE (Eliminar)
    - Eliminación segura de usuarios
    - Confirmación de acción
    - Prevención de auto-eliminación

- 🔄 Flujo Visual del Proceso:
```
Usuario administrador accede a la página
         ↓
Verifica que sea administrador → Si no es admin → Acceso denegado
         ↓ (Es admin)
Carga lista de usuarios paginada
         ↓
Muestra tabla con opciones
         ↓
¿Qué acción quiere realizar?
         ↓
Registrar nuevo usuario    Cambiar contraseña    Eliminar usuario
         ↓                   ↓                   ↓
Muestra formulario       Muestra formulario   Muestra confirmación
         ↓                   ↓                   ↓
Valida datos             Actualiza contraseña  Verifica no auto-eliminación
         ↓                   ↓                   ↓
Crea usuario             Muestra confirmación  Elimina usuario
         ↓                   ↓                   ↓
Recarga página           Recarga página       Recarga página
```



## 5. 💰 Centros de costos

- 📋 Descripción:
- 📁 Ubicacion:
- 🎨 Templates:
- ⚙️ Controladores: 
- ☰ Menú: 
- 🔑 Nivel de acceso: 
- 🛢️ Tablas: 
- ⚠️ Manejo de Errores:
- 🛡️ Seguridad: 
- 🎯 Funcionalidades CRUD:
- 🔄 Flujo Visual del Proceso:



## 6. 🔐 Permisos

- 📋 Descripción:
- 📁 Ubicacion:
- 🎨 Templates:
- ⚙️ Controladores: 
- ☰ Menú: 
- 🔑 Nivel de acceso: 
- 🛢️ Tablas: 
- ⚠️ Manejo de Errores:
- 🛡️ Seguridad: 
- 🎯 Funcionalidades CRUD:
- 🔄 Flujo Visual del Proceso:


## 7. ❌ IPs Bloqueadas

- 📋 Descripción: Página de gestión y visualización de direcciones IP bloqueadas por intentos fallidos de autenticación o actividades sospechosas. Proporciona una interfaz administrativa para monitorear y liberar bloqueos de seguridad.
- 📁 Ubicacion: `datafun/admin/security/ips_bloqueadas.php`
- 🎨 Templates:
  1. `datafun/app/templates/header.php` - Cabecera del sistema
  2. `datafun/app/templates/footer.php` - Pie de página del sistema  
- ⚙️ Controladores: 
  1. `datafun/app/controllers/auth_admin_check.php` -  Validación de permisos de administrador
  2. `datafun/app/controllers/config.php` - Configuración de base de datos  
- ☰ Menú: **Sistema > IPs Bloqueadas** 
- 🔑 Nivel de acceso: 
  1. Rol Requerido: Administrador
  2. Permisos: Lectura/Escritura
  3. Restricciones:
    - Acceso solo mediante autenticación válida
    - Verificación de token CSRF para acciones destructivas
    - Validación de sesión activa
- 🛢️ Tablas: 
  1. `ips_bloqueadas`
    - `id`
    - `direccion_ip`
    - `intentos`
    - `bloqueado_en`
    - `bloqueado_por`
  2. `users`
    - `id`
    - `username`
- ⚠️ Manejo de Errores:
  1. Errores de Base de Datos.
  2. Errores de Seguridad.
- 🛡️ Seguridad: 
  1. CSRF Protection.
  2. SQL Injection Prevention.
  3. XSS Prevention.
  4. Authorization.
  5. Session Security.
- 🎯 Funcionalidades CRUD:
  1. Create (C)
    - ❌ No disponible en esta vista
    - (Los bloqueos se crean automáticamente por el sistema) 
  2. Read (R):
    - Listado completo de IPs bloqueadas
    - Ordenamiento por fecha descendente
    - JOIN con tabla users para información de quién bloqueó
  3. Update (U):
    - ❌ No disponible en esta vista
    - (Los bloqueos no se modifican, solo se eliminan)
  4. Delete (D):
    - Eliminación individual por ID
    - Confirmación mediante interfaz modal
    - Procesamiento seguro con prepared statements
- 🔄 Flujo Visual del Proceso:
  1.  Visualización Inicial
  ```
  [Carga Página]
        ↓
  [Verificación Admin]
        ↓
  [Generar Token CSRF]
        ↓
  [Consulta IPs Bloqueadas]
        ↓
  [Renderizar Tabla]
        ↓
  [Mostrar Notificaciones]
  ```
  2. Proceso de Eliminación:
  ```
  [Usuario hace click "Eliminar Bloqueo"]
          ↓
  [Mostrar Diálogo de Confirmación]
          ↓
  [Usuario confirma eliminación]
          ↓
  [Enviar POST con CSRF + ID]
          ↓
  [Validar Token CSRF]
          ↓
  [Ejecutar DELETE en Base de Datos]
          ↓
  [Recibir Resultado Operación]
          ↓
  [Guardar Mensaje en Sesión]
          ↓
  [Redirección GET]
          ↓
  [Mostrar Resultado/Notificación]
  ```

## 8. ⚠️ Inicios Fallidos

- 📋 Descripción: Página de administración para gestión de registros de intentos de login fallidos. Proporciona capacidades de visualización, monitoreo y eliminación de intentos de acceso fallidos al sistema, funcionando como herramienta de seguridad para prevenir ataques por fuerza bruta.
- 📁 Ubicacion: `datafun/views/admin/security/intentos_login.phpdatafun/views/admin/security/intentos_login.php`
- 🎨 Templates:
  1. `datafun/app/templates/header.php` - Cabecera del sistema
  2. `datafun/app/templates/footer.php` - Pie de página del sistema
- ⚙️ Controladores: 
  1. `datafun/app/controllers/auth_admin_check.php` -  Validación de permisos de administrador
  2. `datafun/app/controllers/config.php` - Configuración de base de datos
- ☰ Menú: **Sistema > Inicios Fallidos** 
- 🔑 Nivel de acceso: 
  1. Rol: **🛠️ Administrador** 
  2. Permisos:
    - Ver todos los registros.
    - Eliminar registros individuales.
    - Limpiar tabla completa.
- 🛢️ Tablas: 
  1. `intentos_login`
    - `id` 
    - `direccion_ip` 
    - `username`
    - `hora_intento`  
- ⚠️ Manejo de Errores:
  1. CSRF Token Inválido.
  2. Errores de Base de Datos.
  3. Acceso No Autorizado.
  4. Datos Vacíos.
- 🛡️ Seguridad: 
  1. CSRF Protection
  2. SQL Injection Prevention
  3. XSS Prevention
  4. Control de Acceso
  5. Buffer Management
- 🎯 Funcionalidades CRUD:
  1. CREATE (Indirecto)
   - ❌ No aplica - Los registros se crean automáticamente desde login.php
  2. READ ✅ COMPLETO
   - Visualización completa de todos los campos
   - Ordenamiento por fecha descendente
   - Formato responsive para dispositivos móviles
   - Mensaje cuando no hay datos
  3. UPDATE
   - ❌ No aplica - Los intentos de login son registros históricos inmutables
  4. DELETE ✅ COMPLETO
   - Eliminación Individual.
   - Eliminación Masiva.
- 🔄 Flujo Visual del Proceso:

1. Flujo Prncipal
```
  [USUARIO ACCEDE] 
      ↓
  [auth_admin_check.php] → [¿Es admin?] → NO → [Redirige a Login]
      ↓ SÍ
  [Generar Token CSRF] 
      ↓
  [Cargar Registros BD] → [¿Error?] → SÍ → [Mostrar Error]
      ↓ NO  
  [Renderizar Interfaz]
      ↓
  [Usuario Ve Tabla] → [¿Vacía?] → SÍ → [Mostrar "No hay datos"]
      ↓ NO
  [Mostrar Registros con Botones]
```
2. Flujo Eliminación Individual
```
  [Usuario Click "Eliminar"]
      ↓
  [JavaScript: showDeleteForm(id, ip)]
      ↓  
  [Mostrar Formulario Confirmación]
      ↓
  [Usuario Confirma] 
      ↓
  [POST con CSRF Token]
      ↓
  [Validar CSRF] → [¿Inválido?] → SÍ → [Error y Terminar]
      ↓ NO
  [Prepared Statement DELETE]
      ↓
  [¿Éxito?] → NO → [Mensaje Error]
      ↓ SÍ
  [Session: success_msg]
      ↓
  [Redirección a self] → [Recarga sin registro eliminado]
```

3. Flujo Eliminación Masiva
```
  [Usuario Click "Limpiar Todos"]
      ↓
  [JavaScript: showDeleteAllForm()]
      ↓
  [Mostrar Advertencia Severa]
      ↓
  [Usuario Confirma (doble verificación)]
      ↓
  [POST con CSRF Token]
      ↓
  [Validar CSRF] → [¿Inválido?] → SÍ → [Error y Terminar]
      ↓ NO
  [TRUNCATE TABLE intentos_login]
      ↓
  [¿Éxito?] → NO → [Mensaje Error BD]
      ↓ SÍ  
  [Session: success_msg]
      ↓
  [Redirección a self] → [Tabla Vacía + Mensaje "No hay datos"]
```