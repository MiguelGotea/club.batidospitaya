<?php
/**
 * ai_nutrition_api.php — API de análisis nutricional con IA
 * Portal de Miembros Club Batidos Pitaya
 *
 * Usa AIService del Core Ligero para rotación automática de keys y soporte
 * multi-proveedor (Groq, OpenAI, DeepSeek, etc.)
 *
 * Respuesta JSON: { success, analysis, hasAI, productos_analizados }
 */

// ⚠️ LiteSpeed Hostinger: session_start() antes de cualquier require
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://club.batidospitaya.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

// Verificar sesión — acepta key nueva (club_logged_in) y vieja (logged_in)
$autenticado = ($_SESSION['club_logged_in'] ?? false) || ($_SESSION['logged_in'] ?? false);
if (!$autenticado) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Conexión DB y Core AI
require_once __DIR__ . '/core/database/conexion.php'; // $conn PDO
require_once __DIR__ . '/core/ai/AIService.php';

// Input
$input = json_decode(file_get_contents('php://input'), true);
$productos_con_ingredientes = $input['productos_con_ingredientes'] ?? [];

if (empty($productos_con_ingredientes)) {
    echo json_encode(['error' => 'No se proporcionaron productos con ingredientes']);
    exit();
}

// Proveedores en orden de preferencia — se usa el primero con keys activas
const AI_PROVEEDORES = ['groq', 'google', 'openai', 'deepseek', 'cerebras', 'openrouter', 'huggingface', 'mistral'];

// --- Análisis ---
try {
    $ai       = null;
    $analisis = null;

    foreach (AI_PROVEEDORES as $proveedor) {
        $candidato = new AIService($conn, $proveedor);
        if ($candidato->hasAvailableKeys()) {
            $ai = $candidato;
            break;
        }
    }

    if ($ai) {
        $analisis = analizarConIA($ai, $productos_con_ingredientes);
    }

    $hasAI = ($analisis !== null);

    if (!$analisis) {
        $analisis = fallbackEstatico($productos_con_ingredientes);
    }

    echo json_encode([
        'success'              => true,
        'analysis'             => $analisis,
        'hasAI'                => $hasAI,
        'proveedor'            => $ai ? $ai->getProveedor() : null,
        'productos_analizados' => count($productos_con_ingredientes),
    ]);

} catch (Exception $e) {
    error_log('Error en ai_nutrition_api: ' . $e->getMessage());
    echo json_encode([
        'success'  => true,
        'analysis' => fallbackEstatico($productos_con_ingredientes),
        'hasAI'    => false,
        'fallback' => true,
        'proveedor' => null,
    ]);
}

// ---------------------------------------------------------------------------
// Funciones de análisis
// ---------------------------------------------------------------------------

/**
 * Llama AIService por cada producto y concatena los bloques HTML de respuesta.
 */
function analizarConIA(AIService $ai, array $productos): ?string
{
    $systemPrompt = 'Eres un nutricionista experto en batidos y alimentos saludables. '
        . 'Proporciona información científica precisa sin listar ingredientes; '
        . 'enfócate en beneficios generales del producto. '
        . 'Responde en español con el siguiente formato EXACTO (sin asteriscos):'
        . "\n\nBeneficios principales:\n• Punto 1\n• Punto 2\n• Punto 3"
        . "\n\nValor nutricional:\nTexto descriptivo..."
        . "\n\nRecomendaciones:\n• Recomendación 1\n• Recomendación 2"
        . "\n\nConsideraciones:\n• Consideración 1 si aplica"
        . "\n\nMáximo 300 palabras. Sé específico y científico.";

    $analisisCompleto = '';

    foreach ($productos as $item) {
        $producto     = $item['producto'];
        $ingredientes = $item['ingredientes'];

        if (empty($ingredientes)) continue;

        $lista = implode(', ', array_column($ingredientes, 'ingrediente'));
        $userPrompt = "Analiza el batido '{$producto['DBBatidos_Nombre']}' "
            . "que contiene los siguientes ingredientes: {$lista}.";

        try {
            $respuesta = $ai->procesarPrompt($systemPrompt, $userPrompt);
            $analisisCompleto .= "<div class='producto-analysis'>"
                . "<h4>🥤 " . htmlspecialchars($producto['DBBatidos_Nombre']) . "</h4>"
                . formatearRespuesta($respuesta)
                . "</div><br>";
        } catch (Exception $e) {
            error_log('AIService error para ' . $producto['DBBatidos_Nombre'] . ': ' . $e->getMessage());
        }
    }

    return $analisisCompleto ?: null;
}

