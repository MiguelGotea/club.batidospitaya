<?php
// login.php - Página de login
require_once 'config.php';

session_start();

if ($_SESSION['logged_in'] ?? false) {
    header("Location: dashboard.php");
    exit();
}

$error_message = "";

if (isset($_POST['login'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $membresia = trim($_POST['membresia'] ?? '');
        $password  = trim($_POST['password']  ?? '');

        if (!empty($membresia) && !empty($password)) {
            $query = "SELECT * FROM clientesclub WHERE membresia = :membresia";
            $stmt  = $db->prepare($query);
            $stmt->bindParam(':membresia', $membresia);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($password === $user['membresia'] || $password == $user['membresia']) {
                    $_SESSION['logged_in'] = true;
                    $_SESSION['membresia'] = $user['membresia'];
                    $_SESSION['nombre']    = $user['nombre'];
                    $_SESSION['apellido']  = $user['apellido'];

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_message = "Contraseña incorrecta. Use su número de membresía como contraseña.";
                }
            } else {
                $error_message = "Número de membresía no encontrado.";
            }
        } else {
            $error_message = "Por favor complete todos los campos.";
        }
    } catch(Exception $e) {
        error_log("Error de login: " . $e->getMessage());
        $error_message = "Error del sistema. Por favor intente nuevamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Miembros - Batidos Pitaya</title>
    <link href="https://fonts.googleapis.com/css2?family=Calibri:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pitaya-primary: #51B8AC;
            --pitaya-secondary: #0E544C;
            --pitaya-light: #F6F6F6;
            --pitaya-white: #FFFFFF;
            --pitaya-gradient: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            --shadow-soft: 0 4px 20px rgba(81, 184, 172, 0.1);
            --shadow-medium: 0 8px 30px rgba(14, 84, 76, 0.15);
            --border-radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Calibri', Arial, sans-serif;
            background: var(--pitaya-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-30px, -30px) rotate(180deg); }
        }

        .login-container {
            background: var(--pitaya-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            padding: 50px 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .brand-header {
            margin-bottom: 40px;
        }

        .logo {
            width: 100px;
            height: 100px;
            background: var(--pitaya-gradient);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--pitaya-white);
            font-weight: bold;
            box-shadow: var(--shadow-soft);
        }

        h1 {
            color: var(--pitaya-secondary);
            margin-bottom: 10px;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: var(--pitaya-secondary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: 'Calibri', Arial, sans-serif;
            background: var(--pitaya-light);
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--pitaya-primary);
            background: var(--pitaya-white);
            box-shadow: 0 0 0 3px rgba(81, 184, 172, 0.1);
            transform: translateY(-2px);
        }

        .btn-login {
            width: 100%;
            padding: 18px;
            background: var(--pitaya-gradient);
            color: var(--pitaya-white);
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Calibri', Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .error {
            background: linear-gradient(135deg, #ff6b6b, #ffa8a8);
            color: var(--pitaya-white);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #ff5252;
            font-weight: 600;
        }

        .help-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }

        .help-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .help-text strong {
            color: var(--pitaya-secondary);
        }

        .help-example {
            background: var(--pitaya-light);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 13px;
            border-left: 3px solid var(--pitaya-primary);
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 25px;
            }
            h1 { font-size: 1.8rem; }
            .logo { width: 80px; height: 80px; font-size: 32px; }
        }

        .btn-login.loading {
            position: relative;
            color: transparent;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand-header">
            <div class="logo">🥭</div>
            <h1>Batidos Pitaya</h1>
            <p class="subtitle">Portal de Miembros</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="membresia">Número de Membresía</label>
                <input type="text" id="membresia" name="membresia"
                       value="<?php echo htmlspecialchars($_POST['membresia'] ?? ''); ?>"
                       placeholder="Ingrese su número de membresía" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       placeholder="Su número de membresía" required>
            </div>

            <button type="submit" name="login" class="btn-login" id="loginButton">
                Iniciar Sesión
            </button>
        </form>

        <div class="help-section">
            <div class="help-text">
                <p><strong>Acceso Simple:</strong> Su número de membresía es tanto su usuario como su contraseña</p>
                <div class="help-example">
                    <strong>Ejemplo:</strong><br>
                    Si su membresía es <strong>12345</strong><br>
                    Usuario: <code>12345</code><br>
                    Contraseña: <code>12345</code>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.getElementById('loginButton');
            button.classList.add('loading');
            setTimeout(function() { button.disabled = true; }, 100);
        });

        document.getElementById('membresia').addEventListener('input', function() {
            const passwordField = document.getElementById('password');
            if (this.value && !passwordField.value) {
                passwordField.value = this.value;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('membresia').focus();
        });

        window.addEventListener('load', function() {
            const button = document.getElementById('loginButton');
            button.classList.remove('loading');
            button.disabled = false;
        });
    </script>
</body>
</html>
