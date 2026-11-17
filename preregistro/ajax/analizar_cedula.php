<?php
/**
 * preregistro/ajax/analizar_cedula.php
 * Recibe imagen de cédula nicaragüense en base64 y retorna datos extraídos vía Gemini Vision.
 *
 * POST:
 *   t       — token HMAC firmado (verifica membresía)
 *   imagen  — base64 de la imagen (sin prefijo data:image/...)
 *   mime    — mime type (image/jpeg | image/png | image/webp)
 *
 * Respuesta JSON:
 *   {success, datos: {nombre, apellido, cedula, fecha_nacimiento: "YYYY-MM-DD"}}
 */

require_once __DIR__ . '/../../core/database/conexion.php';
require_once __DIR__ . '/../../core/config/club_token_secret.php';
require_once __DIR__ . '/../../core/ai/AIService.php';

header('Content-Type: application/json');

// ── Validar token ──────────────────────────────────────────────────────────────
$tokenRaw  = isset($_POST['t']) ? trim($_POST['t']) : '';
$membresia = $tokenRaw ? clubTokenVerificar($tokenRaw) : null;

if (!$membresia) {
    echo json_encode(['success' => false, 'mensaje' => 'Token inválido.']);
    exit;
}

// ── Validar imagen ─────────────────────────────────────────────────────────────
$imagenB64 = isset($_POST['imagen']) ? trim($_POST['imagen']) : '';
$mime      = isset($_POST['mime'])   ? trim($_POST['mime'])   : 'image/jpeg';

$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $mimesPermitidos)) {
    $mime = 'image/jpeg';
}

if (empty($imagenB64)) {
    echo json_encode(['success' => false, 'mensaje' => 'No se recibió imagen.']);
    exit;
}

// Validar que sea base64 válido
if (base64_decode($imagenB64, true) === false) {
    echo json_encode(['success' => false, 'mensaje' => 'Imagen con formato inválido.']);
    exit;
}

// ── Prompt para Gemini Vision ─────────────────────────────────────────────────
$systemPrompt = <<<PROMPT
Eres un asistente especializado en extraer datos de cédulas de identidad nicaragüenses.

ESTRUCTURA DE LA CÉDULA NICARAGÜENSE:
El número de cédula tiene el formato: NNN-DDMMAA-NNNNL
  - Primeros 3 dígitos: código de municipio
  - Siguientes 6 dígitos: fecha de nacimiento en formato DDMMAA (día, mes, año)
  - Siguientes 4 dígitos: consecutivo
  - Último carácter: letra verificadora

TAREA:
Analiza la imagen de cédula de identidad nicaragüense y extrae exactamente:
1. nombre: Solo el primer nombre (en mayúsculas)
2. apellido: Solo el primer apellido (en mayúsculas)
3. cedula: El número completo en formato exacto de la cédula (con guiones si aplica)
4. fecha_nacimiento: La fecha de nacimiento en formato YYYY-MM-DD (extráela del número de cédula si está visible, o del campo de fecha si aparece impresa)

RESPONDE ÚNICAMENTE con JSON válido, sin texto adicional, sin markdown:
{"nombre":"...","apellido":"...","cedula":"...","fecha_nacimiento":"YYYY-MM-DD"}

Si no puedes extraer algún campo con certeza, usa null para ese campo.
Si la imagen no es una cédula nicaragüense, responde: {"error":"No es una cédula nicaragüense válida"}
PROMPT;

$userPrompt = "Analiza esta cédula de identidad nicaragüense y extrae los datos solicitados en formato JSON.";

// ── Llamar a IA con fallback automático (google → openai → groq) ──────────────
$extraParts = [
    [
        'inline_data' => [
            'mime_type' => $mime,
            'data'      => $imagenB64
        ]
    ]
];

// Proveedores con soporte Vision, en orden de preferencia
$proveedores = [
    'google' => 'gemini-2.0-flash',
    'openai' => 'gpt-4o-mini',
    'groq'   => 'llama-3.2-90b-vision-preview',
];

$respuesta    = null;
$errores      = [];

foreach ($proveedores as $prov => $modelo) {
    try {
        $ai = new AIService($conn, $prov);
        $ai->setModel($modelo);

        $respuesta = $ai->procesarPrompt($systemPrompt, $userPrompt, 0.1, $extraParts);
        if ($respuesta) break; // Éxito — salir del loop
    } catch (Exception $e) {
        $errores[] = strtoupper($prov) . ': ' . $e->getMessage();
        error_log('analizar_cedula [' . $prov . '] ERROR: ' . $e->getMessage());
        continue; // Intentar siguiente proveedor
    }
}

if (!$respuesta) {
    error_log('analizar_cedula — todos los proveedores fallaron: ' . implode(' | ', $errores));
    echo json_encode(['success' => false, 'mensaje' => 'Error al analizar la imagen. Intenta nuevamente.']);
    exit;
}

// Limpiar markdown si la IA lo devuelve entre ```json ... ```
$respuesta = preg_replace('/^```(?:json)?\s*/i', '', trim($respuesta));
$respuesta = preg_replace('/\s*```$/', '', $respuesta);

$datos = json_decode($respuesta, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
    echo json_encode(['success' => false, 'mensaje' => 'No se pudo interpretar la respuesta de IA.']);
    exit;
}

if (isset($datos['error'])) {
    echo json_encode(['success' => false, 'mensaje' => $datos['error']]);
    exit;
}

// Normalizar fecha: si viene como DD/MM/YYYY o similares, convertir a YYYY-MM-DD
if (!empty($datos['fecha_nacimiento']) && $datos['fecha_nacimiento'] !== null) {
    $fec = $datos['fecha_nacimiento'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fec)) {
        $ts = strtotime($fec);
        if ($ts !== false) {
            $fec = date('Y-m-d', $ts);
        }
    }
    $datos['fecha_nacimiento'] = $fec;
}

echo json_encode([
    'success' => true,
    'datos'   => [
        'nombre'           => isset($datos['nombre'])           ? strtoupper(trim($datos['nombre']))    : null,
        'apellido'         => isset($datos['apellido'])         ? strtoupper(trim($datos['apellido']))  : null,
        'cedula'           => isset($datos['cedula'])           ? trim($datos['cedula'])                 : null,
        'fecha_nacimiento' => isset($datos['fecha_nacimiento']) ? $datos['fecha_nacimiento']             : null,
    ]
]);
