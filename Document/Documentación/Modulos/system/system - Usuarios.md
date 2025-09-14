# 🖥️ System (Sistema)

## 👥 Usuarios

### 📋 Descripción

1. Se utiliza para gestionar la creación y eliminacion de los usuarios del sistema.
2. Permite realizar los cambios de contraseña de los usuarios.
3. En esta pagina se asigna el rol de los usuarios.

---

### 🔐 Acceso

- 🔒 **Exclusivo** para usuarios con rol de administrador
- 🔑 Requiere autenticación válida y privilegios elevados

### 👁️ Páginas Visibles

```php

- 🐘 usuarios.php # Interfaz principal de gestión de usuarios

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
- 📊 users  # Tabla principal de usuarios
```
