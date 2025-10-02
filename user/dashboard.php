<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener datos del usuario
$stmt = $db->prepare("
    SELECT u.*, w.balance, w.pending_balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener órdenes recientes del usuario
$stmt = $db->prepare("
    SELECT o.*, u.username as vendor_name 
    FROM orders o 
    JOIN users u ON o.vendor_id = u.id 
    WHERE o.buyer_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener transacciones recientes
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .wallet-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Usuario -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-play-circle me-2"></i>Fluxa</h4>
                        <small>Mi Cuenta</small>
                        <div class="mt-3">
                            <img src="<?php echo $user_data['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user_data['username']); ?>" 
                                 class="rounded-circle" width="80" height="80">
                            <h6 class="mt-2"><?php echo $user_data['username']; ?></h6>
                            <small><?php echo $user_data['email']; ?></small>
                        </div>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="buy_coins.php">
                                <i class="fas fa-coins me-2"></i>Comprar Coins
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Mis Compras
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">
                                <i class="fas fa-exchange-alt me-2"></i>Transacciones
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
                <h1>Mi Dashboard</h1>
                <p class="text-muted">Bienvenido de vuelta, <?php echo $user_data['username']; ?></p>

                <!-- Wallet y Balance -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card wallet-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4>$<?php echo number_format($user_data['balance'], 2); ?></h4>
                                        <p class="mb-0">Balance Disponible</p>
                                    </div>
                                    <div>
                                        <i class="fas fa-wallet fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <a href="buy_coins.php" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-plus-circle me-2"></i>Comprar Más Coins
                                </a>
                                <small class="text-muted">1 Coin = $1.00 USD</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Órdenes Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>Mis Compras Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_orders) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_orders as $order): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>Orden #<?php echo $order['id']; ?></strong>
                                                        <br>
                                                        <small>Vendedor: <?php echo $order['vendor_name']; ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                        <br>
                                                        <span class="badge bg-<?php 
                                                            switch($order['status']) {
                                                                case 'completed': echo 'success'; break;
                                                                case 'pending': echo 'warning'; break;
                                                                case 'cancelled': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="orders.php" class="btn btn-outline-primary btn-sm">Ver Todas</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No has realizado compras aún</p>
                                        <a href="../index.php" class="btn btn-primary">Explorar Tiendas</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Transacciones Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exchange-alt me-2"></i>Transacciones Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_transactions) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo ucfirst($transaction['type']); ?></strong>
                                                        <br>
                                                        <small><?php echo $transaction['description']; ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong class="<?php echo $transaction['type'] == 'purchase' ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $transaction['type'] == 'purchase' ? '+' : '-'; ?>
                                                            $<?php echo number_format($transaction['amount'], 2); ?>
                                                        </strong>
                                                        <br>
                                                        <span class="badge bg-<?php 
                                                            switch($transaction['status']) {
                                                                case 'completed': echo 'success'; break;
                                                                case 'pending': echo 'warning'; break;
                                                                case 'cancelled': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No hay transacciones recientes</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="../index.php" class="btn btn-outline-primary w-100 py-3">
                                            <i class="fas fa-store fa-2x mb-2"></i><br>
                                            Explorar Tiendas
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="buy_coins.php" class="btn btn-outline-success w-100 py-3">
                                            <i class="fas fa-coins fa-2x mb-2"></i><br>
                                            Comprar Coins
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="orders.php" class="btn btn-outline-info w-100 py-3">
                                            <i class="fas fa-history fa-2x mb-2"></i><br>
                                            Historial Compras
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="profile.php" class="btn btn-outline-warning w-100 py-3">
                                            <i class="fas fa-cog fa-2x mb-2"></i><br>
                                            Mi Perfil
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