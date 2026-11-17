<?php
/**
 * config.example.php — Plantilla de configuración (SIN credenciales)
 *
 * Copiar como config.php y completar con las credenciales reales.
 * config.php está en .gitignore — este archivo sí va al repositorio.
 *
 * La conexión real vive en core/database/conexion.php (Core Ligero).
 * Allí se definen: $servername, $username, $password, $dbname, $conn.
 */

date_default_timezone_set('America/Managua');

require_once __DIR__ . '/core/database/conexion.php';
// $conn (PDO) disponible después de este require
