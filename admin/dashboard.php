<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkAdmin();

$database = new Database();
$db = $database->getConnection();

// Procesar creación de usuario si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $store_name = trim($_POST['store_name'] ?? '');
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos obligatorios.';
    } else {
        // Verificar si el usuario ya existe
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'El usuario o email ya existen.';
        } else {
            // ✅ GENERAR HASH CORRECTO para la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $db->beginTransaction();
                
                // Insertar usuario
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, store_name, is_verified) VALUES (?, ?, ?, ?, ?, TRUE)");
                $stmt->execute([$username, $email, $password_hash, $role, $store_name]);
                
                $user_id = $db->lastInsertId();
                
                // Crear wallet para el usuario
                $stmt = $db->prepare("INSERT INTO wallets (user_id) VALUES (?)");
                $stmt->execute([$user_id]);
                
                $db->commit();
                $success = "Usuario {$username} creado exitosamente como {$role}.";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error al crear usuario: " . $e->getMessage();
            }
        }
    }
}

// Obtener estadísticas generales
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_vendors' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'vendor'")->fetchColumn(),
    'total_products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_transactions' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn(),
    'total_revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
    'platform_earnings' => $db->query("SELECT COALESCE(SUM(platform_commission), 0) FROM sales_reports WHERE status = 'paid'")->fetchColumn()
];

// Transacciones pendientes
$stmt = $db->prepare("
    SELECT t.*, u.username, u.email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'pending' AND t.type = 'purchase'
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute();
$pending_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - Fluxa</title>
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
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .modal-content {
            border-radius: 15px;
            border: none;
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="fas fa-user-plus me-2"></i>Crear Usuario
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Gestionar Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">
                                <i class="fas fa-exchange-alt me-2"></i>Transacciones
                                <?php if ($stats['pending_transactions'] > 0): ?>
                                    <span class="badge bg-danger float-end"><?php echo $stats['pending_transactions']; ?></span>
                                <?php endif; ?>
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
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Dashboard Administrativo</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus me-2"></i>Crear Usuario
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-primary"><?php echo $stats['total_users']; ?></h4>
                                        <p class="text-muted">Total Usuarios</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-success"><?php echo $stats['total_vendors']; ?></h4>
                                        <p class="text-muted">Vendedores</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-store fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-warning"><?php echo $stats['total_products']; ?></h4>
                                        <p class="text-muted">Productos</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-box fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-info"><?php echo $stats['total_orders']; ?></h4>
                                        <p class="text-muted">Órdenes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-danger"><?php echo $stats['pending_transactions']; ?></h4>
                                        <p class="text-muted">Pendientes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card stats-card border-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="text-dark">$<?php echo number_format($stats['platform_earnings'], 2); ?></h4>
                                        <p class="text-muted">Ganancias</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x text-dark"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transacciones Pendientes -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Transacciones Pendientes
                                    <?php if (count($pending_transactions) > 0): ?>
                                        <span class="badge bg-danger float-end"><?php echo count($pending_transactions); ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($pending_transactions) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Usuario</th>
                                                    <th>Monto</th>
                                                    <th>Referencia</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_transactions as $transaction): ?>
                                                    <tr>
                                                        <td>
                                                            <small><?php echo $transaction['username']; ?></small>
                                                            <br><small class="text-muted"><?php echo $transaction['email']; ?></small>
                                                        </td>
                                                        <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                        <td><small><?php echo $transaction['reference'] ?: 'N/A'; ?></small></td>
                                                        <td><small><?php echo date('d/m H:i', strtotime($transaction['created_at'])); ?></small></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-success" onclick="approveTransaction(<?php echo $transaction['id']; ?>)">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                                <button class="btn btn-danger" onclick="rejectTransaction(<?php echo $transaction['id']; ?>)">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <p class="text-muted">No hay transacciones pendientes</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Crear Usuario -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="create_user" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" name="password" required 
                                   placeholder="La contraseña se hasheará automáticamente">
                            <div class="form-text">✅ El hash se generará correctamente con password_hash()</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" name="role" required>
                                <option value="user">Usuario Normal</option>
                                <option value="vendor">Vendedor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="storeNameField">
                            <label class="form-label">Nombre de Tienda</label>
                            <input type="text" class="form-control" name="store_name" 
                                   placeholder="Solo para vendedores">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Crear Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar campo de tienda según el rol
        document.querySelector('select[name="role"]').addEventListener('change', function() {
            const storeField = document.getElementById('storeNameField');
            if (this.value === 'vendor') {
                storeField.style.display = 'block';
                storeField.querySelector('input').required = true;
            } else {
                storeField.style.display = 'none';
                storeField.querySelector('input').required = false;
            }
        });

        // Inicializar estado del campo tienda
        document.querySelector('select[name="role"]').dispatchEvent(new Event('change'));

        function approveTransaction(transactionId) {
            if (confirm('¿Estás seguro de aprobar esta transacción?')) {
                // Aquí iría la lógica para aprobar la transacción
                alert('Transacción ' + transactionId + ' aprobada');
            }
        }

        function rejectTransaction(transactionId) {
            if (confirm('¿Estás seguro de rechazar esta transacción?')) {
                // Aquí iría la lógica para rechazar la transacción
                alert('Transacción ' + transactionId + ' rechazada');
            }
        }
    </script>
</body>
</html>