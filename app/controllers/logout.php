<?php

/**
 * ARCHIVO: logout.php
 * 
 * DESCRIPCIÓN:
 * Este script normalmente maneja el cierre de sesión de usuarios:
 * 1. Inicia la sesión
 * 2. Destruye los datos de sesión
 * 3. Redirige al formulario de login
 * 
 */


session_start();
session_destroy();
header("Location: ../views/system/login.php");
exit();
