<?php
/**
 * header_club.php — Navbar del Portal de Miembros Club Pitaya
 *
 * Uso: include al inicio del <body> en cada página del portal.
 * Requiere que la sesión ya esté iniciada (auth_club.php).
 *
 * Variables disponibles: $_SESSION['club_nombre'], $_SESSION['club_apellido'], $_SESSION['club_membresia']
 */
$nombre    = $_SESSION['club_nombre']    ?? '';
$apellido  = $_SESSION['club_apellido'] ?? '';
$membresia = $_SESSION['club_membresia'] ?? '';
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg club-header">
    <div class="container">

        <!-- Logo + Nombre -->
        <a class="navbar-brand" href="dashboard.php">
            <img src="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>/core/assets/img/Logo.svg"
                 alt="Batidos Pitaya"
                 onerror="this.style.display='none';">
            Club Pitaya
        </a>

        <!-- Toggle mobile -->
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#clubNavbar"
                aria-controls="clubNavbar" aria-expanded="false" aria-label="Menú">
            <i class="fas fa-bars text-white"></i>
        </button>

        <div class="collapse navbar-collapse" id="clubNavbar">

            <!-- Links de navegación -->
            <ul class="navbar-nav me-auto ms-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>"
                       href="dashboard.php">
                        <i class="fas fa-house me-1"></i> Mi Portal
                    </a>
                </li>
                <!-- Futuras secciones se agregan aquí:
                <li class="nav-item">
                    <a class="nav-link" href="pedidos.php">
                        <i class="fas fa-bag-shopping me-1"></i> Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cupones.php">
                        <i class="fas fa-ticket me-1"></i> Cupones
                    </a>
                </li>
                -->
            </ul>

            <!-- Usuario + Logout -->
            <div class="d-flex align-items-center gap-3">
                <span style="color:rgba(255,255,255,0.8); font-size:0.85rem;">
                    <i class="fas fa-circle-user me-1"></i>
                    <strong><?= htmlspecialchars($nombre . ' ' . $apellido) ?></strong>
                    <span style="opacity:0.65;"> &mdash; #<?= htmlspecialchars($membresia) ?></span>
                </span>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-right-from-bracket me-1"></i> Salir
                </a>
            </div>

        </div>
    </div>
</nav>
