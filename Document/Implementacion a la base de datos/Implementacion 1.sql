-- 1. Crear tabla productos
CREATE TABLE IF NOT EXISTS productos (
  codigo BIGINT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  um VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Crear tabla almacen_canal_inventario_usd (MODIFICADA)
CREATE TABLE IF NOT EXISTS almacen_usd_inventario (
  producto BIGINT NOT NULL PRIMARY KEY,
  saldo_fisico DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  valor_usd DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  fecha_operacion DATE NOT NULL,
  FOREIGN KEY (producto) REFERENCES productos(codigo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 3. Crear tabla tarjetas_estiba_usd
CREATE TABLE IF NOT EXISTS almacen_usd_tarjetas_estiba (
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
ADD COLUMN E_Almacen_USD TINYINT(1) DEFAULT 0,
ADD COLUMN S_Almacen_USD TINYINT(1) DEFAULT 0,
ADD COLUMN Almacen_USD TINYINT(1) DEFAULT 0 COMMENT 'Almacén USD (1: Sí, 0: No)';

-- 6. Insertando almacenes en USD en la base de datos
INSERT INTO centros_costo (
    codigo, 
    nombre, 
    Establecimiento, 
    E_Caja_Princ, 
    S_Caja_Princ, 
    E_Caja_Panad, 
    S_Caja_Panad, 
    E_Caja_Trinid, 
    S_Caja_Trinid, 
    E_Caja_Gallet, 
    S_Caja_Gallet, 
    E_Caja_Cochi, 
    S_Caja_Cochi, 
    Modulo, 
    E_Almacen_USD, 
    S_Almacen_USD, 
    Almacen_USD
) VALUES 
-- Almacén Canal
(
    700, 
    'Almacén Canal', 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    NULL, 
    0, 
    0, 
    1
),
-- Almacén Terminal
(
    640, 
    'Almacén Terminal', 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    0, 
    NULL, 
    0, 
    0, 
    1
);

-- 7. Insertar registros en la tabla permisos
INSERT INTO permisos (
    user_id,
    centro_costo_codigo,
    permiso
) VALUES 
(1, 640, 'escribir'),
(1, 700, 'escribir'),
(10, 640, 'tramitar'),
(10, 700, 'tramitar');


-- MODIFICACIÓN: Agregar campo almacen_id a la tabla almacen_canal_inventario_usd
ALTER TABLE almacen_usd_inventario 
ADD COLUMN almacen_id INT(11) NOT NULL AFTER producto,
ADD FOREIGN KEY (almacen_id) REFERENCES centros_costo(codigo) ON DELETE RESTRICT ON UPDATE CASCADE,
DROP PRIMARY KEY,
ADD PRIMARY KEY (producto, almacen_id);

-- MODIFICACIÓN: Agregar campo almacen_id a la tabla almacen_canal_tarjetas_estiba_usd
ALTER TABLE almacen_usd_tarjetas_estiba 
ADD COLUMN almacen_id INT(11) NOT NULL AFTER producto,
ADD FOREIGN KEY (almacen_id) REFERENCES centros_costo(codigo) ON DELETE RESTRICT ON UPDATE CASCADE;

INSERT INTO productos (nombre, um) VALUES
('Grasa', 'Cubetas 15kg'),
('Sal', 'Sacos'),
('Grasa', 'Kg'),
('Nailón', 'Cono'),
('Colorante', 'Pomos'),
('Colorante', 'Galones'),
('Esencia de mantequilla', 'Pomos'),
('Bicarbonato', 'Kg');


ALTER TABLE centros_costo 
CHANGE COLUMN Establecimiento Punto_Venta TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE centros_costo 
MODIFY COLUMN Modulo tinyint(1) NULL DEFAULT NULL AFTER nombre,
MODIFY COLUMN Almacen_USD tinyint(1) NULL DEFAULT 0 COMMENT 'Almacén USD (1: Sí, 0: No)' AFTER Punto_Venta;