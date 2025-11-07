# 🔐 Sistema de Accesos

## 🎯 Niveles de Acceso

| Nivel | Permiso              | Descripción                                                         |
| :---: | :------------------- | :------------------------------------------------------------------ |
| **1** | 🛠️ **Administrador** | Configuración del sistema y supervisión/verificación de operaciones |
| **2** | ✏️ **Escribir**      | Creación, edición y eliminación de operaciones del sistema          |
| **3** | 📋 **Tramitar**      | Supervisión y verificación de operaciones                           |
| **4** | 👀 **Leer**          | Solo actividades de lectura de operaciones                          |
| **5** | 🔒 **Sin permisos**  | Acceso limitado a actividades comunes del sistema                   |

---

## 🔍 Descripción Detallada

### 🛠️ 1. Administrador

- **Enfoque**: Configuración del sistema
- **Funciones**: Supervisión y verificación de operaciones
- **Acceso**: Completo al sistema

### ✏️ 2. Usuario con permiso: Escribir

- **Enfoque**: Operaciones del sistema
- **Funciones**: Creación, edición y eliminación de operaciones
- **Acceso**: Modificación de datos

### 📋 3. Usuario con permiso: Tramitar

- **Funciones**: Supervisión y verificación de operaciones
- **Acceso**: Nivel operativo

### 👀 4. Usuario con permiso: Leer

- **Funciones**: Solo actividades de lectura
- **Acceso**: Consulta limitada

### 🔒 5. Usuario sin permisos

- **Acceso**: Muy limitado
- **Funciones**: Solo actividades comunes del sistema

---

## ⚙️ Gestión de Niveles de Acceso

La gestión de accesos se realiza mediante:

- **`usuarios.php`**
- **`permisos.php`**

> 📌 Ambos archivos corresponden al módulo system

---
