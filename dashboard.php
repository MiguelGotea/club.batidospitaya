<?php
// dashboard.php - Dashboard básico sin consultas complejas
session_start();

if (!($_SESSION['logged_in'] ?? false)) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$membresia = $_SESSION['membresia'];
$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];

// Solo consultas básicas sin JOINs complejos
try {
    // Información del miembro
    $query_miembro = "SELECT * FROM clientesclub WHERE membresia = :membresia";
    $stmt_miembro = $db->prepare($query_miembro);
    $stmt_miembro->bindParam(':membresia', $membresia);
    $stmt_miembro->execute();
    $miembro = $stmt_miembro->fetch(PDO::FETCH_ASSOC);

    // Historial de compras básico con filtro por grupos principales
    $query_compras = "SELECT v.*, DATE_FORMAT(v.Fecha, '%d/%m/%Y') as fecha_formateada,
                             b.Nombre as nombre_batido, g.NombreGrupo, g.Tipo
                      FROM VentasGlobalesAccessCSV v
                      LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                      LEFT JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                      WHERE v.CodCliente = :membresia 
                      AND (g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store') OR g.Tipo IS NULL)
                      ORDER BY v.Fecha DESC 
                      LIMIT 20";
    $stmt_compras = $db->prepare($query_compras);
    $stmt_compras->bindParam(':membresia', $membresia);
    $stmt_compras->execute();
    $compras = $stmt_compras->fetchAll(PDO::FETCH_ASSOC);

    // Puntos totales solo de grupos principales
    $query_puntos = "SELECT SUM(v.Puntos * v.Cantidad) as total_puntos 
                     FROM VentasGlobalesAccessCSV v
                     LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                     LEFT JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                     WHERE v.CodCliente = :membresia
                     AND (g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store') OR g.Tipo IS NULL)";
    $stmt_puntos = $db->prepare($query_puntos);
    $stmt_puntos->bindParam(':membresia', $membresia);
    $stmt_puntos->execute();
    $puntos_resultado = $stmt_puntos->fetch(PDO::FETCH_ASSOC);
    $total_puntos = $puntos_resultado['total_puntos'] ?? 0;

    // Estadísticas por tipo de producto
    $query_stats_tipos = "SELECT g.Tipo, 
                                 COUNT(*) as total_compras,
                                 SUM(v.Cantidad) as total_productos
                          FROM VentasGlobalesAccessCSV v
                          JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                          JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                          WHERE v.CodCliente = :membresia
                          AND g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store')
                          GROUP BY g.Tipo
                          ORDER BY total_productos DESC";
    $stmt_stats_tipos = $db->prepare($query_stats_tipos);
    $stmt_stats_tipos->bindParam(':membresia', $membresia);
    $stmt_stats_tipos->execute();
    $stats_por_tipo = $stmt_stats_tipos->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas generales solo de grupos principales
    $query_stats = "SELECT 
                        COUNT(*) as total_compras,
                        SUM(v.Cantidad) as total_productos,
                        MAX(v.Fecha) as ultima_compra
                    FROM VentasGlobalesAccessCSV v
                    LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                    LEFT JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                    WHERE v.CodCliente = :membresia
                    AND (g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store') OR g.Tipo IS NULL)";
    $stmt_stats = $db->prepare($query_stats);
    $stmt_stats->bindParam(':membresia', $membresia);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Producto favorito solo de grupos principales
    $query_producto_favorito = "SELECT v.DBBatidos_Nombre, g.Tipo, SUM(v.Cantidad) as total_cantidad
                                FROM VentasGlobalesAccessCSV v
                                LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                                LEFT JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                                WHERE v.CodCliente = :membresia
                                AND g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store')
                                GROUP BY v.DBBatidos_Nombre, g.Tipo
                                ORDER BY total_cantidad DESC
                                LIMIT 1";
    $stmt_producto = $db->prepare($query_producto_favorito);
    $stmt_producto->bindParam(':membresia', $membresia);
    $stmt_producto->execute();
    $producto_favorito = $stmt_producto->fetch(PDO::FETCH_ASSOC);

    // Ingredientes más consumidos - OPTIMIZADO para evitar timeout
    // Primero obtenemos los productos más consumidos del usuario
    $query_productos_usuario = "SELECT DISTINCT v.CodProducto
                                FROM VentasGlobalesAccessCSV v
                                JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                                JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                                WHERE v.CodCliente = :membresia
                                AND g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store')
                                LIMIT 10";
    
    $stmt_productos = $db->prepare($query_productos_usuario);
    $stmt_productos->bindParam(':membresia', $membresia);
    $stmt_productos->execute();
    $productos_usuario = $stmt_productos->fetchAll(PDO::FETCH_COLUMN);
    

    // Top 3 productos más consumidos (para análisis nutricional detallado)
    $query_top_productos = "SELECT v.DBBatidos_Nombre, v.CodProducto, SUM(v.Cantidad) as total_cantidad,
                                   COUNT(*) as veces_comprado, AVG(v.Puntos) as puntos_promedio
                            FROM VentasGlobalesAccessCSV v
                            LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                            LEFT JOIN GrupoProductosVenta g ON b.CodGrupo = g.CodGrupo
                            WHERE v.CodCliente = :membresia
                            AND g.Tipo IN ('Batido', 'Bowl', 'Waffles', 'Pitaya Store')
                            GROUP BY v.DBBatidos_Nombre, v.CodProducto
                            ORDER BY total_cantidad DESC
                            LIMIT 3";
    $stmt_top_productos = $db->prepare($query_top_productos);
    $stmt_top_productos->bindParam(':membresia', $membresia);
    $stmt_top_productos->execute();
    $top_productos_detalle = $stmt_top_productos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener ingredientes de cocina de los productos del usuario (solo Cocina = 'VERDADERO')
    // EXCLUYENDO los ingredientes específicos: 'B001', 'B004', 'E004'
    $top_ingredientes = [];
    if (!empty($productos_usuario)) {
        $placeholders = str_repeat('?,', count($productos_usuario) - 1) . '?';
        $query_ingredientes_cocina = "SELECT DISTINCT i.Nombre as ingrediente, i.Unidad, 
                                             SUM(sr.Cantidad) as cantidad_total
                                      FROM SubReceta sr
                                      JOIN DBIngredientes i ON sr.CodIngrediente = i.CodIngrediente
                                      WHERE sr.CodBatido IN ($placeholders)
                                      AND i.Cocina = 'VERDADERO'
                                      AND i.CodIngrediente NOT IN ('B001', 'B004', 'E004') -- EXCLUIR ESTOS INGREDIENTES
                                      GROUP BY i.CodIngrediente, i.Nombre, i.Unidad
                                      ORDER BY cantidad_total DESC
                                      LIMIT 3";
        
        $stmt_ingredientes = $db->prepare($query_ingredientes_cocina);
        $stmt_ingredientes->execute($productos_usuario);
        $top_ingredientes = $stmt_ingredientes->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener TODOS los ingredientes de cada producto top para análisis completo
    // EXCLUYENDO los ingredientes específicos: 'B001', 'B004', 'E004'
    $productos_con_ingredientes = [];
    if (!empty($top_productos_detalle)) {
        foreach ($top_productos_detalle as $producto) {
            if ($producto['CodProducto']) {
                $query_ingredientes_completo = "SELECT i.Nombre as ingrediente, i.Unidad, sr.Cantidad
                                               FROM SubReceta sr
                                               JOIN DBIngredientes i ON sr.CodIngrediente = i.CodIngrediente
                                               WHERE sr.CodBatido = :cod_producto
                                               AND i.Cocina = 'VERDADERO'
                                               AND i.CodIngrediente NOT IN ('B001', 'B004', 'E004') -- EXCLUIR ESTOS INGREDIENTES
                                               ORDER BY sr.Cantidad DESC";
                
                $stmt_completo = $db->prepare($query_ingredientes_completo);
                $stmt_completo->bindParam(':cod_producto', $producto['CodProducto']);
                $stmt_completo->execute();
                $ingredientes_del_producto = $stmt_completo->fetchAll(PDO::FETCH_ASSOC);
                
                $productos_con_ingredientes[] = [
                    'producto' => $producto,
                    'ingredientes' => $ingredientes_del_producto
                ];
            }
        }
    }

    // Debug: Información de ingredientes para diagnosticar
    // TAMBIÉN EXCLUIR los ingredientes específicos en el debug
    $debug_ingredientes = [];
    if (!empty($productos_usuario)) {
        $placeholders = str_repeat('?,', count($productos_usuario) - 1) . '?';
        $query_debug = "SELECT b.Nombre as batido, i.Nombre as ingrediente, sr.Cantidad, i.Cocina, i.CodIngrediente
                       FROM DBBatidos b
                       LEFT JOIN SubReceta sr ON b.CodBatido = sr.CodBatido
                       LEFT JOIN DBIngredientes i ON sr.CodIngrediente = i.CodIngrediente
                       WHERE b.CodBatido IN ($placeholders)
                       AND (i.CodIngrediente IS NULL OR i.CodIngrediente NOT IN ('B001', 'B004', 'E004')) -- EXCLUIR EN DEBUG TAMBIÉN
                       ORDER BY b.Nombre, i.Nombre
                       LIMIT 50";
        
        $stmt_debug = $db->prepare($query_debug);
        $stmt_debug->execute($productos_usuario);
        $debug_ingredientes = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Exception $e) {
    // En caso de error, mostrar valores por defecto
    $compras = [];
    $total_puntos = 0;
    $stats = ['total_compras' => 0, 'total_productos' => 0, 'ultima_compra' => null];
    $stats_por_tipo = [];
    $producto_favorito = null;
    $top_ingredientes = [];
    $top_productos_detalle = [];
    $productos_con_ingredientes = [];
    $debug_ingredientes = [];
    error_log("Error en dashboard: " . $e->getMessage());
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Calibri', Arial, sans-serif;
            background: var(--pitaya-light);
            min-height: 100vh;
            line-height: 1.6;
        }

        .header {
            background: var(--pitaya-gradient);
            color: var(--pitaya-white);
            padding: 20px 0;
            box-shadow: var(--shadow-medium);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 700;
            font-size: 2rem;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .user-info {
            text-align: right;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: var(--pitaya-white);
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .points-hero {
            background: linear-gradient(135deg, #FF6B6B, #FFE066);
            color: var(--pitaya-white);
            padding: 50px 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: var(--shadow-medium);
        }

        .points-number {
            font-size: 4rem;
            font-weight: 700;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .points-label {
            font-size: 1.3rem;
            opacity: 0.95;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--pitaya-white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--pitaya-secondary);
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .section {
            background: var(--pitaya-white);
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--pitaya-secondary);
            margin-bottom: 25px;
        }

        .purchases-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .purchases-table th,
        .purchases-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .purchases-table th {
            background: var(--pitaya-light);
            font-weight: 700;
            color: var(--pitaya-secondary);
        }

        .purchases-table tr:hover {
            background: rgba(81, 184, 172, 0.05);
        }

        .points-badge {
            background: var(--pitaya-gradient);
            color: var(--pitaya-white);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
            background: var(--pitaya-light);
            border-radius: 15px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .favorites-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .favorite-item {
            background: var(--pitaya-light);
            padding: 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .favorite-item:hover {
            background: #e8e8e8;
            transform: translateX(5px);
        }

        .ingredient-detail-btn:hover {
            background: var(--pitaya-secondary) !important;
            transform: translateY(-2px);
        }

        .health-analysis-content h3 {
            margin-bottom: 15px;
            color: white;
        }

        .health-analysis-content h4 {
            margin: 15px 0 10px 0;
            color: rgba(255,255,255,0.9);
        }

        .ingredient-card {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
            border-left: 4px solid rgba(255,255,255,0.5);
        }

        .benefit-list {
            margin: 10px 0;
        }

        .benefit-item {
            margin: 5px 0;
            padding-left: 15px;
            position: relative;
        }

        .benefit-item::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4ECDC4;
            font-weight: bold;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            margin: 20px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>
                <div class="logo">
                    <img src="https://batidospitaya.com/Imagenes/Logo.svg" alt="Batidos Pitaya Logo">
                </div>
                Batidos Pitaya
            </h1>
            <div class="user-info">
                <div>Bienvenido, <strong><?php echo htmlspecialchars($nombre . ' ' . $apellido); ?></strong></div>
                <div>Membresía: <strong><?php echo htmlspecialchars($membresia); ?></strong></div>
                <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="success-message">
            ¡Bienvenido al portal! El sistema está funcionando correctamente.
        </div>

        <!-- Points Hero -->
        <div class="points-hero">
            <div class="points-label">Puntos Acumulados</div>
            <div class="points-number"><?php echo number_format($total_puntos); ?></div>
            <div class="points-label">¡Sigue disfrutando tus batidos favoritos!</div>
        </div>

        <!-- Stats por Tipo -->
        <div class="stats-grid">
            <?php foreach($stats_por_tipo as $tipo_stat): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <?php 
                    $icon = '';
                    switch($tipo_stat['Tipo']) {
                        case 'Batido': $icon = '🥤'; break;
                        case 'Bowl': $icon = '🥣'; break;
                        case 'Waffles': $icon = '🧇'; break;
                        case 'Pitaya Store': $icon = '🛒'; break;
                        default: $icon = '🍽️';
                    }
                    echo $icon;
                    ?>
                </div>
                <div class="stat-number"><?php echo $tipo_stat['total_productos']; ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($tipo_stat['Tipo']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Stats Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🛍️</div>
                <div class="stat-number"><?php echo $stats['total_compras'] ?? 0; ?></div>
                <div class="stat-label">Total de Compras</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🍽️</div>
                <div class="stat-number"><?php echo $stats['total_productos'] ?? 0; ?></div>
                <div class="stat-label">Productos Consumidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number">
                    <?php 
                    if ($stats['ultima_compra']) {
                        echo date('d/m/Y', strtotime($stats['ultima_compra']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Última Compra</div>
            </div>
            <?php if ($producto_favorito): ?>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number"><?php echo $producto_favorito['total_cantidad']; ?></div>
                <div class="stat-label">Tu <?php echo htmlspecialchars($producto_favorito['Tipo']); ?> favorito:<br><strong><?php echo htmlspecialchars($producto_favorito['DBBatidos_Nombre']); ?></strong></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Purchase History -->
        <div class="section">
            <h2 class="section-title">Historial de Compras Recientes</h2>
            
            <?php if (count($compras) > 0): ?>
                <table class="purchases-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Cantidad</th>
                            <th>Puntos/Unidad</th>
                            <th>Total Puntos</th>
                            <th>Sucursal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($compra['fecha_formateada']); ?></td>
                                <td><strong><?php echo htmlspecialchars($compra['DBBatidos_Nombre']); ?></strong></td>
                                <td>
                                    <?php 
                                    if ($compra['Tipo']) {
                                        $badge_color = '';
                                        switch($compra['Tipo']) {
                                            case 'Batido': $badge_color = 'background: #51B8AC;'; break;
                                            case 'Bowl': $badge_color = 'background: #0E544C;'; break;
                                            case 'Waffles': $badge_color = 'background: #FF6B6B;'; break;
                                            case 'Pitaya Store': $badge_color = 'background: #FFE066; color: #333;'; break;
                                            default: $badge_color = 'background: #6c757d;';
                                        }
                                        echo '<span style="' . $badge_color . ' color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">' . htmlspecialchars($compra['Tipo']) . '</span>';
                                    } else {
                                        echo '<span style="background: #6c757d; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">General</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($compra['Cantidad']); ?></td>
                                <td><?php echo htmlspecialchars($compra['Puntos']); ?></td>
                                <td>
                                    <span class="points-badge">
                                        <?php echo $compra['Puntos'] * $compra['Cantidad']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($compra['Sucursal_Nombre']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>Aún no tienes compras registradas</p>
                    <p>¡Realiza tu primera compra y comienza a acumular puntos!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección de Ingredientes Favoritos -->
        <?php if (!empty($top_ingredientes)): ?>
        <div class="section">
            <h2 class="section-title">🌿 Tus Ingredientes Favoritos</h2>
            <div class="favorites-grid">
                <?php 
                $max_ingrediente = $top_ingredientes[0]['cantidad_total'] ?? 1;
                foreach ($top_ingredientes as $ingrediente): 
                    $porcentaje = ($ingrediente['cantidad_total'] / $max_ingrediente) * 100;
                ?>
                <div class="favorite-item">
                    <div style="flex: 1;">
                        <div class="favorite-name" style="font-weight: 600; color: var(--pitaya-secondary); margin-bottom: 8px;">
                            <?php echo htmlspecialchars($ingrediente['ingrediente']); ?>
                        </div>
                        <div class="favorite-stats" style="display: flex; gap: 15px; font-size: 0.9rem; color: #666;">
                            <span>📊 <?php echo number_format($ingrediente['cantidad_total'], 1); ?> <?php echo htmlspecialchars($ingrediente['Unidad']); ?></span>
                        </div>
                        <div class="progress-bar" style="width: 100%; height: 8px; background: #eee; border-radius: 4px; margin-top: 8px; overflow: hidden;">
                            <div class="progress-fill" style="height: 100%; background: var(--pitaya-gradient); border-radius: 4px; transition: width 0.6s ease; width: <?php echo $porcentaje; ?>%;"></div>
                        </div>
                    </div>
                    <button class="ingredient-detail-btn" onclick="showIngredientInfo('<?php echo htmlspecialchars($ingrediente['ingrediente']); ?>')" style="background: var(--pitaya-primary); color: white; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; margin-left: 15px;">
                        Info
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sección de Análisis Nutricional IA -->
        <div class="section" style="background: linear-gradient(135deg, var(--pitaya-primary), var(--pitaya-secondary)); color: white;">
            <h2 class="section-title" style="color: white;">🤖 Análisis Nutricional con IA</h2>
            <p style="margin-bottom: 20px; opacity: 0.9;">Obtén recomendaciones personalizadas basadas en tus ingredientes favoritos</p>
            
            <button id="generate-health-btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.3); padding: 15px 30px; border-radius: 25px; cursor: pointer; font-weight: 600; font-size: 16px; transition: all 0.3s ease;">
                <span id="health-btn-text">Generar Análisis de Salud</span>
            </button>
            
            <div id="health-analysis-content" style="display: none; margin-top: 20px; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 12px;">
                <!-- Contenido del análisis aparecerá aquí -->
            </div>
        </div>

        <!-- Personal Info -->
        <div class="section">
            <h2 class="section-title">Información Personal</h2>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($miembro['nombre'] . ' ' . $miembro['apellido']); ?></p>
            <p><strong>Membresía:</strong> <?php echo htmlspecialchars($miembro['membresia']); ?></p>
            <?php if (isset($miembro['fecha_registro'])): ?>
            <p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y', strtotime($miembro['fecha_registro'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Función de prueba para verificar si JavaScript funciona
        function testClick() {
            alert('¡El botón funciona! JavaScript está activo.');
            generateHealthAnalysis();
        }

        // Base de datos de información nutricional (simplificada)
        const nutritionDB = {
            'Mango': {
                calories: 60,
                vitamins: ['Vitamina C', 'Vitamina A', 'Folato'],
                minerals: ['Potasio', 'Cobre', 'Magnesio'],
                benefits: ['Rico en antioxidantes', 'Mejora la digestión', 'Fortalece el sistema inmune'],
                icon: '🥭'
            },
            'Fresa': {
                calories: 32,
                vitamins: ['Vitamina C', 'Folato', 'Vitamina K'],
                minerals: ['Manganeso', 'Potasio'],
                benefits: ['Potente antioxidante', 'Baja en calorías', 'Mejora la salud cardiovascular'],
                icon: '🍓'
            },
            'Plátano': {
                calories: 105,
                vitamins: ['Vitamina B6', 'Vitamina C'],
                minerals: ['Potasio', 'Manganeso'],
                benefits: ['Energía rápida', 'Mejora el rendimiento deportivo', 'Regula la presión arterial'],
                icon: '🍌'
            },
            'Pitaya': {
                calories: 60,
                vitamins: ['Vitamina C', 'Vitamina B1'],
                minerals: ['Hierro', 'Magnesio'],
                benefits: ['Superfruta antioxidante', 'Mejora la salud cardiovascular', 'Rica en fibra'],
                icon: '🐉'
            }
        };

        // Función mejorada para generar análisis con IA moderna
        async function generateHealthAnalysis() {
            const button = document.getElementById('generate-health-btn');
            const btnText = document.getElementById('health-btn-text');
            const content = document.getElementById('health-analysis-content');
            
            console.log('Iniciando análisis nutricional con modelos modernos...');
            
            btnText.innerHTML = '⏳ Conectando con IA moderna...';
            button.disabled = true;
            
            try {
                const productosConIngredientes = <?php echo json_encode($productos_con_ingredientes); ?>;
                
                console.log('Productos con ingredientes:', productosConIngredientes);
                
                if (!productosConIngredientes || productosConIngredientes.length === 0) {
                    content.innerHTML = `
                        <div class="no-analysis">
                            <h3>🤔 Análisis No Disponible</h3>
                            <p>Necesitas más compras para generar un análisis nutricional personalizado.</p>
                            <p>Realiza algunas compras de nuestros productos principales para obtener recomendaciones detalladas!</p>
                        </div>
                    `;
                    content.style.display = 'block';
                    btnText.innerHTML = 'Generar Análisis de Salud';
                    button.disabled = false;
                    return;
                }
                
                // Mostrar productos mientras se procesa
                let analysisHTML = '<h3>🌟 Análisis Nutricional por Producto</h3>';
                analysisHTML += `<p style="opacity: 0.9; margin-bottom: 20px;">Analizando ${productosConIngredientes.length} productos favoritos...</p>`;
                
                // Mostrar productos con ingredientes básicos
                analysisHTML += '<div class="productos-preview">';
                productosConIngredientes.forEach((item, index) => {
                    const producto = item.producto;
                    const ingredientes = item.ingredientes || [];
                    
                    // Filtrar ingredientes excluidos también en el frontend por seguridad
                    const ingredientesFiltrados = ingredientes.filter(ing => 
                        !['B001', 'B004', 'E004'].includes(ing.CodIngrediente || '')
                    );
                    
                    analysisHTML += `
                        <div class="producto-preview">
                            <h4>🥤 ${producto.DBBatidos_Nombre}</h4>
                            <div class="producto-stats">
                                <span>📊 ${producto.total_cantidad} consumidos</span>
                                <span>🔄 ${producto.veces_comprado} pedidos</span>
                                <span>⭐ ${Math.round(producto.puntos_promedio || 0)} pts promedio</span>
                            </div>
                            <div class="ingredientes-count">
                                🌿 ${ingredientesFiltrados.length} ingredientes de cocina (excluyendo B001, B004, E004)
                            </div>
                            ${ingredientesFiltrados.length > 0 ? 
                                `<div class="ingredientes-list" style="margin-top: 8px; font-size: 0.85rem; opacity: 0.8;">
                                    ${ingredientesFiltrados.map(ing => `• ${ing.ingrediente}`).join('<br>')}
                                </div>` : 
                                '<div style="color: #FFE066; font-size: 0.85rem; margin-top: 8px;">⚠️ Solo ingredientes básicos después del filtro</div>'
                            }
                        </div>
                    `;
                });
                analysisHTML += '</div>';
                
                content.innerHTML = analysisHTML;
                content.style.display = 'block';
                
                // Llamar a la API de IA moderna
                btnText.innerHTML = '🤖 Generando análisis con IA...';
                
                const aiResponse = await fetch('ai_nutrition_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        productos_con_ingredientes: productosConIngredientes,
                        ingredientes_excluidos: ['B001', 'B004', 'E004'] // Informar a la API sobre la exclusión
                    })
                });
                
                if (!aiResponse.ok) {
                    throw new Error(`Error HTTP: ${aiResponse.status}`);
                }
                
                const aiData = await aiResponse.json();
                
                if (aiData.success) {
                    // Agregar el análisis de IA al contenido existente
                    analysisHTML += `
                        <div class="ai-analysis-section">
                            <div class="ai-header">
                                <h4>🤖 Análisis Nutricional ${aiData.hasAI ? 'con IA Avanzada' : 'Básico'}</h4>
                                ${aiData.hasAI ? 
                                    '<div class="ai-badge success">✅ Powered by llama-3.1-8b-instant</div>' : 
                                    '<div class="ai-badge basic">📊 Análisis básico (configurar API para IA avanzada)</div>'
                                }
                            </div>
                            <div class="filter-info" style="background: rgba(255,193,7,0.1); padding: 10px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #FFC107;">
                                <strong>🔍 Filtro aplicado:</strong> Ingredientes B001, B004, E004 excluidos del análisis
                            </div>
                            <div class="analysis-content">
                                ${aiData.analysis}
                            </div>
                        </div>
                    `;
                    
                    // Mostrar notificación de éxito
                    if (aiData.hasAI) {
                        showNotification('✅ Análisis generado con IA moderna (ingredientes excluidos)', 'success');
                    } else {
                        showNotification('📊 Análisis básico generado (IA no configurada)', 'info');
                    }
                } else {
                    throw new Error(aiData.error || 'Error desconocido en análisis');
                }
                
                content.innerHTML = analysisHTML;
                btnText.innerHTML = '🔄 Actualizar Análisis';
                button.disabled = false;
                
            } catch (error) {
                console.error('Error en análisis:', error);
                
                content.innerHTML = `
                    <div class="error-analysis">
                        <h4>❌ Error en el Análisis</h4>
                        <p>Hubo un problema técnico. El análisis básico estará disponible pronto.</p>
                        <details style="margin-top: 10px; opacity: 0.8;">
                            <summary>Detalles técnicos</summary>
                            <code>${error.message}</code>
                        </details>
                    </div>
                `;
                content.style.display = 'block';
                btnText.innerHTML = 'Reintentar Análisis';
                button.disabled = false;
                
                showNotification('⚠️ Error en análisis - Intenta nuevamente', 'error');
            }
        }

        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;font-size:18px;cursor:pointer;margin-left:10px;">&times;</button>
            `;
            
            // Estilos según tipo
            const styles = {
                success: 'background: #d4edda; color: #155724; border-left: 4px solid #28a745;',
                error: 'background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545;',
                info: 'background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8;'
            };
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                z-index: 1000;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                ${styles[type]}
                animation: slideIn 0.3s ease-out;
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOut 0.3s ease-in forwards';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // CSS para animaciones de notificaciones
        const notificationStyles = document.createElement('style');
        notificationStyles.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .ai-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-left: 10px;
            }
            .ai-badge.success {
                background: rgba(40, 167, 69, 0.2);
                color: #155724;
            }
            .ai-badge.basic {
                background: rgba(255, 193, 7, 0.2);
                color: #856404;
            }
            .productos-preview {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .producto-preview {
                background: rgba(255,255,255,0.1);
                padding: 15px;
                border-radius: 12px;
                border-left: 4px solid #FFE066;
            }
            .producto-stats {
                display: flex;
                gap: 15px;
                margin: 8px 0;
                font-size: 0.9rem;
                flex-wrap: wrap;
            }
            .ingredientes-count {
                font-size: 0.9rem;
                opacity: 0.9;
                margin-top: 8px;
            }
            .ai-analysis-section {
                margin-top: 30px;
                padding: 25px;
                background: rgba(255,255,255,0.05);
                border-radius: 15px;
                border: 1px solid rgba(255,255,255,0.1);
            }
            .ai-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            .analysis-content {
                line-height: 1.6;
            }
            .producto-analysis {
                background: rgba(255,255,255,0.08);
                padding: 20px;
                border-radius: 12px;
                margin: 15px 0;
                border-left: 4px solid #4ECDC4;
            }
            .no-analysis, .error-analysis {
                text-align: center;
                padding: 40px 20px;
                border-radius: 15px;
            }
            .no-analysis {
                background: rgba(255,255,255,0.1);
            }
            .error-analysis {
                background: rgba(255,107,107,0.1);
            }
        `;
        document.head.appendChild(notificationStyles);

        // Función de debug para verificar ingredientes
        function showDebugInfo() {
            const debugContent = document.getElementById('debug-content');
            const debugData = <?php echo json_encode($debug_ingredientes); ?>;
            const productosUsuario = <?php echo json_encode($productos_usuario); ?>;
            
            let debugHTML = '<h3>🔍 Información de Debug</h3>';
            debugHTML += `<p><strong>Productos del usuario encontrados:</strong> ${productosUsuario.length}</p>`;
            debugHTML += `<p><strong>Códigos de productos:</strong> ${productosUsuario.join(', ')}</p>`;
            debugHTML += `<p><strong>Registros de ingredientes:</strong> ${debugData.length}</p>`;
            
            if (debugData.length > 0) {
                debugHTML += '<h4>Ingredientes por producto:</h4>';
                debugHTML += '<div style="max-height: 300px; overflow-y: auto;">';
                
                const groupedByBatido = {};
                debugData.forEach(item => {
                    if (!groupedByBatido[item.batido]) {
                        groupedByBatido[item.batido] = [];
                    }
                    groupedByBatido[item.batido].push(item);
                });
                
                for (const [batido, ingredientes] of Object.entries(groupedByBatido)) {
                    debugHTML += `
                        <div style="background: rgba(255,255,255,0.05); padding: 10px; margin: 10px 0; border-radius: 8px;">
                            <strong>${batido}</strong><br>
                            ${ingredientes.map(ing => 
                                ing.ingrediente ? `• ${ing.ingrediente} (${ing.Cantidad || 'N/A'})` : '• Sin ingredientes registrados'
                            ).join('<br>')}
                        </div>
                    `;
                }
                debugHTML += '</div>';
            } else {
                debugHTML += '<p style="color: #FFE066;"><strong>❌ No se encontraron ingredientes registrados</strong></p>';
                debugHTML += '<p>Esto significa que los productos en SubReceta no están conectados con DBIngredientes.</p>';
            }
            
            debugContent.innerHTML = debugHTML;
            debugContent.style.display = 'block';
        }

        // Modal functions para ingredientes
        function showIngredientInfo(ingredient) {
            const info = nutritionDB[ingredient] || {
                calories: 50,
                vitamins: ['Vitaminas naturales'],
                minerals: ['Minerales esenciales'],
                benefits: ['Aporta nutrientes', 'Sabor natural'],
                icon: '🌱'
            };

            alert(`${info.icon} ${ingredient}\n\nCalorías: ${info.calories}\nVitaminas: ${info.vitamins.join(', ')}\nBeneficios: ${info.benefits.join(', ')}`);
        }

        // Animación de contadores (simplificada)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado, inicializando...');
            
            // Agregar event listener al botón de análisis de salud
            const healthBtn = document.getElementById('generate-health-btn');
            if (healthBtn) {
                healthBtn.addEventListener('click', function() {
                    console.log('Botón clickeado');
                    generateHealthAnalysis();
                });
            }
            
            const counters = document.querySelectorAll('.stat-number');
            console.log('Contadores encontrados:', counters.length);
            
            counters.forEach(counter => {
                const text = counter.innerText;
                const number = parseInt(text.replace(/,/g, ''));
                
                if (isNaN(number) || number === 0) return;

                let current = 0;
                const increment = Math.max(1, number / 30);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= number) {
                        counter.innerText = number.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.innerText = Math.floor(current).toLocaleString();
                    }
                }, 50);
            });
        });
    </script>
</body>
</html>
