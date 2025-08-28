-- 1. Crear tabla productos
CREATE TABLE IF NOT EXISTS productos (
  codigo BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  um VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Crear tabla almacen_canal_inventario_usd (MODIFICADA)
CREATE TABLE IF NOT EXISTS almacen_canal_inventario_usd (
  producto BIGINT NOT NULL PRIMARY KEY,
  saldo_fisico DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  valor_usd DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  fecha_operacion DATE NOT NULL,
  FOREIGN KEY (producto) REFERENCES productos(codigo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 3. Crear tabla tarjetas_estiba_usd
CREATE TABLE IF NOT EXISTS almacen_canal_tarjetas_estiba_usd (
  numero_operacion INT AUTO_INCREMENT PRIMARY KEY,
  producto BIGINT NOT NULL,
  fecha DATE NOT NULL,
  tipo_movimiento ENUM('entrada', 'salida') NOT NULL,
  cantidad_fisica DECIMAL(15,3) NOT NULL CHECK (cantidad_fisica > 0),
  valor_usd DECIMAL(15,2) NOT NULL CHECK (valor_usd > 0),
  saldo_fisico DECIMAL(15,3) NOT NULL,
  saldo_usd DECIMAL(15,2) NOT NULL,
  desde_para INT(11) NOT NULL,
  observaciones VARCHAR(255),
  FOREIGN KEY (producto) REFERENCES productos(codigo) ON DELETE RESTRICT,
  FOREIGN KEY (desde_para) REFERENCES centros_costo(codigo) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Añadir nuevas columnas a centros_costo
ALTER TABLE centros_costo 
ADD COLUMN E_A_Canal_USD TINYINT(1) DEFAULT 0,
ADD COLUMN S_A_Canal_USD TINYINT(1) DEFAULT 0;












