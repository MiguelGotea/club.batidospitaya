<?php
// ai_nutrition_api.php - API de análisis nutricional con IA
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://club.batidospitaya.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verificar sesión
if (!($_SESSION['logged_in'] ?? false)) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$productos_con_ingredientes = $input['productos_con_ingredientes'] ?? [];

if (empty($productos_con_ingredientes)) {
    echo json_encode(['error' => 'No se proporcionaron productos con ingredientes']);
    exit();
}

// Configuración de APIs — la key se carga desde variables de entorno del servidor
$groq_api_key = $_ENV['GROQ_API_KEY'] ?? '';

$apis_config = [
    'groq' => [
        'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
        'api_key' => $groq_api_key,
        'models' => [
            'llama-3.1-8b-instant',
            'gemma2-9b-it'
        ],
        'active' => !empty($groq_api_key)
    ]
];

try {
    $analisis = null;

    // Intentar análisis con IA real
    if ($apis_config['groq']['active']) {
        $analisis = callGroqAPIWithFallback($productos_con_ingredientes, $apis_config['groq']);
    }

    // Si no hay IA disponible, usar análisis básico
    if (!$analisis) {
        $analisis = generateAdvancedBasicAnalysis($productos_con_ingredientes);
    }

    echo json_encode([
        'success' => true,
        'analysis' => $analisis,
        'hasAI' => $apis_config['groq']['active'] && $analisis !== null,
        'productos_analizados' => count($productos_con_ingredientes)
    ]);

} catch (Exception $e) {
    error_log("Error en AI API: " . $e->getMessage());

    $analisis_fallback = generateAdvancedBasicAnalysis($productos_con_ingredientes);

    echo json_encode([
        'success' => true,
        'analysis' => $analisis_fallback,
        'fallback' => true,
        'hasAI' => false
    ]);
}

function callGroqAPIWithFallback($productos_con_ingredientes, $config)
{
    $prompts = createProductPrompts($productos_con_ingredientes);

    foreach ($config['models'] as $model) {
        try {
            $analisis_completo = '';

            foreach ($prompts as $prompt_data) {
                $response = callSingleGroqAPI($prompt_data['prompt'], $config['api_key'], $model);
                $analisis_completo .= "<div class='producto-analysis'>";
                $analisis_completo .= "<h4>🥤 " . $prompt_data['producto_nombre'] . "</h4>";
                $analisis_completo .= $response;
                $analisis_completo .= "</div><br>";
            }

            return $analisis_completo;

        } catch (Exception $e) {
            error_log("Error con modelo $model: " . $e->getMessage());
            continue;
        }
    }

    throw new Exception('Todos los modelos de Groq fallaron');
}

function callSingleGroqAPI($prompt, $api_key, $model)
{
    $context = "Eres un nutricionista experto especializado en batidos saludables y análisis nutricional. Proporciona información científica precisa sin listar ingredientes, enfócate en beneficios generales del producto.";

    $data = [
        'messages' => [
            ['role' => 'system', 'content' => $context],
            ['role' => 'user', 'content' => $prompt]
        ],
        'model' => $model,
        'max_tokens' => 600,
        'temperature' => 0.7
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Error de conexión con Groq API');
    }

    if ($http_code !== 200) {
        throw new Exception("Error HTTP $http_code");
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return formatAIResponse($result['choices'][0]['message']['content']);
    }

    throw new Exception('Respuesta inválida de Groq API');
}

function createProductPrompts($productos_con_ingredientes)
{
    $prompts = [];

    foreach ($productos_con_ingredientes as $item) {
        $producto = $item['producto'];
        $ingredientes = array_column($item['ingredientes'], 'ingrediente');

        if (empty($ingredientes)) {
            continue;
        }

        $ingredientes_texto = implode(', ', $ingredientes);

        $prompt = "Analiza el valor nutricional y beneficios de salud del producto '{$producto['DBBatidos_Nombre']}' que contiene los siguientes ingredientes: {$ingredientes_texto}.

POR FAVOR USA ESTE FORMATO EXACTO SIN **:

Beneficios principales:
• Punto 1
• Punto 2
• Punto 3

Valor nutricional:
Texto descriptivo aquí...

Recomendaciones:
• Recomendación 1
• Recomendación 2

Consideraciones:
• Consideración 1 si aplica

Máximo 300 palabras. Sé específico y científico.";

        $prompts[] = [
            'prompt' => $prompt,
            'producto_nombre' => $producto['DBBatidos_Nombre']
        ];
    }

    return $prompts;
}

