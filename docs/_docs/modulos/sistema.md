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
[1. 🏠 Inicio](#1--inicio)  
[2. 📦 Respaldar BD](#2--respaldar-bd)    
[3. 👥 Usuarios](#3--usuarios)  
[4. 💰 Centros de costos](#4--centros-de-costos)  
[5. 🔐 Permisoss](#5--permisos)  
[6. ❌ IPs Bloqueadas](#6--ips-bloqueadas)  
[7. ⚠️ Inicios Fallidos](#7-️-inicios-fallidos)  

## 1. 🏠 Inicio
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

## 2. 📦 Respaldar BD
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
```
- 💻 Funciones:
  1. Muestra la información del usuario logueado (usuario y rol).
  2. Muestra contenido diferente según el rol.

- 🚀 Escalabilidad
  1. Desarrollar un sistema de noticias y novedades.
  2. Que esta pagina sirva para que el usuario pueda maantenerse informado.





## 3. 👥 Usuarios

## 4. 💰 Centros de costos

## 5. 🔐 Permisos

## 6. ❌ IPs Bloqueadas

## 7. ⚠️ Inicios Fallidos