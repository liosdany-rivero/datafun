# 🖥️ System (Sistema)

## 💰 Centros de costo

### 📋 Descripción

Se utiliza para gestionar la creación, edición y eliminacion de los centros de costo del sistema.

---

### 🔐 Acceso

- 🔒 **Exclusivo** para usuarios con rol de administrador
- 🔑 Requiere autenticación válida y privilegios elevados

### 👁️ Páginas Visibles

```php
- centros_costo.php # Interfaz principal de gestión de centros de costo
```

### 🎮 Controladores

```php
- auth_admin_check.php   # Verifica permisos de administrador
- config.php            # Configuración de conexión a BD
```

### 🎨 Templates

```php
- header.php  # Cabecera de la aplicación
- footer.php  # Pie de página
```

### 🗃️ Tablas de Base de Datos

```php
- 📊 centros_costo  # Tabla principal de los centros de costo
```
