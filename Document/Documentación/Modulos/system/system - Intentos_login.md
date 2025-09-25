# 🖥️ System (Sistema)

## 🚫 intentos_login

### 📋 Descripción

1. Se utiliza como medida de seguridad para evitar ataques de fuerza bruta ddos contra el sitio.
2. Permite ver una tabla con los intentos de acceder al sitio infructuosos.

---

### 🔐 Acceso

- 🔒 **Exclusivo** para usuarios con rol de administrador
- 🔑 Requiere autenticación válida y privilegios elevados

### 👁️ Páginas Visibles

```php

- 🐘 intentos_login.php # Interfaz principal de los Ips

```

### 🎮 Controladores

```php
- ⚙️ auth_admin_check.php   # Verifica permisos de administrador
- ⚙️ config.php            # Configuración de conexión a BD
```

### 🎨 Templates

```php
- 📋 header.php  # Cabecera de la aplicación
- 📋 footer.php  # Pie de página
```

### 🗃️ Tablas de Base de Datos

```php
- 📊 intentos_login  # Tabla que muestras los intentos fallidos
```
