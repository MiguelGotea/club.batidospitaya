<?php
/**
 * _login_debug.php — Debug del flujo completo
 * ELIMINAR DESPUÉS: https://club.batidospitaya.com/_login_debug.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión PRIMERO
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<pre style='font:13px monospace;padding:20px;background:#111;color:#eee'>";
echo "=== DEBUG FLUJO LOGIN " . date('H:i:s') . " ===\n\n";

// --- PASO A: Conexión ---
echo "[A] Cargando conexion.php...\n";
try {
    require_once __DIR__ . '/core/database/conexion.php';
    echo "    OK — PDO conectado\n\n";
} catch(Throwable $e) {
    die("    FALLO: " . $e->getMessage() . "\n");
}

// --- PASO B: Estado actual de la tabla ---
echo "[B] Estado club_credenciales para membresia=16572:\n";
try {
    $r = $conn->query("SELECT COUNT(*) as total FROM club_credenciales")->fetch();
    echo "    Total registros en tabla: " . $r['total'] . "\n";
    
    $s = $conn->prepare("SELECT id, membresia, last_login, login_attempts FROM club_credenciales WHERE membresia='16572'");
    $s->execute();
    $reg = $s->fetch();
    if ($reg) {
        echo "    Registro 16572: id={$reg['id']}, attempts={$reg['login_attempts']}, last_login={$reg['last_login']}\n\n";
    } else {
        echo "    Sin registro para 16572\n\n";
    }
} catch(Throwable $e) {
    echo "    ERROR: " . $e->getMessage() . "\n\n";
}

// --- PASO C: Leer cédula de clientesclub ---
echo "[C] Cédula en clientesclub (membresia=16572):\n";
try {
    $s2 = $conn->prepare("SELECT membresia, TRIM(cedula) AS cedula, nombre FROM clientesclub WHERE membresia=?");
    $s2->execute(['16572']);
    $m = $s2->fetch();
    if ($m) {
        echo "    nombre : " . $m['nombre'] . "\n";
        echo "    cedula : [" . $m['cedula'] . "] len=" . strlen($m['cedula']) . "\n";
        $prueba = '5611112760005B';
        echo "    prueba : [$prueba] len=" . strlen($prueba) . "\n";
        echo "    iguales: " . ($prueba === $m['cedula'] ? "SÍ ✅" : "NO ❌") . "\n\n";
    } else {
        echo "    No encontrado\n\n";
    }
} catch(Throwable $e) {
    echo "    ERROR: " . $e->getMessage() . "\n\n";
}

// --- PASO D: Simular INSERT directo ---
echo "[D] INSERT directo en club_credenciales (membresia=16572):\n";
try {
    // Primero borrar si existe para prueba limpia
    $conn->prepare("DELETE FROM club_credenciales WHERE membresia=?")->execute(['16572']);
    echo "    DELETE previo OK\n";
    
    $hash = password_hash('5611112760005B', PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $conn->prepare("INSERT INTO club_credenciales (membresia, password_hash, last_login) VALUES (?,?,NOW())");
    $ok   = $ins->execute(['16572', $hash]);
    $id   = $conn->lastInsertId();
    echo "    INSERT " . ($ok ? "OK — id=$id" : "FALLÓ") . "\n";
    
    // Verificar que quedó
    $v = $conn->prepare("SELECT id, membresia FROM club_credenciales WHERE membresia=?");
    $v->execute(['16572']);
    $vr = $v->fetch();
    echo "    Verificación: " . ($vr ? "✅ registro existe id={$vr['id']}" : "❌ NO existe tras insert") . "\n\n";
} catch(Throwable $e) {
    echo "    ERROR INSERT: " . $e->getMessage() . "\n\n";
}

// --- PASO E: Estado de sesión ---
echo "[E] Sesión actual:\n";
echo "    session_id: " . session_id() . "\n";
echo "    save_path : " . ini_get('session.save_path') . "\n";
$keys = ['club_logged_in','club_membresia','logged_in','membresia','csrf_token'];
foreach ($keys as $k) {
    $val = $_SESSION[$k] ?? '(no existe)';
    if ($k === 'csrf_token' && strlen((string)$val) > 10) $val = substr($val,0,10).'...';
    echo "    $_SESSION[$k] = $val\n";
}

// --- PASO F: Crear sesión de prueba y ver si dashboard la acepta ---
echo "\n[F] Creando sesión de prueba para 16572:\n";
$_SESSION['club_logged_in'] = true;
$_SESSION['club_membresia'] = '16572';
$_SESSION['club_nombre']    = 'julio cesar';
$_SESSION['club_apellido']  = 'solorzano jimenez';
echo "    Sesión creada. Ahora ve a: https://club.batidospitaya.com/dashboard.php\n";
echo "    Si el dashboard carga = problema era solo el deploy del fix.\n";
echo "    Si redirige a login.php = el fix NO llegó a Hostinger todavía.\n";

echo "\n=== FIN ===\n</pre>";
?>
