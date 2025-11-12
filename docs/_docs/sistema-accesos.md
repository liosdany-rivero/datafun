---
layout: default
title: "Sistema de Accesos"
nav_order: 2
---

# 🔐 Sistema de Accesos

Documentación completa del sistema de accesos y permisos del ERP DataFun.

## 📄 Contenido

  [👥 1. Roles del Sistema](#1--roles-del-sistema)  
  [🎯 2. Niveles de Acceso](#2--niveles-de-acceso)


## 1. 👥 Roles del Sistema

1. **🛠️ Administrador**
   - Se centra en actividades de la configuracón del sistema.
   - Realiza actividades de supervición y verificacion de operaciones.
  
2. **👤 Usuario**
   - Se centra en las actividades de escritura, lectura, y tramitación del sistema.

### 1.1 ⚙️ Gestión de roles

> 📌 La administración de roles se realiza mediante: `datafun\app\views\system\usuarios.php`  
> 📌 Este archivo se ejecuta desde el menú: **Sistema>Usuarios**

## 2. 🎯 Niveles de Acceso

| Nivel | Permiso             | Descripción                                                         |
| :---: | :------------------ | :------------------------------------------------------------------ |
| **1** | 🛠️ **Administrador** | Configuración del sistema y supervisión/verificación de operaciones |
| **2** | ✏️ **Escribir**      | Creación, edición y eliminación de operaciones del sistema          |
| **3** | 📋 **Tramitar**      | Supervisión y verificación de operaciones                           |
| **4** | 👀 **Leer**          | Solo actividades de lectura de operaciones                          |
| **5** | 🔒 **Sin permisos**  | Acceso limitado a actividades comunes del sistema                   |

### 2.1 🔍 Descripción Detallada

1. 🛠️ **Administrador**  
   - *Enfoque*: Configuración del sistema  
   - *Funciones*: Supervisión y verificación de operaciones  
   - *Acceso*: Completo al sistema  

2. ✏️ **Usuario con permiso: Escribir**  
   - *Enfoque*: Operaciones del sistema  
   - *Funciones*: Creación, edición y eliminación de operaciones  
   - *Acceso*: Modificación de datos  

3. 📋 **Usuario con permiso: Tramitar**  
   - *Funciones*: Supervisión y verificación de operaciones  
   - *Acceso*: Nivel operativo  

4. 👀 **Usuario con permiso: Leer**  
   - *Funciones*: Solo actividades de lectura  
   - *Acceso*: Consulta limitada  

5. 🔒 **Usuario sin permisos**  
   - *Acceso*: Muy limitado  
   - *Funciones*: Solo actividades comunes del sistema  


### 2.2 ⚙️ Gestión de Niveles de Acceso

> 📌 La gestión de accesos se realiza mediante:  
> `datafun\app\views\system\usuarios.php`  
> `datafun\app\views\system\permisos.php`  
> 📌 Estos archivos se ejecutan desde el menú:  
> **Sistema>Usuarios**  
> **Sistema>Permisos**
