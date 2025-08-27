PROMP 1

Estoy desarrollando una aplicación web con php puro, html, css, y js.
Quisiera agregar a mi aplicación un módulo al que llamaré almacen_usd que me permita controlar los inventarios de mis almacenes en usd. Mi empresa dispone de varios almacenes en los cuales se guardan diferentes productos que controlo en dólares americanos (USD).

Por cada producto se hace una tarjeta de estiba que recoge los datos siguientes:

- Nombre del almacen (es el nombre del almacen, se tienen varios establecimientos).
- Nombre del producto (es el nombre del producto, un almacen puede guardar de 1 a muchos productos).
- Unidad de medida (es la unidad de medida en que se encuentra contabilizado el producto).
- Fecha de la operación (aquí se anota la fecha en que ocurrió la operación de entrada o de salida).
- Cantidad de entrada en físico (es un número decimal mayor que cero).
- Valor entrada en USD (es un número decimal mayor que cero)
- Cantidad de salida en físico (es un número decimal mayor que cero).
- Valor de la salida en USD (es un número decimal mayor que cero).
- Saldo existente en físico (se amacena el valor acumulado que se obtiene de la operación matemática resultante de tomar el saldo existente en físico del registro anterior más la cantidad entrada en físico menos la cantidad salida en físico).
- saldo existente en USD (se amacena el valor acumulado que se obtiene de la operación matemática resultante de tomar el saldo existente en físico del registro anterior más la cantidad entrada en físico menos la cantidad salida en físico),
- Desde-Para (aquí de coloca el código de la tabla centros_costo de la base de datos).
- Observaciones (Se recoge un texto con un tamaño máximo de 255 caracteres).

La tabla centros_costo de la base de datos tiene la siguiente estructura en la base de datos:

{
"centros_costo": {
"codigo": {
"tipo": "int(11)",
"clave_primaria": true,
"nulo": false,
"predeterminado": null,
"comentario": null
},
"nombre": {
"tipo": "varchar(25)",
"cotejamiento": "latin1_swedish_ci",
"nulo": false,
"predeterminado": null,
"comentario": null
},
"Establecimiento": {
"tipo": "tinyint(1)",
"nulo": false,
"predeterminado": 0,
"comentario": null
},
"E_Caja_Princ": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Entrada Caja Principal"
},
"S_Caja_Princ": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Salida Caja Principal"
},
"E_Caja_Panad": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Entrada Caja Panadería"
},
"S_Caja_Panad": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Salida Caja Panadería"
},
"E_Caja_Trinid": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Entrada Caja Trinidad"
},
"S_Caja_Trinid": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Salida Caja Trinidad"
},
"E_Caja_Gallet": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Entrada Caja Galletera"
},
"S_Caja_Gallet": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Salida Caja Galletera"
},
"E_Caja_Cochi": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Entrada Caja Cochiquera"
},
"S_Caja_Cochi": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": 0,
"comentario": "Salida Caja Cochiquera"
},
"Modulo": {
"tipo": "tinyint(1)",
"nulo": true,
"predeterminado": null,
"comentario": null
}
}
}

A la tabla centros_costo quisiera añadirle dos columnas booleanas llamadas E_Alm_USD y S_Alm_USD para que solo se puedan utilizar en el modulo que quiero crear los centros de costo que tenga marcados como verdadero.

Actualmente tengo dos almacenes donde controlo productos de esta forma los almacenes se llaman. Almacén Canal USD y Almacen Terminal USD, pero es posible que en el futuro cercano continue creando más almacenes. Créame un script sql que me permita crear una tabla en la base de datos llamada almacenes_usd donde yo pueda registrar todos los almacenes de este tipo. Quiero que la tabla almacenes_usd tenga los siguientes campos:

- codigo (este será la llave primaria de la tabla y tomará los datos del código de la tabla centros_costo).
- activo (será un booleano)

Tambien quisiera crear una tabla llamada productos que tenga los siguientes campos:

- codigo (este será la llave primaria no será auto incrementable, se almacenará un numero entero largo)
- nombre (se almacenará el nombre del producto, será de entrada requerido)
- um (se almacenara la unidad de medida será requerido)
- activo (será un campo booleano)

Tambien se creará una tabla llamada tarjetas_estiba_usd la cual tendrá los siguientes campos:

- numero_operacion (el cual será auto incrementable y la clave primaria)
- almacen (el cual almacenará el código de la tabla almacenes_usd será requerido)
- producto (el cual almacenará el código de la tabla producto será requerido)
- fecha_operacion (recogerá la fecha en que se realizó la operación y será requerido)
- tipo_movimiento (el cual será o entrada o salida, siendo requerido)
- cantidad_fisica (será un numero real positivo y será requerido)
- valor_usd (será un numero decimal positivo y será requerido)
- saldo_fisico (será un numero real, el resultado del calculo matemático de al saldo_fisico del registro anterior sumarle la cantidad_fisica si el tipo_movimiento es una entrada o restarle la cantidad_fisica si el tipo_movimiento es una salida)
- saldo_usd (será un numero real, el resultado del calculo matemático de al saldo_usd del registro anterior sumarle el valor_usd si el tipo_movimiento es una entrada o restarle la cantidad_fisica si el tipo_movimiento es una salida)
- desde_para (se almacenará el codigo de la tabla centros_costo, este campo será requerido y servirá para saber el origen o el destino del movimiento del producto en dependencia si es una entrada o una salida)
- observaciones (guardará un texto que tendrá como máximo 255 caracteres)

Tambien quisiera crear una tabla llamada inventario_actual_usd que tendrá los siguientes campos:

- id (será auto incrementable y la llave primara de la aplicación), almacen (almacenará el codigo de la tabla almacenes_usd y será de uso requerido), producto (el cual almacenará el código de la tabla producto será requerido) saldo_fisico (será un número real positivo y será requerido), valor_usd (será un numero decimal positivo y será requerido)
  Estas son todas las tablas que necesito agregar a mi base de datos la cual es maría db.
  Además de crearme los script sql quisiera que me desarrolles un cronograma con todo lo demás que se requiere para implementar este nuevo módulo en mi aplicación, ponme todos los detalles con sus explicaciones en un promt bien elaborado que me sirva para que en el futuro una ia me pueda ir implementando dicho cronograma por pasos. Pregúntame si necesitas mas información.

TABLAS
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

Hazme un resumen con todo lo que quiero desarrollar y creame un cronograma paso a paso sin dejar pasar por alto ningun detalle que me permita implementar dicha solucion en mi sitio web. Hazlo para que en un futuro le presente el cronogframa a una ia que no sepa nada de mi proyecto y ella me pueda continuar implementando el mismo por eso es importante que no me dejes ningun dato de los que te presente sin poner en el resumen. Preguntame si no entiendes algo o si necesitas mas informacion.
