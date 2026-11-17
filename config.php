<?php
/**
 * config.php — Configuración del Portal Club Pitaya
 *
 * Este archivo NO debe subirse al repositorio (.gitignore).
 * Ver config.example.php como plantilla.
 *
 * La conexión a BD la provee el Core Ligero (core/database/conexion.php),
 * que crea la variable $conn (PDO) disponible globalmente.
 */

date_default_timezone_set('America/Managua');

// Cargar conexión desde el Core Ligero (sincronizado automáticamente desde PitayaCore)
require_once __DIR__ . '/core/database/conexion.php';

// $conn (PDO) ya está disponible desde este punto en cualquier archivo que haga require_once 'config.php'
