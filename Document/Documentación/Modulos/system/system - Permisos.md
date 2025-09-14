# 🖥️ System (Sistema)

## 🔑 Permisos

### 📋 Descripción

Se utiliza para asignarle a los usuarios acceso a los módulos del sistema de acuerdo al rol que desempeñan en la compañia.

---

### 🔐 Acceso

- 🔒 **Exclusivo** para usuarios con rol de administrador
- 🔑 Requiere autenticación válida y privilegios elevados

### 👁️ Páginas Visibles

```php

- 🐘 permisos.php # Interfaz principal de gestión de permisos

```

### 🎮 Controladores

```php
- ⚙️ auth_admin_check.php   # Verifica permisos de administrador
- ⚙️ config.php             # Configuración de conexión a BD
```

### 🎨 Templates

```php
- 📋 header.php  # Cabecera de la aplicación
- 📋 footer.php  # Pie de página
```

### 🗃️ Tablas de Base de Datos

```php
- 📊 permisos      # Tabla principal de los permisos
- 📊 users         # Tabla principal de usuarios
- 📊 centros_costo # Tabla principal de los centros de costo
```
