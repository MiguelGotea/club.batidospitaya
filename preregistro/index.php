<?php
/**
 * preregistro/index.php
 * Portal público de preregistro Club Pitaya.
 *
 * URL: https://club.batidospitaya.com/preregistro/?t=TOKEN
 *
 * Acceso seguro: el token es HMAC-SHA256 firmado sobre el número de membresía.
 * No requiere sesión ni login. Solo válido con el token correcto.
 */

require_once __DIR__ . '/../core/database/conexion.php';
require_once __DIR__ . '/../core/config/club_token_secret.php';

// ── Validar token de la URL ────────────────────────────────────────────────────
$tokenRaw  = isset($_GET['t']) ? trim($_GET['t']) : '';
$membresia = $tokenRaw ? clubTokenVerificar($tokenRaw) : null;

if (!$membresia) {
    http_response_code(403);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <title>Enlace inválido — Club Pitaya</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-4">
        <div class="fs-1 mb-3">🔒</div>
        <h4 class="fw-bold text-danger">Enlace inválido o expirado</h4>
        <p class="text-muted">Este enlace no es válido. Si crees que es un error, contacta a tu sucursal Batidos Pitaya.</p>
    </div></body></html>
    <?php
    exit;
}

// ── Buscar el socio en BD ──────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id_clienteclub, membresia, nombre, apellido, cedula,
            fecha_nacimiento, correo, celular, nombre_sucursal, fecha_registro
     FROM clientesclub
     WHERE membresia = :m
     LIMIT 1"
);
$stmt->execute([':m' => $membresia]);
$socio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$socio) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <title>Membresía no encontrada — Club Pitaya</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-4">
        <div class="fs-1 mb-3">🤔</div>
        <h4 class="fw-bold">Membresía no encontrada</h4>
        <p class="text-muted">No se encontró la membresía #<?= htmlspecialchars($membresia) ?> en nuestro sistema.</p>
    </div></body></html>
    <?php
    exit;
}

