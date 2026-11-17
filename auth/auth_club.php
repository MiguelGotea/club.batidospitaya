<?php
/**
 * auth_club.php — Verificación de sesión para miembros del Club Pitaya
 *
 * Incluir al inicio de cada página protegida:
 *   require_once __DIR__ . '/../auth/auth_club.php';
 *
 * NO usar auth.php del core — ese es para empleados internos del ERP.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que la sesión de club esté activa
if (!($_SESSION['club_logged_in'] ?? false)) {
    // Guardar la URL que intentó acceder para redirigir después del login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/../login.php');
    exit();
}

// Regenerar ID de sesión periódicamente para prevenir session fixation
// Se regenera una vez por hora
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Datos del miembro disponibles globalmente después de este include:
// $_SESSION['club_logged_in']  → true
// $_SESSION['club_membresia']  → número de membresía
// $_SESSION['club_nombre']     → nombre del miembro
// $_SESSION['club_apellido']   → apellido del miembro
