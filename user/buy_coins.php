<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Procesar solicitud de compra de coins
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_coins'])) {
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $reference = trim($_POST['reference']);
    
    if ($amount > 0 && !empty($reference)) {
        // Crear transacción pendiente
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, type, amount, description, status, reference) 
            VALUES (?, 'purchase', ?, ?, 'pending', ?)
        ");
        
        $description = "Compra de $amount coins via $payment_method";
        $stmt->execute([$user_id, $amount, $description, $reference]);
        
        $success = "Solicitud de compra enviada. Un administrador verificará tu pamento.";
    } else {
        $error = "Por favor, completa todos los campos correctamente.";
    }
}

// Obtener transacciones pendientes del usuario
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? AND type = 'purchase' AND status = 'pending'
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$pending_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Coins - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (igual que dashboard) -->
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1>Comprar Coins</h1>
                <p class="text-muted">Adquiere coins para comprar en todas las tiendas</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Formulario de Compra -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>Solicitar Compra de Coins
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="request_coins" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Cantidad de Coins *</label>
                                        <input type="number" class="form-control" name="amount" 
                                               min="1" step="1" required placeholder="Ej: 100">
                                        <div class="form-text">1 Coin = $1.00 USD</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Método de Pago *</label>
                                        <select class="form-select" name="payment_method" required>
                                            <option value="">Seleccionar método</option>
                                            <option value="transferencia">Transferencia Bancaria</option>
                                            <option value="paypal">PayPal</option>
                                            <option value="binance">Binance</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Referencia/Número de Transacción *</label>
                                        <input type="text" class="form-control" name="reference" 
                                               required placeholder="Número de transacción o referencia">
                                        <div class="form-text">Incluye el número que identifique tu pago</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Pago -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Información de Pago
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6>Métodos de Pago Aceptados:</h6>
                                <ul>
                                    <li><strong>Transferencia Bancaria</strong></li>
                                    <li><strong>PayPal</strong></li>
                                    <li><strong>Binance</strong></li>
                                    <li><strong>Otros métodos</strong> (consultar)</li>
                                </ul>
                                
                                <div class="alert alert-warning">
                                    <small>
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Proceso:</strong> 
                                        1. Solicita la compra → 
                                        2. Realiza el pago → 
                                        3. Espera verificación → 
                                        4. Recibe tus coins
                                    </small>
                                </div>
                                
                                <h6>Tiempo de Procesamiento:</h6>
                                <p class="small">Normalmente 1-24 horas después de la verificación del pago.</p>
                            </div>
                        </div>

                        <!-- Solicitudes Pendientes -->
                        <?php if (count($pending_purchases) > 0): ?>
                        <div class="card mt-4">
                            <div class="card-header bg-warning">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Solicitudes Pendientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($pending_purchases as $purchase): ?>
                                    <div class="alert alert-warning">
                                        <strong><?php echo $purchase['amount']; ?> coins</strong>
                                        <br>
                                        <small>
                                            Referencia: <?php echo $purchase['reference']; ?>
                                            <br>
                                            Fecha: <?php echo date('d/m/Y H:i', strtotime($purchase['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>