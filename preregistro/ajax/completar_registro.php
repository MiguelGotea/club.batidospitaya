<?php
/**
 * preregistro/ajax/completar_registro.php
 * Guarda el perfil completo del socio (nombre, apellido, cedula, fecha_nacimiento, correo).
 *
 * POST JSON: {t, nombre, apellido, cedula, fecha_nacimiento, correo}
 *
 * Solo actualiza si el socio AÚN no tiene nombre (evita sobreescrituras).
 */

require_once __DIR__ . '/../../core/database/conexion.php';
require_once __DIR__ . '/../../core/config/club_token_secret.php';

header('Content-Type: application/json');

// ── Leer body JSON ─────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    // Fallback: intentar $_POST
    $body = $_POST;
}

// ── Validar token ──────────────────────────────────────────────────────────────
$tokenRaw  = isset($body['t']) ? trim($body['t']) : '';
$membresia = $tokenRaw ? clubTokenVerificar($tokenRaw) : null;

if (!$membresia) {
    echo json_encode(['success' => false, 'mensaje' => 'Token inválido.']);
    exit;
}

// ── Sanitizar campos ───────────────────────────────────────────────────────────
$nombre        = isset($body['nombre'])           ? strtoupper(trim($body['nombre']))          : '';
$apellido      = isset($body['apellido'])         ? strtoupper(trim($body['apellido']))        : '';
$cedula        = isset($body['cedula'])           ? trim($body['cedula'])                       : '';
$fechaNac      = isset($body['fecha_nacimiento']) ? trim($body['fecha_nacimiento'])             : '';
$correo        = isset($body['correo'])           ? strtolower(trim($body['correo']))           : '';

// ── Validaciones básicas ───────────────────────────────────────────────────────
if (!$nombre || !$apellido || !$cedula || !$fechaNac) {
    echo json_encode(['success' => false, 'mensaje' => 'Faltan datos obligatorios (nombre, apellido, cédula, fecha de nacimiento).']);
    exit;
}

// Validar formato fecha YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
    echo json_encode(['success' => false, 'mensaje' => 'Formato de fecha inválido.']);
    exit;
}

// Validar correo si se proporcionó
if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'mensaje' => 'El correo electrónico no es válido.']);
    exit;
}

// ── Verificar que el socio existe y aún no ha completado el perfil ─────────────
try {
    $stmtCheck = $conn->prepare(
        "SELECT id_clienteclub, nombre FROM clientesclub WHERE membresia = :m LIMIT 1"
    );
    $stmtCheck->execute([':m' => $membresia]);
    $socio = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$socio) {
        echo json_encode(['success' => false, 'mensaje' => 'Membresía no encontrada.']);
        exit;
    }

    if (!empty($socio['nombre'])) {
        // Ya completado — OK igualmente
        echo json_encode(['success' => true, 'mensaje' => 'Tu perfil ya estaba completado. ¡Bienvenido al Club! 💚']);
        exit;
    }

    // ── UPDATE del perfil ──────────────────────────────────────────────────────
    $stmtUpd = $conn->prepare(
        "UPDATE clientesclub
         SET nombre          = :nom,
             apellido        = :ape,
             cedula          = :ced,
             fecha_nacimiento = :fec,
             correo          = :cor
         WHERE membresia = :m
           AND (nombre IS NULL OR nombre = '')
         LIMIT 1"
    );
    $stmtUpd->execute([
        ':nom' => $nombre,
        ':ape' => $apellido,
        ':ced' => $cedula,
        ':fec' => $fechaNac,
        ':cor' => $correo ?: null,
        ':m'   => $membresia,
    ]);
    $afectadas = $stmtUpd->rowCount();

    if ($afectadas > 0) {
        echo json_encode([
            'success' => true,
            'mensaje' => "¡Perfil completado con éxito! Bienvenido al Club Pitaya, {$nombre}. 💚"
        ]);
    } else {
        // Ya fue actualizado por otra petición concurrente
        echo json_encode(['success' => true, 'mensaje' => '¡Perfil guardado correctamente!']);
    }

} catch (Exception $e) {
    error_log('completar_registro ERROR: ' . $e->getMessage());
    echo json_encode(['success' => false, 'mensaje' => 'Error al guardar el perfil. Intenta nuevamente.']);
}
