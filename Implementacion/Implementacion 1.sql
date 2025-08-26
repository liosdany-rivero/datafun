-- 1. Añadir nuevas columnas a centros_costo
ALTER TABLE centros_costo 
ADD COLUMN E_Alm_USD TINYINT(1) DEFAULT 0,
ADD COLUMN S_Alm_USD TINYINT(1) DEFAULT 0;

-- 2. Crear tabla almacenes_usd
CREATE TABLE almacenes_usd (
    codigo INT(11) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (codigo) REFERENCES centros_costo(codigo)
);

-- 3. Crear tabla productos
CREATE TABLE productos (
    codigo BIGINT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    um VARCHAR(20) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- 4. Crear tabla tarjetas_estiba_usd
CREATE TABLE tarjetas_estiba_usd (
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
    FOREIGN KEY (almacen) REFERENCES almacenes_usd(codigo),
    FOREIGN KEY (producto) REFERENCES productos(codigo),
    FOREIGN KEY (desde_para) REFERENCES centros_costo(codigo)
);

-- 5. Crear tabla inventario_actual_usd
CREATE TABLE inventario_actual_usd (
    id INT AUTO_INCREMENT PRIMARY KEY,
    almacen INT(11) NOT NULL,
    producto BIGINT NOT NULL,
    saldo_fisico DECIMAL(15,3) NOT NULL CHECK (saldo_fisico >= 0),
    valor_usd DECIMAL(15,2) NOT NULL CHECK (valor_usd >= 0),
    FOREIGN KEY (almacen) REFERENCES almacenes_usd(codigo),
    FOREIGN KEY (producto) REFERENCES productos(codigo),
    UNIQUE KEY (almacen, producto)
);