function generateAdvancedBasicAnalysis($productos_con_ingredientes)
{
    $beneficios_db = [
        'Mango' => ['Vitamina C', 'Antioxidantes', 'Digestión', 'Sistema inmune'],
        'Fresa' => ['Vitamina C', 'Folato', 'Antioxidantes', 'Control de peso'],
        'Plátano' => ['Potasio', 'Energía', 'Estado de ánimo', 'Rendimiento'],
        'Papaya' => ['Papaína', 'Digestión', 'Antiinflamatorio', 'Vitamina A'],
        'Pitaya' => ['Antioxidantes', 'Fibra', 'Hierro', 'Detox'],
        'Leche' => ['Calcio', 'Proteínas', 'Huesos', 'Recuperación'],
        'Yogurt' => ['Probióticos', 'Digestión', 'Proteínas', 'Inmunidad'],
        'Avena' => ['Fibra', 'Saciedad', 'Energía', 'Colesterol'],
        'Miel' => ['Energía', 'Antioxidantes', 'Antibacteriano', 'Natural']
    ];

    $analisis_completo = '';

    foreach ($productos_con_ingredientes as $item) {
        $producto = $item['producto'];
        $ingredientes = $item['ingredientes'];

        if (empty($ingredientes)) {
            continue;
        }

        $beneficios_producto = [];

        foreach ($ingredientes as $ing) {
            $nombre_ingrediente = $ing['ingrediente'];
            if (isset($beneficios_db[$nombre_ingrediente])) {
                $beneficios_producto = array_merge($beneficios_producto, $beneficios_db[$nombre_ingrediente]);
            }
        }

        $beneficios_unicos = array_unique($beneficios_producto);
        $nutrientes_principales = array_slice($beneficios_unicos, 0, 4);

        $analisis_completo .= "<div class='producto-analysis'>";
        $analisis_completo .= "<h4>🥤 " . htmlspecialchars($producto['DBBatidos_Nombre']) . "</h4>";
        $analisis_completo .= "<strong>Beneficios principales:</strong><br>";
        $analisis_completo .= "• Combinación rica en antioxidantes que protegen contra el daño celular<br>";
        $analisis_completo .= "• Aporta vitaminas esenciales que fortalecen el sistema inmunológico<br>";
        $analisis_completo .= "• Proporciona energía natural y nutrientes para el bienestar diario<br>";
        $analisis_completo .= "• Ingredientes que favorecen la digestión y absorción de nutrientes<br><br>";
        $analisis_completo .= "<strong>Valor nutricional:</strong><br>";
        $analisis_completo .= "Este producto aporta: " . implode(', ', $nutrientes_principales) . " y otros nutrientes esenciales para el organismo.<br><br>";
        $analisis_completo .= "<strong>Recomendaciones:</strong><br>";
        $analisis_completo .= "• Ideal para consumir en la mañana o como merienda saludable<br>";
        $analisis_completo .= "• Perfecto después del ejercicio para recuperación<br>";
        $analisis_completo .= "• Acompaña con abundante agua durante el día<br><br>";
        $analisis_completo .= "<strong>Consideraciones:</strong><br>";
        $analisis_completo .= "• Forma parte de una dieta balanceada y variada<br>";
        $analisis_completo .= "• Si tienes condiciones médicas específicas, consulta con tu médico<br>";
        $analisis_completo .= "</div><br>";
    }

    return $analisis_completo;
}

function formatAIResponse($text)
{
    $text = trim($text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

    $lines = explode("\n", $text);
    $formatted_lines = [];

    foreach ($lines as $line) {
        $trimmed_line = trim($line);

        if (
            preg_match('/^<strong>.*<\/strong>:$/', $trimmed_line) ||
            preg_match('/^<strong>.*<\/strong>$/', $trimmed_line)
        ) {
            $formatted_lines[] = $trimmed_line;
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed_line, $matches)) {
            $formatted_lines[] = '• ' . $matches[1];
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trimmed_line, $matches)) {
            $formatted_lines[] = '• ' . $matches[1];
            continue;
        }

        $formatted_lines[] = $trimmed_line;
    }

    $text = implode("\n", $formatted_lines);
    $text = nl2br($text);

    return $text;
}
?>