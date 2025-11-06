---
layout: default
title: API y Endpoints
nav_order: 5
---

# Endpoints del Sistema

## 🔐 Autenticación

### Verificar Administrador

**Archivo:** `controllers/auth_admin_check.php`
**Uso:** Verifica si usuario actual es administrador

### Verificar Usuario

**Archivo:** `controllers/auth_user_check.php`
**Uso:** Verifica autenticación de usuario regular

### Validar Usuario

**Archivo:** `controllers/check_username.php`
**Uso:** Verifica disponibilidad de nombre de usuario

## 📊 Datos y Consultas

### Obtener Tasas

**Archivo:** `controllers/get_tasa.php`
**Función:** Retorna tasas de cambio actuales

### Datos de Almacén

**Archivos:**

- `controllers/get_ultimo_registro_almacen_usd.php`
- `controllers/get_registro_anterior_almacenes_usd.php`
- `controllers/get_registro_tarjeta_estiba_almacen_usd.php`

## 🛠️ Utilidades

### Exportar Base de Datos

**Archivo:** `controllers/exportar_bd.php`
**Función:** Genera backup de la base de datos

### Logs del Sistema

**Archivo:** `logs/php_login_errors.log`
**Contenido:** Registro de errores de autenticación
