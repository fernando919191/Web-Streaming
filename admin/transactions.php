<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkAdmin();

$database = new Database();
$db = $database->getConnection();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_transaction'])) {
        $transaction_id = $_POST['transaction_id'];
        $action = 'approve';
    } elseif (isset($_POST['reject_transaction'])) {
        $transaction_id = $_POST['transaction_id'];
        $action = 'reject';
        $reject_reason = $_POST['reject_reason'] ?? '';
    }
    
    if (isset($transaction_id) && isset($action)) {
        processTransaction($db, $transaction_id, $action, $reject_reason ?? '');
    }
}

// Función para procesar transacciones - ¡CORREGIDA!
function processTransaction($db, $transaction_id, $action, $reason = '') {
    try {
        $db->beginTransaction();
        
        // Obtener información de la transacción
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception('Transacción no encontrada o ya procesada');
        }
        
        if ($action == 'approve') {
            // 1. Primero verificar que el usuario tenga wallet
            $stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $stmt->execute([$transaction['user_id']]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                // Si no tiene wallet, crear uno
                $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
                $stmt->execute([$transaction['user_id']]);
            }
            
            // 2. Aprobar transacción
            $stmt = $db->prepare("UPDATE transactions SET status = 'completed', admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute(["Aprobado por administrador", $transaction_id]);
            
            // 3. ✅ ACTUALIZAR WALLET DEL USUARIO - ¡ESTO ES LO MÁS IMPORTANTE!
            $stmt = $db->prepare("UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$transaction['amount'], $transaction['user_id']]);
            
            // 4. Obtener información para el mensaje
            $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$transaction['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success_message = "✅ Transacción #$transaction_id aprobada. {$transaction['amount']} coins agregados a {$user['username']}.";
            
        } elseif ($action == 'reject') {
            // Rechazar transacción
            $stmt = $db->prepare("UPDATE transactions SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute(["Rechazado: " . $reason, $transaction_id]);
            
            $success_message = "❌ Transacción #$transaction_id rechazada.";
        }
        
        $db->commit();
        $_SESSION['success'] = $success_message;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: transactions.php");
    exit();
}

// Obtener filtros
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Construir consulta con filtros
$where_conditions = ["1=1"];
$params = [];

if ($status_filter != 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($type_filter != 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR t.reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where_conditions);

// Obtener transacciones
$stmt = $db->prepare("
    SELECT t.*, u.username, u.email, u.role,
           COALESCE(w.balance, 0) as user_balance
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN wallets w ON u.id = w.user_id 
    WHERE $where_sql
    ORDER BY t.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_approved
    FROM transactions
    WHERE type = 'purchase'
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Transacciones - Fluxa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            color: white;
        }
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .transaction-row {
            transition: all 0.3s ease;
        }
        .transaction-row:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .balance-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-crown me-2"></i>Fluxa Admin</h4>
                        <span class="badge bg-danger">Administrador</span>
                        <div class="mt-2">
                            <small><?php echo $_SESSION['username']; ?></small>
                        </div>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="transactions.php">
                                <i class="fas fa-exchange-alt me-2"></i>Transacciones
                                <?php if ($stats['pending'] > 0): ?>
                                    <span class="badge bg-danger float-end"><?php echo $stats['pending']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="coins.php">
                                <i class="fas fa-coins me-2"></i>Gestión de Coins
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reportes
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
                <!-- Alertas -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-exchange-alt me-2"></i>Gestión de Transacciones</h1>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>Administra las solicitudes de compra de coins
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-primary">
                            <div class="card-body text-center">
                                <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                                <p class="text-muted mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-warning">
                            <div class="card-body text-center">
                                <h4 class="text-warning"><?php echo $stats['pending']; ?></h4>
                                <p class="text-muted mb-0">Pendientes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-success">
                            <div class="card-body text-center">
                                <h4 class="text-success"><?php echo $stats['completed']; ?></h4>
                                <p class="text-muted mb-0">Aprobadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-danger">
                            <div class="card-body text-center">
                                <h4 class="text-danger"><?php echo $stats['rejected']; ?></h4>
                                <p class="text-muted mb-0">Rechazadas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-8 mb-3">
                        <div class="card stats-card border-info">
                            <div class="card-body text-center">
                                <h4 class="text-info">$<?php echo number_format($stats['total_approved'], 2); ?></h4>
                                <p class="text-muted mb-0">Total Aprobado</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filtros y Búsqueda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completadas</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rechazadas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="type">
                                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>Todos los tipos</option>
                                    <option value="purchase" <?php echo $type_filter == 'purchase' ? 'selected' : ''; ?>>Compra de Coins</option>
                                    <option value="sale" <?php echo $type_filter == 'sale' ? 'selected' : ''; ?>>Venta</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Usuario, email o referencia..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Transacciones -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista de Transacciones
                            <span class="badge bg-light text-dark float-end"><?php echo count($transactions); ?> registros</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($transactions) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Tipo</th>
                                            <th>Monto</th>
                                            <th>Balance</th>
                                            <th>Referencia</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr class="transaction-row">
                                                <td>
                                                    <small class="text-muted">#<?php echo $transaction['id']; ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo $transaction['username']; ?></strong>
                                                        <?php if ($transaction['role'] == 'admin'): ?>
                                                            <span class="badge bg-danger status-badge">ADMIN</span>
                                                        <?php elseif ($transaction['role'] == 'vendor'): ?>
                                                            <span class="badge bg-warning status-badge">VENDEDOR</span>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $transaction['email']; ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $transaction['type'] == 'purchase' ? 'primary' : 'success'; ?>">
                                                        <?php echo $transaction['type'] == 'purchase' ? 'COMPRA' : 'VENTA'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-success">$<?php echo number_format($transaction['amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge balance-badge">
                                                        $<?php echo number_format($transaction['user_balance'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo $transaction['reference'] ?: 'N/A'; ?></small>
                                                    <?php if ($transaction['description']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $transaction['description']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'warning',
                                                        'completed' => 'success', 
                                                        'rejected' => 'danger',
                                                        'cancelled' => 'secondary'
                                                    ][$transaction['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo strtoupper($transaction['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?>
                                                        <br>
                                                        <span class="text-muted"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['status'] == 'pending'): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                                <button type="submit" name="approve_transaction" 
                                                                        class="btn btn-success" 
                                                                        onclick="return confirm('¿Aprobar transacción #<?php echo $transaction['id']; ?>? Se agregarán $<?php echo number_format($transaction['amount'], 2); ?> coins al usuario.')">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                            <button type="button" class="btn btn-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectModal"
                                                                    data-transaction-id="<?php echo $transaction['id']; ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-info"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#detailModal"
                                                                    data-transaction='<?php echo json_encode($transaction); ?>'>
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">Procesada</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-exchange-alt fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No se encontraron transacciones</h4>
                                <p class="text-muted">
                                    <?php if ($status_filter != 'all' || $type_filter != 'all' || $search): ?>
                                        Intenta con otros filtros
                                    <?php else: ?>
                                        No hay transacciones registradas
                                    <?php endif; ?>
                                </p>
                                <?php if ($status_filter != 'all' || $type_filter != 'all' || $search): ?>
                                    <a href="transactions.php" class="btn btn-primary">
                                        <i class="fas fa-times me-2"></i>Limpiar Filtros
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para Rechazar -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>Rechazar Transacción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="transaction_id" id="rejectTransactionId">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ¿Estás seguro de rechazar esta transacción?
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Motivo del Rechazo *</label>
                            <textarea class="form-control" name="reject_reason" rows="3" 
                                      placeholder="Explica el motivo del rechazo..." required></textarea>
                            <div class="form-text">Este mensaje será visible para el usuario.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="reject_transaction" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Rechazar Transacción
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Detalles -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalles de Transacción
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="transactionDetails">
                    <!-- Los detalles se cargan via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal para rechazar
        const rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const transactionId = button.getAttribute('data-transaction-id');
            document.getElementById('rejectTransactionId').value = transactionId;
        });

        // Modal para detalles
        const detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const transaction = JSON.parse(button.getAttribute('data-transaction'));
            
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información General</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>#${transaction.id}</td></tr>
                            <tr><td><strong>Usuario:</strong></td><td>${transaction.username}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${transaction.email}</td></tr>
                            <tr><td><strong>Rol:</strong></td><td>${transaction.role}</td></tr>
                            <tr><td><strong>Balance Actual:</strong></td><td>$${parseFloat(transaction.user_balance).toFixed(2)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Detalles de Transacción</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Tipo:</strong></td><td>${transaction.type}</td></tr>
                            <tr><td><strong>Monto:</strong></td><td>$${parseFloat(transaction.amount).toFixed(2)}</td></tr>
                            <tr><td><strong>Estado:</strong></td><td>${transaction.status}</td></tr>
                            <tr><td><strong>Referencia:</strong></td><td>${transaction.reference || 'N/A'}</td></tr>
                            <tr><td><strong>Fecha:</strong></td><td>${new Date(transaction.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                </div>
                ${transaction.description ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Descripción</h6>
                        <div class="alert alert-light">${transaction.description}</div>
                    </div>
                </div>
                ` : ''}
                ${transaction.admin_notes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Notas del Administrador</h6>
                        <div class="alert alert-warning">${transaction.admin_notes}</div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('transactionDetails').innerHTML = detailsHtml;
        });

        // Auto-close alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>