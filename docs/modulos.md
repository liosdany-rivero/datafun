---
layout: default
title: Módulos del Sistema
nav_order: 4
---

# Módulos del Sistema

## 🔐 Módulo de Sistema

**Archivos principales:**

- `views/system/login.php` - Autenticación
- `views/system/dashboard.php` - Panel control
- `views/system/usuarios.php` - Gestión usuarios

**Controladores relacionados:**

- `controllers/auth_admin_check.php`
- `controllers/auth_user_check.php`
- `controllers/check_username.php`

## 📦 Módulo de Almacenes USD

**Funcionalidades:**

- Dashboard de almacenes
- Control de inventario
- Gestión de tarjetas de estiba

**Archivos:**

- `views/almacen/almacen_usd_dashboard.php`
- `views/almacen/almacen_usd_inventario.php`
- `views/almacen/almacen_usd_tarjetas_estiba.php`

## 💰 Módulo de Cajas (6 Cajas)

### Caja Principal

- `views/caja/caja_principal_dashboard.php`
- `views/caja/caja_principal_operaciones.php`

### Cajas Especializadas

- **Cochiquera, Galletera, Panadería, Trinidad**
- Cada una con: Dashboard, Flujo, Movimientos, Operaciones

**Archivo común:**

- `views/caja/contador_dinero.php` - Conteo de efectivo

## 📊 Módulo de Tasas

**Control de tipos de cambio:**

- `views/tasa/tasas.php` - Gestión de tasas
- `controllers/get_tasa.php` - Obtención de tasas

## 🏷️ Módulo de Catálogos

**Gestion de productos:**

- `views/catalogo/productos.php` - Catálogo productos
