<?php
/**
 * _diagnostico.php — SOLO USO TEMPORAL — ELIMINAR DESPUÉS DE DEPURAR
 * Sube este archivo a Hostinger, ábrelo en el navegador, y comparte el resultado.
 * URL: https://club.batidospitaya.com/_diagnostico.php
 */

// No mostrar en producción si alguien lo deja olvidado — protección básica
$allowed_ips = ['127.0.0.1', '::1']; // Agrega tu IP aquí si quieres restringir
// (lo dejamos abierto para que puedas verlo fácilmente)

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#1e1e1e;color:#d4d4d4;'>";
echo "=== DIAGNÓSTICO CLUB LOGIN — " . date('Y-m-d H:i:s') . " ===\n\n";

// 1. PHP Version
echo "PHP VERSION: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n\n";

// 2. ¿Existe env.php?
$envPath = __DIR__ . '/core/database/env.php';
echo "env.php existe: " . (file_exists($envPath) ? "✅ SÍ en $envPath" : "❌ NO — ESTE ES EL PROBLEMA") . "\n";

// 3. ¿Existe conexion.php?
$conexionPath = __DIR__ . '/core/database/conexion.php';
echo "conexion.php existe: " . (file_exists($conexionPath) ? "✅ SÍ" : "❌ NO") . "\n\n";

// 4. Cargar conexion.php y capturar errores
echo "--- Intentando cargar conexion.php ---\n";
try {
    require_once $conexionPath;
    echo "✅ conexion.php cargado OK\n";
    echo "Variable \$conn: " . (isset($conn) ? "✅ existe (" . get_class($conn) . ")" : "❌ NO existe — conexion.php no creó \$conn") . "\n\n";
} catch (Throwable $t) {
    echo "❌ ERROR al cargar conexion.php: " . $t->getMessage() . "\n";
    echo "   Archivo: " . $t->getFile() . " línea " . $t->getLine() . "\n\n";
}

// 5. Probar query a clientesclub
if (isset($conn)) {
    echo "--- Probando consulta a clientesclub ---\n";
    try {
        $stmt = $conn->prepare("SELECT membresia, nombre, apellido, cedula FROM clientesclub WHERE membresia = ?");
        $stmt->execute(['16572']);
        $row = $stmt->fetch();
        if ($row) {
            echo "✅ Registro encontrado:\n";
            echo "   membresia  : " . $row['membresia'] . "\n";
            echo "   nombre     : " . $row['nombre'] . "\n";
            echo "   apellido   : " . $row['apellido'] . "\n";
            echo "   cedula     : [" . $row['cedula'] . "]\n";
            echo "   cedula TRIM: [" . trim($row['cedula']) . "]\n";
            echo "   cedula len : " . strlen(trim($row['cedula'])) . " chars\n";
            
            // Comparar con la cédula de prueba
            $cedula_prueba = '5611112760005B';
            $cedula_db     = trim($row['cedula']);
            echo "\n--- Comparación de contraseña ---\n";
            echo "   Cédula prueba : [$cedula_prueba]\n";
            echo "   Cédula DB     : [$cedula_db]\n";
            echo "   ¿Son iguales? : " . ($cedula_prueba === $cedula_db ? "✅ SÍ" : "❌ NO — ahí está el problema") . "\n";
            
            // Comparar char por char si no son iguales
            if ($cedula_prueba !== $cedula_db) {
                echo "\n   Char por char (prueba vs DB):\n";
                $maxLen = max(strlen($cedula_prueba), strlen($cedula_db));
                for ($i = 0; $i < $maxLen; $i++) {
                    $cp = isset($cedula_prueba[$i]) ? $cedula_prueba[$i] : '(fin)';
                    $cd = isset($cedula_db[$i])     ? $cedula_db[$i]     : '(fin)';
                    $ok = ($cp === $cd) ? '✅' : '❌';
                    $op = isset($cedula_prueba[$i]) ? ord($cedula_prueba[$i]) : '-';
                    $od = isset($cedula_db[$i])     ? ord($cedula_db[$i])     : '-';
                    echo "   pos[$i]: prueba='$cp'(ord=$op) vs DB='$cd'(ord=$od) $ok\n";
                }
            }
        } else {
            echo "❌ Membresía 16572 NO encontrada en clientesclub\n";
        }
    } catch (Throwable $t) {
        echo "❌ ERROR en consulta clientesclub: " . $t->getMessage() . "\n";
    }

    echo "\n--- Probando consulta a club_credenciales ---\n";
    try {
        $stmt2 = $conn->prepare("SELECT id, membresia, login_attempts, locked_until, last_login FROM club_credenciales WHERE membresia = ?");
        $stmt2->execute(['16572']);
        $cred = $stmt2->fetch();
        if ($cred) {
            echo "✅ Ya existe en club_credenciales:\n";
            echo "   id            : " . $cred['id'] . "\n";
            echo "   login_attempts: " . $cred['login_attempts'] . "\n";
            echo "   locked_until  : " . ($cred['locked_until'] ?? 'NULL') . "\n";
            echo "   last_login    : " . ($cred['last_login'] ?? 'NULL') . "\n";
        } else {
            echo "ℹ️  Membresía 16572 NO tiene registro en club_credenciales (es primera vez)\n";
        }
    } catch (Throwable $t) {
        echo "❌ ERROR en consulta club_credenciales: " . $t->getMessage() . "\n";
        if (strpos($t->getMessage(), "doesn't exist") !== false) {
            echo "   ⚠️ La tabla club_credenciales NO EXISTE en esta base de datos\n";
        }
    }

    // 6. Verificar charset / collation de la columna cedula
    echo "\n--- Verificando columna 'cedula' en clientesclub ---\n";
    try {
        $stmt3 = $conn->query("SHOW COLUMNS FROM clientesclub LIKE 'cedula'");
        $col = $stmt3->fetch();
        if ($col) {
            echo "   Tipo     : " . $col['Type'] . "\n";
            echo "   Null     : " . $col['Null'] . "\n";
            echo "   Collation: " . ($col['Collation'] ?? $col['Key'] ?? '(ver charset tabla)') . "\n";
        }
    } catch (Throwable $t) {
        echo "❌ " . $t->getMessage() . "\n";
    }
}

// 7. Sesiones
echo "\n--- Sesiones ---\n";
session_start();
echo "session_start() OK: session_id = " . session_id() . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.gc_probability: " . ini_get('session.gc_probability') . "\n";

echo "\n=== FIN DIAGNÓSTICO ===\n";
echo "</pre>";
?>
