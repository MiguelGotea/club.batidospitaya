<?php
/**
 * login.php — Portal de Miembros Club Pitaya
 * Estándar corporativo: Bootstrap 5, sin gradientes, logo SVG, bcrypt
 */

// ⚠️ LiteSpeed Hostinger: session_start() DEBE ir antes de require_once conexion.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/core/database/conexion.php';

date_default_timezone_set('America/Managua');

// Ya logueado → ir al dashboard
if ($_SESSION['club_logged_in'] ?? false) {
    header('Location: dashboard.php');
    exit();
}

// ── Generar / mantener token CSRF ──────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Tipos de mensaje para la vista ────────────────────────────
$error_message  = '';
$error_type     = 'error';   // 'error' | 'warning' | 'sin_cedula'

// ── Constantes de seguridad ────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCK_MINUTES',       15);

// ── Procesar formulario ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    // 1. Validar CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_message = 'Sesión inválida. Recarga la página e intenta de nuevo.';
    } else {

        $membresia = trim($_POST['membresia'] ?? '');
        $password  = trim($_POST['password']  ?? '');

        if (empty($membresia) || empty($password)) {
            $error_message = 'Por favor completa todos los campos.';
        } else {
            try {

                // 2. Verificar si existe en club_credenciales
                $stmt = $conn->prepare("
                    SELECT id, password_hash, login_attempts, locked_until
                    FROM   club_credenciales
                    WHERE  membresia = ?
                ");
                $stmt->execute([$membresia]);
                $credencial = $stmt->fetch();

                if ($credencial) {
                    // ── USUARIO YA REGISTRADO en club_credenciales ──────

                    // 3a. ¿Está bloqueado?
                    if ($credencial['locked_until'] && strtotime($credencial['locked_until']) > time()) {
                        $minutos = ceil((strtotime($credencial['locked_until']) - time()) / 60);
                        $error_message = "Cuenta bloqueada temporalmente por múltiples intentos fallidos. "
                                       . "Intenta de nuevo en <strong>{$minutos} min</strong>.";
                        $error_type = 'warning';

                    } elseif (password_verify($password, $credencial['password_hash'])) {
                        // 3b. ✅ Login exitoso
                        $conn->prepare("
                            UPDATE club_credenciales
                            SET    login_attempts = 0,
                                   locked_until   = NULL,
                                   last_login      = NOW()
                            WHERE  id = ?
                        ")->execute([$credencial['id']]);

                        $miembro = $conn->prepare("SELECT nombre, apellido FROM clientesclub WHERE membresia = ?");
                        $miembro->execute([$membresia]);
                        $datos = $miembro->fetch();

                        // LiteSpeed: NO usar session_regenerate_id — rompe la cookie de sesión en redirect
                        $_SESSION['club_logged_in']     = true;
                        $_SESSION['club_membresia']     = $membresia;
                        $_SESSION['club_nombre']        = $datos['nombre']   ?? '';
                        $_SESSION['club_apellido']      = $datos['apellido'] ?? '';
                        $_SESSION['last_regeneration']  = time();
                        unset($_SESSION['csrf_token']); // invalidar CSRF viejo

                        $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect);
                        exit();

                    } else {
                        // 3c. ❌ Contraseña incorrecta → incrementar intentos
                        $nuevos_intentos = $credencial['login_attempts'] + 1;
                        $lock_sql        = '';
                        $params          = [$nuevos_intentos];

                        if ($nuevos_intentos >= MAX_LOGIN_ATTEMPTS) {
                            $lock_sql = ", locked_until = DATE_ADD(NOW(), INTERVAL " . LOCK_MINUTES . " MINUTE)";
                            $error_message = "Contraseña incorrecta. Cuenta bloqueada "
                                           . LOCK_MINUTES . " minutos por seguridad.";
                            $error_type = 'warning';
                        } else {
                            $restantes     = MAX_LOGIN_ATTEMPTS - $nuevos_intentos;
                            $error_message = "Contraseña incorrecta. Te quedan <strong>{$restantes} intento(s)</strong>.";
                        }

                        $params[] = $credencial['id'];
                        $conn->prepare("
                            UPDATE club_credenciales
                            SET    login_attempts = ? {$lock_sql}
                            WHERE  id = ?
                        ")->execute($params);
                    }

                } else {
                    // ── PRIMERA VEZ — verificar con cédula (migración transparente) ──

                    $stmt2 = $conn->prepare("
                        SELECT membresia, nombre, apellido, TRIM(cedula) AS cedula
                        FROM   clientesclub
                        WHERE  membresia = ?
                    ");
                    $stmt2->execute([trim($membresia)]);
                    $miembro = $stmt2->fetch();

                    if (!$miembro) {
                        $error_message = 'Número de membresía no encontrado.';

                    } elseif (empty($miembro['cedula'])) {
                        // 4a. ⚠️ Sin cédula registrada
                        $error_type    = 'sin_cedula';
                        $error_message = $membresia; // se usa para personalizar el mensaje

                    } elseif (trim($password) === trim($miembro['cedula'])) {
                        // 4b. ✅ Cédula coincide → crear hash y registrar primera vez
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                        $conn->prepare("
                            INSERT INTO club_credenciales (membresia, password_hash, last_login)
                            VALUES (?, ?, NOW())
                        ")->execute([$membresia, $hash]);

                        // LiteSpeed: NO usar session_regenerate_id — rompe la cookie de sesión en redirect
                        $_SESSION['club_logged_in']    = true;
                        $_SESSION['club_membresia']    = $membresia;
                        $_SESSION['club_nombre']       = $miembro['nombre']   ?? '';
                        $_SESSION['club_apellido']     = $miembro['apellido'] ?? '';
                        $_SESSION['last_regeneration'] = time();
                        $_SESSION['primer_ingreso']    = true;
                        unset($_SESSION['csrf_token']); // invalidar CSRF viejo

                        header('Location: dashboard.php');
                        exit();

                    } else {
                        $error_message = 'Contraseña incorrecta. Use su número de cédula como contraseña inicial.';
                    }
                }

            } catch (Exception $e) {
                error_log('[Club Login Error] ' . $e->getMessage());
                // Detectar si la tabla no existe aún
                if (strpos($e->getMessage(), "club_credenciales") !== false
                 || strpos($e->getMessage(), "doesn't exist") !== false
                 || strpos($e->getMessage(), "Table") !== false) {
                    $error_message = 'Sistema en configuración. Contacta al administrador. (tabla pendiente)';
                } else {
                    $error_message = 'Error del sistema. Por favor intenta de nuevo.';
                }
            }
        }
    }

    // Regenerar CSRF después de cada intento fallido
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Club Batidos Pitaya</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- CSS corporativo del portal -->
    <link rel="stylesheet" href="css/club_global.css">

    <style>
        body {
            background-color: #F0F4F3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(14, 84, 76, 0.13);
            padding: 44px 40px 36px;
        }

        .login-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            margin-bottom: 6px;
        }

        .login-logo-fallback {
            width: 72px;
            height: 72px;
            background-color: var(--pitaya-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            font-size: 1.9rem;
        }

        .login-title {
            color: #0E544C;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 2px;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 0.88rem;
            margin-bottom: 28px;
        }

        .form-control-login {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #f8faf9;
        }

        .form-control-login:focus {
            border-color: #51B8AC;
            box-shadow: 0 0 0 3px rgba(81,184,172,0.15);
            background-color: #fff;
            outline: none;
        }

        .btn-login {
            background-color: #0E544C;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 700;
            width: 100%;
            letter-spacing: 0.5px;
            transition: background-color 0.25s, transform 0.15s, box-shadow 0.25s;
            cursor: pointer;
        }

        .btn-login:hover:not(:disabled) {
            background-color: #0a3d37;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(14, 84, 76, 0.22);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Alerta error general */
        .alert-login-error {
            background-color: #fff2f2;
            border: 1px solid #f5c2c7;
            border-left: 4px solid #dc3545;
            border-radius: 10px;
            padding: 12px 15px;
            color: #842029;
            font-size: 0.88rem;
            margin-bottom: 20px;
        }

        /* Alerta advertencia (bloqueo, intentos) */
        .alert-login-warning {
            background-color: #fff8e1;
            border: 1px solid #ffe082;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 12px 15px;
            color: #7c5a00;
            font-size: 0.88rem;
            margin-bottom: 20px;
        }

        /* Alerta especial: sin cédula */
        .alert-sin-cedula {
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            border-left: 4px solid #51B8AC;
            border-radius: 10px;
            padding: 16px;
            color: #14532d;
            font-size: 0.88rem;
            margin-bottom: 20px;
        }

        .alert-sin-cedula .alert-title {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 7px;
            color: #0E544C;
        }

        .help-box {
            background-color: #f8faf9;
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 22px;
            font-size: 0.82rem;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }

        .help-box strong { color: #0E544C; }

        /* Spinner en botón */
        .spinner-btn {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="login-wrapper">

    <div class="login-card">

        <!-- Logo corporativo -->
        <div class="text-center mb-1">
            <img src="core/assets/img/Logo.svg"
                 alt="Batidos Pitaya"
                 class="login-logo"
                 onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
            <div id="logo-fallback" class="login-logo-fallback" style="display:none;">
                <i class="fas fa-leaf text-white"></i>
            </div>
        </div>

        <div class="text-center">
            <h1 class="login-title">Batidos Pitaya</h1>
            <p class="login-subtitle">Portal de Miembros &mdash; Club Pitaya</p>
        </div>

        <!-- ── Alertas ──────────────────────────────────────── -->
        <?php if ($error_message): ?>

            <?php if ($error_type === 'sin_cedula'): ?>
                <!-- Alerta especial: sin cédula registrada -->
                <div class="alert-sin-cedula">
                    <div class="alert-title">
                        <i class="fas fa-info-circle"></i>
                        Registro incompleto
                    </div>
                    <p class="mb-2">
                        Tu membresía <strong>#<?= htmlspecialchars($error_message) ?></strong> no tiene
                        número de cédula registrado en el sistema, que es necesario como
                        contraseña de primer acceso.
                    </p>
                    <p class="mb-0">
                        <strong>¿Qué hacer?</strong> Visítanos o escríbenos para completar tu registro
                        y recibir tu acceso:
                    </p>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <a href="https://wa.me/50578784400"
                           target="_blank"
                           class="btn btn-sm"
                           style="background:#25D366;color:#fff;border-radius:8px;font-weight:600;font-size:0.8rem;">
                            <i class="fab fa-whatsapp me-1"></i> WhatsApp
                        </a>
                        <a href="mailto:info@batidospitaya.com"
                           class="btn btn-sm"
                           style="background:#0E544C;color:#fff;border-radius:8px;font-weight:600;font-size:0.8rem;">
                            <i class="fas fa-envelope me-1"></i> Correo
                        </a>
                    </div>
                </div>

            <?php elseif ($error_type === 'warning'): ?>
                <div class="alert-login-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error_message ?>
                </div>

            <?php else: ?>
                <div class="alert-login-error">
                    <i class="fas fa-times-circle me-2"></i>
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- ── Formulario ──────────────────────────────────── -->
        <form method="POST" id="loginForm" novalidate autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <!-- login=1 siempre presente: el botón submit se deshabilita con JS y browsers lo excluyen del POST -->
            <input type="hidden" name="login" value="1">

            <div class="mb-3">
                <label for="membresia" class="form-label">
                    <i class="fas fa-id-card me-1" style="color:#51B8AC;"></i>
                    Número de Membresía
                </label>
                <input type="text"
                       class="form-control form-control-login"
                       id="membresia"
                       name="membresia"
                       value="<?= htmlspecialchars($_POST['membresia'] ?? '') ?>"
                       placeholder="Ej. 10045"
                       required
                       autofocus
                       autocomplete="off">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1" style="color:#51B8AC;"></i>
                    Contraseña
                </label>
                <div class="position-relative">
                    <input type="password"
                           class="form-control form-control-login pe-5"
                           id="password"
                           name="password"
                           placeholder="Tu contraseña"
                           required
                           autocomplete="current-password">
                    <button type="button"
                            class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 p-0 border-0 bg-transparent text-muted"
                            id="togglePassword"
                            tabindex="-1"
                            aria-label="Mostrar contraseña">
                        <i class="fas fa-eye" id="eyeIcon" style="font-size:1rem;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login" id="loginButton">
                <span class="spinner-btn" id="loginSpinner"></span>
                <span id="loginText">Iniciar Sesión</span>
            </button>
        </form>

        <!-- ── Ayuda ───────────────────────────────────────── -->
        <div class="help-box mt-3">
            <p class="mb-1">
                <i class="fas fa-circle-question me-1" style="color:#51B8AC;"></i>
                <strong>Primer ingreso:</strong> Tu contraseña inicial es tu número de cédula.
            </p>
            <p class="mb-0 text-muted" style="font-size:0.78rem;">
                ¿Olvidaste tu contraseña? Contáctanos por WhatsApp o visita una sucursal.
            </p>
        </div>

    </div><!-- /login-card -->
</div><!-- /login-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mostrar/ocultar contraseña
    document.getElementById('togglePassword').addEventListener('click', function () {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type  = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type  = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });

    // Spinner en botón al enviar
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        const btn     = document.getElementById('loginButton');
        const spinner = document.getElementById('loginSpinner');
        const text    = document.getElementById('loginText');
        btn.disabled        = true;
        spinner.style.display = 'inline-block';
        text.textContent      = 'Verificando...';
    });

    // Reactivar si el navegador regresa (back)
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            const btn = document.getElementById('loginButton');
            document.getElementById('loginSpinner').style.display = 'none';
            document.getElementById('loginText').textContent = 'Iniciar Sesión';
            btn.disabled = false;
        }
    });
</script>
</body>
</html>
