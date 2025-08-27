-- 3. Crear tabla productos
CREATE TABLE IF NOT EXISTS productos (
  codigo BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  um VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 2. Crear tabla almacenes_usd
CREATE TABLE IF NOT EXISTS almacenes_usd (
  codigo INT(11) PRIMARY KEY,
  activo BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (codigo) REFERENCES centros_costo(codigo) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



------- REVIZAR ------


-- 1. Añadir nuevas columnas a centros_costo
ALTER TABLE centros_costo 
ADD COLUMN E_Alm_USD TINYINT(1) DEFAULT 0,
ADD COLUMN S_Alm_USD TINYINT(1) DEFAULT 0;



-- 4. Crear tabla tarjetas_estiba_usd
CREATE TABLE IF NOT EXISTS tarjetas_estiba_usd (
  numero_operacion INT AUTO_INCREMENT PRIMARY KEY,
  almacen INT(11) NOT NULL,
  producto BIGINT NOT NULL,
  fecha_operacion DATE NOT NULL,
  tipo_movimiento ENUM('entrada', 'salida') NOT NULL,
  cantidad_fisica DECIMAL(15,3) NOT NULL CHECK (cantidad_fisica > 0),
  valor_usd DECIMAL(15,2) NOT NULL CHECK (valor_usd > 0),
  saldo_fisico DECIMAL(15,3) NOT NULL,
  saldo_usd DECIMAL(15,2) NOT NULL,
  desde_para INT(11) NOT NULL,
  observaciones VARCHAR(255),
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  usuario_registro VARCHAR(50),
  FOREIGN KEY (almacen) REFERENCES almacenes_usd(codigo) ON DELETE RESTRICT,
  FOREIGN KEY (producto) REFERENCES productos(codigo) ON DELETE RESTRICT,
  FOREIGN KEY (desde_para) REFERENCES centros_costo(codigo) ON DELETE RESTRICT,
  INDEX idx_almacen_producto (almacen, producto),
  INDEX idx_fecha_operacion (fecha_operacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Crear tabla inventario_actual_usd
CREATE TABLE IF NOT EXISTS inventario_actual_usd (
  id INT AUTO_INCREMENT PRIMARY KEY,
  almacen INT(11) NOT NULL,
  producto BIGINT NOT NULL,
  saldo_fisico DECIMAL(15,3) NOT NULL DEFAULT 0 CHECK (saldo_fisico >= 0),
  valor_usd DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (valor_usd >= 0),
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (almacen) REFERENCES almacenes_usd(codigo) ON DELETE RESTRICT,
  FOREIGN KEY (producto) REFERENCES productos(codigo) ON DELETE RESTRICT,
  UNIQUE KEY uk_almacen_producto (almacen, producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

