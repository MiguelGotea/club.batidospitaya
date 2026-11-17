<?php
/**
 * _test_post.php — SOLO USO TEMPORAL — Simula el POST del login para ver el error exacto
 * URL: https://club.batidospitaya.com/_test_post.php
 * ELIMINAR DESPUÉS DE DEPURAR
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#1e1e1e;color:#d4d4d4;'>";
echo "=== TEST POST LOGIN — " . date('Y-m-d H:i:s') . " ===\n\n";

// Simular exactamente lo que hace login.php al recibir el POST
$membresia_prueba = '16572';
$password_prueba  = '5611112760005B';

try {
    require_once __DIR__ . '/core/database/conexion.php';
    echo "✅ conexion.php OK — \$conn: " . get_class($conn) . "\n\n";

    // PASO 1: ¿Existe en club_credenciales?
    echo "--- PASO 1: Buscar en club_credenciales ---\n";
    $stmt = $conn->prepare("SELECT id, password_hash, login_attempts, locked_until FROM club_credenciales WHERE membresia = ?");
    $stmt->execute([$membresia_prueba]);
    $credencial = $stmt->fetch();
    
    if ($credencial) {
        echo "✅ Ya existe en club_credenciales (id=" . $credencial['id'] . ")\n";
        echo "   login_attempts: " . $credencial['login_attempts'] . "\n";
        echo "   locked_until  : " . ($credencial['locked_until'] ?? 'NULL') . "\n";
        
        // Verificar contraseña
        $ok = password_verify($password_prueba, $credencial['password_hash']);
        echo "   password_verify con '$password_prueba': " . ($ok ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n";
    } else {
        echo "ℹ️  No existe en club_credenciales — es primera vez\n\n";

        // PASO 2: Buscar en clientesclub con TRIM
        echo "--- PASO 2: Buscar en clientesclub ---\n";
        $stmt2 = $conn->prepare("SELECT membresia, nombre, apellido, TRIM(cedula) AS cedula FROM clientesclub WHERE membresia = ?");
        $stmt2->execute([trim($membresia_prueba)]);
        $miembro = $stmt2->fetch();
        
        if (!$miembro) {
            echo "❌ Membresía '$membresia_prueba' NO encontrada en clientesclub\n";
        } elseif (empty($miembro['cedula'])) {
            echo "⚠️ Miembro encontrado pero cedula está VACÍA/NULL\n";
        } else {
            $cedula_db    = trim($miembro['cedula']);
            $pass_input   = trim($password_prueba);
            $coincide     = ($pass_input === $cedula_db);
            
            echo "   Miembro    : " . $miembro['nombre'] . " " . $miembro['apellido'] . "\n";
            echo "   cedula DB  : [" . $cedula_db . "] (len=" . strlen($cedula_db) . ")\n";
            echo "   pass input : [" . $pass_input . "] (len=" . strlen($pass_input) . ")\n";
            echo "   ¿Coinciden?: " . ($coincide ? "✅ SÍ — login debería funcionar" : "❌ NO — las cadenas son diferentes") . "\n";
            
            if (!$coincide) {
                echo "\n   Comparación char a char:\n";
                for ($i = 0; $i < max(strlen($pass_input), strlen($cedula_db)); $i++) {
                    $cp = $pass_input[$i]  ?? '(fin)';
                    $cd = $cedula_db[$i]   ?? '(fin)';
                    $ok2 = ($cp === $cd);
                    echo "   [$i] input='" . $cp . "'(". (isset($pass_input[$i]) ? ord($cp) : '-') . ")  DB='" . $cd . "'(" . (isset($cedula_db[$i]) ? ord($cd) : '-') . ")  " . ($ok2 ? "✅" : "❌") . "\n";
                }
            } else {
                // Intentar INSERT para ver si falla
                echo "\n--- PASO 3: Intentar INSERT en club_credenciales ---\n";
                $hash = password_hash($password_prueba, PASSWORD_BCRYPT, ['cost' => 12]);
                try {
                    $conn->prepare("INSERT INTO club_credenciales (membresia, password_hash, last_login) VALUES (?, ?, NOW())")
                         ->execute([$membresia_prueba, $hash]);
                    echo "✅ INSERT exitoso — id=" . $conn->lastInsertId() . "\n";
                    echo "   ⚠️ Registro de prueba creado — deberías poder hacer login ahora\n";
                    echo "   (Elimina este registro de prueba si quieres volver a probar el flujo inicial)\n";
                } catch (Throwable $t) {
                    echo "❌ INSERT falló: " . $t->getMessage() . "\n";
                }
            }
        }
    }

} catch (Throwable $t) {
    echo "❌ EXCEPCIÓN GENERAL: " . $t->getMessage() . "\n";
    echo "   Archivo: " . $t->getFile() . "\n";
    echo "   Línea  : " . $t->getLine() . "\n";
    echo "   Traza  :\n" . $t->getTraceAsString() . "\n";
}

echo "\n=== FIN ===\n";
echo "</pre>";
?>