// ── ¿Ya completó el preregistro? ──────────────────────────────────────────────
$yaCompletado = !empty($socio['nombre']) && !empty($socio['apellido']) && !empty($socio['cedula']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preregistro Club Pitaya — Membresía #<?= $socio['membresia'] ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/preregistro.css?v=<?= time() ?>">
</head>
<body>

<!-- Header compacto sin sesión -->
<header class="preregistro-header">
    <div class="container d-flex align-items-center gap-3 py-3">
        <img src="../core/assets/img/Logo.svg" alt="Club Pitaya" height="36"
             onerror="this.style.display='none'">
        <span class="fw-bold text-white fs-5">Club Pitaya</span>
        <span class="badge bg-white text-success ms-auto">Membresía #<?= htmlspecialchars($socio['membresia']) ?></span>
    </div>
</header>

<main class="container py-4" style="max-width:580px;">

<?php if ($yaCompletado): ?>
    <!-- ── Estado: ya completado ───────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 text-center p-4 mt-3">
        <div class="fs-1 mb-2">✅</div>
        <h4 class="fw-bold text-success">¡Perfil completado!</h4>
        <p class="text-muted mb-1">
            <strong><?= htmlspecialchars(strtoupper($socio['nombre'] . ' ' . $socio['apellido'])) ?></strong>
        </p>
        <p class="text-muted small">
            Tu membresía <strong>#<?= htmlspecialchars($socio['membresia']) ?></strong>
            ya está registrada en <?= htmlspecialchars($socio['nombre_sucursal'] ?? '') ?>.
        </p>
        <p class="text-muted small">Disfruta tus beneficios Club Pitaya 💚</p>
    </div>

<?php else: ?>
    <!-- ── Formulario de preregistro ──────────────────────────────── -->
    <div class="preregistro-card card border-0 shadow-sm rounded-4 overflow-hidden">

        <div class="card-header-preregistro p-4">
            <h5 class="fw-bold mb-1 text-white">
                <i class="bi bi-person-badge me-2"></i>Completa tu perfil
            </h5>
        </div>

        <div class="card-body p-4">

            <!-- PASO 1: Foto de cédula ─────────────────────────── -->
            <div id="seccionFotoCedula" class="mb-4">
                <label class="form-label fw-semibold">
                    <i class="bi bi-camera me-1 text-success"></i>
                    Foto de tu cédula de identidad
                </label>

                <div class="zona-upload" id="zonaUpload"
                     onclick="document.getElementById('inputFotoCedula').click()">
                    <div id="uploadPrompt">
                        <i class="bi bi-cloud-upload fs-2 text-success mb-2 d-block"></i>
                        <span class="fw-semibold text-success">Toca para subir tu cédula</span>
                        <br><small class="text-muted">Foto frontal en buena iluminación</small>
                    </div>
                    <img id="previewCedula" src="" alt="Preview" class="d-none img-fluid rounded-3"
                         style="max-height:200px; object-fit:contain;">
                </div>
                <input type="file" id="inputFotoCedula" class="d-none"
                       accept="image/jpeg,image/png,image/webp" capture="environment">

                <!-- Botón analizar -->
                <button type="button" id="btnAnalizarCedula"
                        class="btn btn-success w-100 mt-3 d-none">
                    <i class="bi bi-upload me-2"></i>Subir Cédula
                </button>

                <!-- Spinner / resultado -->
                <div id="estadoAnalisis" class="mt-2 small text-center" style="min-height:1.5em;"></div>
            </div>

            <!-- PASO 2: Datos extraídos (readonly) ─────────────── -->
            <div id="seccionDatos" class="d-none">



                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted">NOMBRE</label>
                        <div class="campo-readonly" id="dispNombre">—</div>
                        <input type="hidden" id="hidNombre">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted">APELLIDO</label>
                        <div class="campo-readonly" id="dispApellido">—</div>
                        <input type="hidden" id="hidApellido">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted">CÉDULA</label>
                        <div class="campo-readonly" id="dispCedula">—</div>
                        <input type="hidden" id="hidCedula">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted">FECHA NACIMIENTO</label>
                        <div class="campo-readonly" id="dispFechaNac">—</div>
                        <input type="hidden" id="hidFechaNac">
                    </div>
                </div>

                <!-- Correo (único campo editable) -->
                <div class="mb-3">
                    <label for="inputCorreo" class="form-label fw-semibold">
                        <i class="bi bi-envelope me-1 text-success"></i>Correo electrónico
                        <span class="text-muted small fw-normal">(opcional)</span>
                    </label>
                    <input type="email" class="form-control form-control-lg" id="inputCorreo"
                           placeholder="tucorreo@ejemplo.com" autocomplete="email">
                </div>

                <!-- Alerta de error -->
                <div id="alertaError" class="alert alert-danger d-none small py-2" role="alert"></div>

                <!-- Botón guardar -->
                <button type="button" id="btnGuardarPerfil"
                        class="btn btn-success btn-lg w-100 fw-bold">
                    <i class="bi bi-check2-circle me-2"></i>Guardar mi perfil
                </button>

                <!-- Botón re-tomar foto -->
                <button type="button" id="btnRetomar"
                        class="btn btn-link text-muted small w-100 mt-2">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Volver a subir la cédula
                </button>
            </div>

        </div><!-- /card-body -->
    </div><!-- /preregistro-card -->

    <!-- Aviso legal -->
    <p class="text-center text-muted small mt-3">
        <i class="bi bi-shield-check me-1"></i>
        Tus datos solo se usan para identificarte como miembro Club Pitaya.
    </p>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const PREREGISTRO_TOKEN = <?= json_encode($tokenRaw) ?>;
    const MEMBRESIA_NUM     = <?= json_encode($membresia) ?>;
</script>
<script src="js/preregistro.js?v=<?= time() ?>"></script>
</body>
</html>
