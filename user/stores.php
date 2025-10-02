<?php
require_once 'config/auth.php';
require_once 'config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener balance del usuario
$stmt = $db->prepare("
    SELECT u.username, u.email, w.balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener todas las tiendas (vendedores verificados)
$stmt = $db->prepare("
    SELECT u.*, 
           COUNT(p.id) as product_count,
           AVG(r.rating) as avg_rating,
           w.balance as vendor_balance
    FROM users u 
    LEFT JOIN products p ON u.id = p.vendor_id AND p.is_active = TRUE
    LEFT JOIN reviews r ON u.id = r.vendor_id
    LEFT JOIN wallets w ON u.id = w.user_id
    WHERE u.role = 'vendor' AND u.is_verified = TRUE
    GROUP BY u.id
    ORDER BY u.store_name ASC
");
$stmt->execute();
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda y filtros
$search = $_GET['search'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

if ($search || $platform_filter) {
    $filtered_stores = [];
    foreach ($stores as $store) {
        $matches_search = !$search || 
                         stripos($store['store_name'], $search) !== false || 
                         stripos($store['username'], $search) !== false;
        
        $matches_platform = !$platform_filter;
        
        if ($matches_search && $matches_platform) {
            $filtered_stores[] = $store;
        }
    }
    $stores = $filtered_stores;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiendas - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .store-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .store-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .store-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        .rating-stars {
            color: #ffc107;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
    </style>
</head>
<body>
    <!-- Navbar Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="user/dashboard.php">
                <i class="fas fa-play-circle me-2"></i>Fluxa
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $user_data['username']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="user/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="user/buy_coins.php"><i class="fas fa-coins me-2"></i>Comprar Coins</a></li>
                        <li><a class="dropdown-item" href="user/orders.php"><i class="fas fa-shopping-cart me-2"></i>Mis Compras</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Balance y Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><i class="fas fa-store me-2"></i>Tiendas Disponibles</h1>
                <p class="text-muted">Explora todas las tiendas verificadas en Fluxa</p>
            </div>
            <div class="col-md-4">
                <div class="balance-card p-3 text-center">
                    <h4 class="mb-1">$<?php echo number_format($user_data['balance'], 2); ?></h4>
                    <p class="mb-0">Tu Balance de Coins</p>
                    <small>
                        <a href="user/buy_coins.php" class="text-warning text-decoration-none">
                            <i class="fas fa-plus-circle me-1"></i>Recargar
                        </a>
                    </small>
                </div>
            </div>
        </div>

        <!-- Barra de Búsqueda y Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Buscar tienda por nombre..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="platform">
                            <option value="">Todas las plataformas</option>
                            <option value="netflix" <?php echo $platform_filter == 'netflix' ? 'selected' : ''; ?>>Netflix</option>
                            <option value="disney" <?php echo $platform_filter == 'disney' ? 'selected' : ''; ?>>Disney+</option>
                            <option value="spotify" <?php echo $platform_filter == 'spotify' ? 'selected' : ''; ?>>Spotify</option>
                            <option value="hbo" <?php echo $platform_filter == 'hbo' ? 'selected' : ''; ?>>HBO Max</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo count($stores); ?></h3>
                        <p class="text-muted mb-0">Tiendas Activas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">
                            <?php echo array_sum(array_column($stores, 'product_count')); ?>
                        </h3>
                        <p class="text-muted mb-0">Productos Totales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">
                            <?php 
                            $rated_stores = array_filter($stores, function($store) {
                                return $store['avg_rating'] > 0;
                            });
                            echo count($rated_stores);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Tiendas Calificadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info">
                            <?php echo number_format(array_sum(array_column($stores, 'vendor_balance')), 2); ?>
                        </h3>
                        <p class="text-muted mb-0">Coins en Circulación</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Tiendas -->
        <div class="row">
            <?php if (count($stores) > 0): ?>
                <?php foreach ($stores as $store): ?>
                    <div class="col-xl-4 col-lg-6 mb-4">
                        <div class="store-card p-4 text-center">
                            <!-- Logo de la Tienda -->
                            <div class="store-logo">
                                <?php echo strtoupper(substr($store['store_name'] ?: $store['username'], 0, 1)); ?>
                            </div>
                            
                            <!-- Información de la Tienda -->
                            <h4 class="fw-bold"><?php echo $store['store_name'] ?: $store['username']; ?></h4>
                            
                            <?php if ($store['description']): ?>
                                <p class="text-muted"><?php echo $store['description']; ?></p>
                            <?php endif; ?>
                            
                            <!-- Estadísticas -->
                            <div class="d-flex justify-content-center gap-3 mb-3">
                                <span class="badge bg-primary">
                                    <i class="fas fa-box me-1"></i>
                                    <?php echo $store['product_count']; ?> productos
                                </span>
                                
                                <?php if ($store['avg_rating']): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-star me-1"></i>
                                        <?php echo number_format($store['avg_rating'], 1); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Vendedor -->
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    Vendedor: <?php echo $store['username']; ?>
                                </small>
                            </div>
                            
                            <!-- Estado -->
                            <div class="mb-3">
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Verificado
                                </span>
                                <?php if ($store['vendor_balance'] > 0): ?>
                                    <span class="badge bg-info ms-1">
                                        <i class="fas fa-coins me-1"></i>
                                        Activo
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Acciones -->
                            <div class="d-grid gap-2">
                                <a href="store_products.php?vendor_id=<?php echo $store['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>Ver Productos
                                </a>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-envelope me-2"></i>Contactar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-store fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No se encontraron tiendas</h4>
                    <p class="text-muted">
                        <?php if ($search || $platform_filter): ?>
                            Intenta con otros términos de búsqueda
                        <?php else: ?>
                            No hay tiendas registradas en este momento
                        <?php endif; ?>
                    </p>
                    <?php if ($search || $platform_filter): ?>
                        <a href="stores.php" class="btn btn-primary">
                            <i class="fas fa-times me-2"></i>Limpiar Filtros
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navegación Inferior -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="user/dashboard.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-tachometer-alt me-2"></i>Mi Dashboard
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="user/buy_coins.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-coins me-2"></i>Comprar Coins
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="user/orders.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-shopping-cart me-2"></i>Mis Compras
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="user/profile.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-user me-2"></i>Mi Perfil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-play-circle me-2"></i>Fluxa</h5>
                    <p class="mb-0">Marketplace de cuentas de streaming</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">
                        Balance actual: 
                        <strong class="text-warning">$<?php echo number_format($user_data['balance'], 2); ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Actualizar balance en tiempo real (simulado)
        function updateBalance() {
            // En una implementación real, harías una petición AJAX
            console.log("Balance actualizado");
        }
        
        // Mostrar notificación de balance bajo
        document.addEventListener('DOMContentLoaded', function() {
            const balance = <?php echo $user_data['balance']; ?>;
            if (balance < 10) {
                const alert = document.createElement('div');
                alert.className = 'alert alert-warning alert-dismissible fade show';
                alert.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Balance bajo:</strong> Tienes solo $${balance.toFixed(2)} coins. 
                    <a href="user/buy_coins.php" class="alert-link">Recarga ahora</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container').insertBefore(alert, document.querySelector('.container').firstChild);
            }
        });
    </script>
</body>
</html>