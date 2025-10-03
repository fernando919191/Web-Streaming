<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener datos del usuario CON JOIN a wallets
$stmt = $db->prepare("
    SELECT 
        u.*, 
        COALESCE(w.balance, 0) as wallet_balance,
        COALESCE(w.pending_balance, 0) as pending_balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no tiene wallet, crear uno
if (!$user_data || $user_data['wallet_balance'] === null) {
    $stmt = $db->prepare("INSERT IGNORE INTO wallets (user_id, balance) VALUES (?, 0.00)");
    $stmt->execute([$user_id]);
    
    // Volver a obtener datos
    $stmt = $db->prepare("
        SELECT 
            u.*, 
            COALESCE(w.balance, 0) as wallet_balance,
            COALESCE(w.pending_balance, 0) as pending_balance 
        FROM users u 
        LEFT JOIN wallets w ON u.id = w.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener transacciones recientes
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si hay transacciones recién aprobadas para mostrar notificación
$new_approved = array_filter($recent_transactions, function($transaction) {
    return $transaction['status'] == 'completed' && 
           strtotime($transaction['updated_at']) > (time() - 3600);
});

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
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
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
        .wallet-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        .balance-update {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
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
                            <img src="<?php echo $user_data['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user_data['username']) . '&background=6f42c1&color=ffffff'; ?>" 
                                 class="rounded-circle" width="80" height="80" style="border: 3px solid white;">
                            <h6 class="mt-2"><?php echo $user_data['username']; ?></h6>
                            <small><?php echo $user_data['email']; ?></small>
                            <div class="mt-2">
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-coins me-1"></i>
                                    $<?php echo number_format($user_data['wallet_balance'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item position-relative">
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
                            <a class="nav-link" href="stores.php">
                                <i class="fas fa-store me-2"></i>Tiendas
                            </a>
                        </li>
                        <li class="nav-item position-relative">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Mis Compras
                                <?php if (count($recent_orders) > 0): ?>
                                    <span class="notification-badge"><?php echo count($recent_orders); ?></span>
                                <?php endif; ?>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Mi Dashboard</h1>
                        <p class="text-muted mb-0">Bienvenido de vuelta, <?php echo $user_data['username']; ?></p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Última actualización: <?php echo date('H:i:s'); ?></small>
                        <br>
                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="refreshBalance()">
                            <i class="fas fa-sync-alt me-1"></i>Actualizar
                        </button>
                    </div>
                </div>

                <!-- Notificación de coins aprobados -->
                <?php if (count($new_approved) > 0): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <h5 class="alert-heading mb-1">¡Coins Acreditados!</h5>
                                <p class="mb-0">Tu compra de coins ha sido aprobada y ya están disponibles en tu balance.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Wallet y Balance -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card wallet-card balance-update" id="balanceCard">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 id="currentBalance">$<?php echo number_format($user_data['wallet_balance'], 2); ?></h4>
                                        <p class="mb-0">Balance Disponible</p>
                                        <small id="balanceTimestamp" class="opacity-75">
                                            <i class="fas fa-clock me-1"></i>
                                            <span id="timestamp">Actualizado: <?php echo date('H:i:s'); ?></span>
                                        </small>
                                    </div>
                                    <div>
                                        <i class="fas fa-wallet fa-3x opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light h-100">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <a href="buy_coins.php" class="btn btn-primary btn-lg w-100 mb-2">
                                    <i class="fas fa-plus-circle me-2"></i>Comprar Más Coins
                                </a>
                                <a href="stores.php" class="btn btn-success btn-lg w-100 mb-2">
                                    <i class="fas fa-store me-2"></i>Ir de Compras
                                </a>
                                <small class="text-muted">1 Coin = $1.00 USD</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Transacciones Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exchange-alt me-2"></i>Transacciones Recientes
                                </h5>
                                <span class="badge bg-primary"><?php echo count($recent_transactions); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_transactions) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong class="<?php echo $transaction['type'] == 'purchase' ? 'text-success' : 'text-primary'; ?>">
                                                                <?php echo ucfirst($transaction['type']); ?>
                                                            </strong>
                                                            <span class="fw-bold">$<?php echo number_format($transaction['amount'], 2); ?></span>
                                                        </div>
                                                        <small class="text-muted d-block">
                                                            <?php echo $transaction['description']; ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-end">
                                                    <span class="badge bg-<?php 
                                                        switch($transaction['status']) {
                                                            case 'completed': echo 'success'; break;
                                                            case 'pending': echo 'warning'; break;
                                                            case 'rejected': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($transaction['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="transactions.php" class="btn btn-outline-primary btn-sm">Ver Todas las Transacciones</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">No hay transacciones recientes</h6>
                                        <p class="text-muted small">Todas tus compras y ventas aparecerán aquí</p>
                                        <a href="buy_coins.php" class="btn btn-primary">Realizar Mi Primera Compra</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Órdenes Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>Mis Compras Recientes
                                </h5>
                                <span class="badge bg-success"><?php echo count($recent_orders); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_orders) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_orders as $order): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <strong>Orden #<?php echo $order['id']; ?></strong>
                                                            <span class="fw-bold text-success">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                                        </div>
                                                        <small class="text-muted d-block">
                                                            <i class="fas fa-store me-1"></i>Vendedor: <?php echo $order['vendor_name']; ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-end">
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
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="orders.php" class="btn btn-outline-success btn-sm">Ver Todas las Compras</a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">No has realizado compras</h6>
                                        <p class="text-muted small">Tus compras en tiendas aparecerán aquí</p>
                                        <a href="stores.php" class="btn btn-success">Explorar Tiendas</a>
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
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <a href="stores.php" class="btn btn-outline-primary w-100 py-3">
                                            <i class="fas fa-store fa-2x mb-2"></i><br>
                                            <small>Tiendas</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <a href="buy_coins.php" class="btn btn-outline-success w-100 py-3">
                                            <i class="fas fa-coins fa-2x mb-2"></i><br>
                                            <small>Comprar Coins</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <a href="orders.php" class="btn btn-outline-info w-100 py-3">
                                            <i class="fas fa-history fa-2x mb-2"></i><br>
                                            <small>Mis Compras</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <a href="transactions.php" class="btn btn-outline-warning w-100 py-3">
                                            <i class="fas fa-exchange-alt fa-2x mb-2"></i><br>
                                            <small>Transacciones</small>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <button class="btn btn-outline-secondary w-100 py-3" onclick="refreshBalance()">
                                            <i class="fas fa-sync-alt fa-2x mb-2"></i><br>
                                            <small>Actualizar</small>
                                        </button>
                                    </div>
                                    <div class="col-md-2 col-6 text-center mb-3">
                                        <a href="profile.php" class="btn btn-outline-dark w-100 py-3">
                                            <i class="fas fa-cog fa-2x mb-2"></i><br>
                                            <small>Mi Perfil</small>
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
    
    <script>
        // Función para actualizar el balance
        function refreshBalance() {
            const balanceCard = document.getElementById('balanceCard');
            const currentBalance = document.getElementById('currentBalance');
            const balanceTimestamp = document.getElementById('timestamp');
            
            // Mostrar loading
            balanceCard.classList.add('balance-update');
            currentBalance.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            
            // Hacer petición AJAX para obtener balance actualizado
            fetch('get_balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar balance
                        currentBalance.textContent = '$' + parseFloat(data.balance).toFixed(2);
                        balanceTimestamp.textContent = 'Actualizado: ' + new Date().toLocaleTimeString();
                        
                        // Efecto visual de actualización
                        balanceCard.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
                        setTimeout(() => {
                            balanceCard.style.background = '';
                        }, 1000);
                        
                        // Mostrar notificación si hay cambio
                        if (data.previous_balance !== null && data.balance > data.previous_balance) {
                            showBalanceNotification(data.balance - data.previous_balance);
                        }
                    } else {
                        currentBalance.textContent = 'Error';
                        balanceTimestamp.textContent = 'Error al actualizar';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    currentBalance.textContent = '$<?php echo number_format($user_data['wallet_balance'], 2); ?>';
                    balanceTimestamp.textContent = 'Error al actualizar';
                })
                .finally(() => {
                    balanceCard.classList.remove('balance-update');
                });
        }

        // Función para mostrar notificación de nuevo balance
        function showBalanceNotification(amount) {
            // Crear notificación toast
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '1060';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-coins me-2"></i>
                        <strong>¡Nuevos Coins!</strong> Se agregaron $${amount.toFixed(2)} a tu balance.
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Mostrar toast
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remover después de cerrar
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        // Actualizar balance automáticamente cada 30 segundos
        setInterval(refreshBalance, 30000);

        // Actualizar al hacer foco en la ventana
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshBalance();
            }
        });

        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>