/**
 * Convierte markdown básico a HTML limpio para mostrar en el dashboard.
 */
function formatearRespuesta(string $texto): string
{
    $texto = trim($texto);
    // **negrita** → <strong>
    $texto = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $texto);
    // listas con - o * o números → bullets
    $texto = preg_replace('/^[\-\*]\s+/m', '• ', $texto);
    $texto = preg_replace('/^\d+\.\s+/m', '• ', $texto);
    return nl2br($texto);
}

/**
 * Análisis estático cuando no hay keys de IA disponibles.
 * Mantiene la misma estructura HTML que el análisis con IA.
 */
function fallbackEstatico(array $productos): string
{
    $beneficiosPorIngrediente = [
        'Mango'   => ['Vitamina C', 'Antioxidantes', 'Digestión', 'Sistema inmune'],
        'Fresa'   => ['Vitamina C', 'Folato', 'Antioxidantes', 'Control de peso'],
        'Plátano' => ['Potasio', 'Energía', 'Estado de ánimo', 'Rendimiento'],
        'Papaya'  => ['Papaína', 'Digestión', 'Antiinflamatorio', 'Vitamina A'],
        'Pitaya'  => ['Antioxidantes', 'Fibra', 'Hierro', 'Detox'],
        'Leche'   => ['Calcio', 'Proteínas', 'Huesos', 'Recuperación'],
        'Yogurt'  => ['Probióticos', 'Digestión', 'Proteínas', 'Inmunidad'],
        'Avena'   => ['Fibra', 'Saciedad', 'Energía', 'Colesterol'],
        'Miel'    => ['Energía', 'Antioxidantes', 'Antibacteriano', 'Natural'],
    ];

    $html = '';

    foreach ($productos as $item) {
        $producto     = $item['producto'];
        $ingredientes = $item['ingredientes'];

        if (empty($ingredientes)) continue;

        $nutrientes = [];
        foreach ($ingredientes as $ing) {
            if (isset($beneficiosPorIngrediente[$ing['ingrediente']])) {
                $nutrientes = array_merge($nutrientes, $beneficiosPorIngrediente[$ing['ingrediente']]);
            }
        }
        $nutrientes = array_slice(array_unique($nutrientes), 0, 4);
        $nutrientesTexto = $nutrientes ? implode(', ', $nutrientes) : 'vitaminas y minerales esenciales';

        $html .= "<div class='producto-analysis'>"
            . "<h4>🥤 " . htmlspecialchars($producto['DBBatidos_Nombre']) . "</h4>"
            . "<strong>Beneficios principales:</strong><br>"
            . "• Combinación rica en antioxidantes que protegen contra el daño celular<br>"
            . "• Aporta vitaminas esenciales que fortalecen el sistema inmunológico<br>"
            . "• Proporciona energía natural y nutrientes para el bienestar diario<br>"
            . "• Ingredientes que favorecen la digestión y absorción de nutrientes<br><br>"
            . "<strong>Valor nutricional:</strong><br>"
            . "Este producto aporta: {$nutrientesTexto} y otros nutrientes esenciales.<br><br>"
            . "<strong>Recomendaciones:</strong><br>"
            . "• Ideal para consumir en la mañana o como merienda saludable<br>"
            . "• Perfecto después del ejercicio para recuperación<br>"
            . "• Acompaña con abundante agua durante el día<br><br>"
            . "<strong>Consideraciones:</strong><br>"
            . "• Forma parte de una dieta balanceada y variada<br>"
            . "• Si tienes condiciones médicas específicas, consulta con tu médico<br>"
            . "</div><br>";
    }

    return $html;
}
