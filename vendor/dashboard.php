<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkVendor();

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas del vendedor
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        (SELECT COUNT(*) FROM orders WHERE vendor_id = ? AND status = 'completed') as total_sales,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE vendor_id = ? AND status = 'completed') as total_revenue
    FROM products 
    WHERE vendor_id = ? AND is_active = TRUE
");
$stmt->execute([$user_id, $user_id, $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener órdenes recientes
$stmt = $db->prepare("
    SELECT o.*, u.username as buyer_name 
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    WHERE o.vendor_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Vendedor - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-play-circle me-2"></i>Fluxa</h4>
                        <small>Panel Vendedor</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Mis Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_product.php">
                                <i class="fas fa-plus-circle me-2"></i>Agregar Producto
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Órdenes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="wallet.php">
                                <i class="fas fa-wallet me-2"></i>Mi Wallet
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Mi Perfil
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Dashboard del Vendedor</h1>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-user me-2"></i>Bienvenido, <?php echo $_SESSION['username']; ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_products']; ?></h4>
                                        <p>Productos Activos</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-box fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_stock']; ?></h4>
                                        <p>Stock Total</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-layer-group fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_sales']; ?></h4>
                                        <p>Ventas Totales</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>$<?php echo number_format($stats['total_revenue'], 2); ?></h4>
                                        <p>Ingresos Totales</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Órdenes Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_orders) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID Orden</th>
                                                    <th>Comprador</th>
                                                    <th>Total</th>
                                                    <th>Estado</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>#<?php echo $order['id']; ?></td>
                                                        <td><?php echo $order['buyer_name']; ?></td>
                                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                switch($order['status']) {
                                                                    case 'pending': echo 'warning'; break;
                                                                    case 'paid': echo 'info'; break;
                                                                    case 'delivered': echo 'success'; break;
                                                                    case 'cancelled': echo 'danger'; break;
                                                                    default: echo 'secondary';
                                                                }
                                                            ?>">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                        <td>
                                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No hay órdenes recientes</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <a href="add_product.php" class="btn btn-primary btn-lg w-100 py-3">
                                            <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                                            Agregar Producto
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <a href="products.php" class="btn btn-success btn-lg w-100 py-3">
                                            <i class="fas fa-edit fa-2x mb-2"></i><br>
                                            Gestionar Productos
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <a href="wallet.php" class="btn btn-info btn-lg w-100 py-3">
                                            <i class="fas fa-wallet fa-2x mb-2"></i><br>
                                            Ver Mi Wallet
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <a href="profile.php" class="btn btn-warning btn-lg w-100 py-3">
                                            <i class="fas fa-store fa-2x mb-2"></i><br>
                                            Mi Tienda
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>