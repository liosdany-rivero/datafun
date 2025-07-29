<?php

/*
***************************************************************
   ESTO ES PARA TRABAJAR EN LOCAL
***************************************************************/


$servername = "localhost";
$username = "root";
$password = ""; // Si configuraste una contraseña en XAMPP, colócala aquí
$database = "if0_39447217_auth_db"; // Asegúrate de que esta base exista en phpMyAdmin

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  error_log("Conexión fallida: " . $conn->connect_error);
  die("No se pudo conectar a la base de datos.");
}

/*



***************************************************************
   ESTO ES PARA CUANDO SE SUBA A PRODUCCION EN INFINITE FREE
*************************************************************** *

$servername = "sql302.infinityfree.com"; // Tu servidor MySQL
$username = "if0_39447217";       // Tu usuario MySQL
$password = "datafun1";       // Tu contraseña
$database = "if0_39447217_auth_db"; // Tu base de datos

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  // Puedes loguear el error en vez de mostrarlo directamente
  error_log("Conexión fallida: " . $conn->connect_error);
  die("No se pudo conectar a la base de datos.");
}


/**/