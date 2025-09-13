# 🖥️ System (Sistema)

## 📦 Respaldo de Base de Datos (Respaldar BD)

### 📋 Descripción

Función utilizada para descargar una copia de seguridad compactada de la base de datos del sistema.

---

### 🔐 Acceso

- 🔒 **Exclusivo** para usuarios con rol de administrador
- 🔑 Requiere autenticación válida y privilegios elevados

### 👁️ Páginas Visibles

- ❌ No incluye interfaces visuales para el usuario final

### 🎮 Controladores

```php
- exportar_bd.php       # Genera y descarga el backup
- auth_admin_check.php   # Verifica permisos de administrador
- config.php            # Configuración de conexión a BD
```

### 🎨 Templates

- 🚫 No utiliza plantillas de frontend

### 🗃️ Tablas de Base de Datos

- 🔄 Opera sobre todas las tablas del sistema

- 📊 No tiene tablas exclusivas asociadas
