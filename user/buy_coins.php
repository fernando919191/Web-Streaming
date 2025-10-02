<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Obtener datos de pago de la plataforma
$stmt = $db->prepare("SELECT * FROM platform_settings LIMIT 1");
$stmt->execute();
$platform_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar solicitud de compra de coins
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['request_coins'])) {
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'];
        $reference = trim($_POST['reference']);
        
        if ($amount > 0 && !empty($reference)) {
            // Crear transacción pendiente
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, description, status, reference) 
                VALUES (?, 'purchase', ?, ?, 'pending', ?)
            ");
            
            $description = "Compra de $amount coins via $payment_method - Ref: $reference";
            $stmt->execute([$user_id, $amount, $description, $reference]);
            
            $success = "✅ Solicitud de compra enviada. Realiza el pago según las instrucciones.";
        } else {
            $error = "❌ Por favor, completa todos los campos correctamente.";
        }
    }
    
    if (isset($_POST['mark_as_paid'])) {
        $transaction_id = $_POST['transaction_id'];
        $proof_details = trim($_POST['proof_details']);
        
        if (!empty($proof_details)) {
            // Actualizar transacción con detalles del comprobante
            $stmt = $db->prepare("
                UPDATE transactions 
                SET description = CONCAT(description, ' | Comprobante: ', ?),
                    proof_image = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$proof_details, 'pending_verification', $transaction_id, $user_id]);
            
            $success = "✅ Comprobante enviado. El administrador verificará tu pago.";
        } else {
            $error = "❌ Por favor, proporciona detalles del comprobante.";
        }
    }
}

// Obtener transacciones del usuario
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? AND type = 'purchase'
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar Coins - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-method { display: none; }
        .method-active { display: block; }
        .instructions-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 0 5px 5px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1><i class="fas fa-coins me-2"></i>Comprar Coins</h1>
                <p class="text-muted">Adquiere coins para comprar en todas las tiendas - 1 Coin = $1.00 USD</p>

                <!-- Alertas -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Formulario de Compra -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>Solicitar Compra de Coins
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="purchaseForm">
                                    <input type="hidden" name="request_coins" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Cantidad de Coins *</label>
                                        <input type="number" class="form-control form-control-lg" 
                                               name="amount" min="10" step="1" required 
                                               placeholder="Ej: 100">
                                        <div class="form-text">Mínimo: 10 coins | 1 Coin = $1.00 USD</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Método de Pago *</label>
                                        <select class="form-select form-select-lg" name="payment_method" 
                                                id="paymentMethod" required onchange="showPaymentInstructions()">
                                            <option value="">Seleccionar método de pago</option>
                                            <option value="transferencia">🏦 Transferencia Bancaria</option>
                                            <option value="paypal">💰 PayPal</option>
                                            <option value="binance">🔗 Binance (Crypto)</option>
                                            <option value="otro">📱 Otro Método</option>
                                        </select>
                                    </div>

                                    <!-- Instrucciones de Pago (Dinámicas) -->
                                    <div id="paymentInstructions">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Selecciona un método de pago para ver las instrucciones
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tu Referencia/Nombre *</label>
                                        <input type="text" class="form-control" name="reference" 
                                               value="<?php echo $username; ?>" required 
                                               placeholder="Tu nombre de usuario">
                                        <div class="form-text">Usa tu nombre de usuario como referencia de pago</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud de Compra
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Historial y Solicitudes -->
                    <div class="col-lg-6">
                        <!-- Solicitudes Pendientes -->
                        <?php 
                        $pending_transactions = array_filter($transactions, function($t) {
                            return $t['status'] == 'pending';
                        });
                        ?>
                        
                        <?php if (count($pending_transactions) > 0): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Solicitudes Pendientes de Pago
                                    <span class="badge bg-danger float-end"><?php echo count($pending_transactions); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($pending_transactions as $transaction): ?>
                                    <div class="alert alert-warning">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="alert-heading">
                                                    <i class="fas fa-coins me-1"></i>
                                                    <?php echo $transaction['amount']; ?> Coins
                                                </h6>
                                                <small class="text-muted">
                                                    Método: <?php echo ucfirst($transaction['description']); ?><br>
                                                    Fecha: <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#markPaidModal"
                                                    data-transaction-id="<?php echo $transaction['id']; ?>">
                                                <i class="fas fa-check me-1"></i>Ya Pagué
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Historial de Transacciones -->
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Historial de Compras
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($transactions) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($transactions, 0, 5) as $transaction): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?php echo $transaction['amount']; ?> coins</strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $transaction['description']; ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
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
                                                        <br>
                                                        <small><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($transactions) > 5): ?>
                                        <div class="text-center mt-3">
                                            <a href="transactions.php" class="btn btn-outline-primary btn-sm">
                                                Ver Historial Completo
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No hay historial de compras</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para Marcar como Pagado -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Confirmar Pago Realizado
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="mark_as_paid" value="1">
                    <input type="hidden" name="transaction_id" id="modalTransactionId">
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Confirma que ya realizaste el pago según las instrucciones.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Detalles del Comprobante *</label>
                            <textarea class="form-control" name="proof_details" rows="3" 
                                      placeholder="Ej: Número de transacción, screenshot ID, o cualquier información que ayude a verificar tu pago..."
                                      required></textarea>
                            <div class="form-text">
                                Proporciona cualquier información que ayude al administrador a verificar tu pago.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirmar Pago Realizado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Datos de pago desde PHP
        const paymentData = {
            transferencia: `<?php echo nl2br(htmlspecialchars($platform_settings['bank_details'] ?? 'Datos no disponibles')); ?>`,
            paypal: `<?php echo nl2br(htmlspecialchars($platform_settings['paypal_details'] ?? 'Datos no disponibles')); ?>`,
            binance: `<?php echo nl2br(htmlspecialchars($platform_settings['binance_details'] ?? 'Datos no disponibles')); ?>`,
            otro: `<?php echo nl2br(htmlspecialchars($platform_settings['other_details'] ?? 'Datos no disponibles')); ?>`
        };

        function showPaymentInstructions() {
            const method = document.getElementById('paymentMethod').value;
            const instructionsDiv = document.getElementById('paymentInstructions');
            
            if (method && paymentData[method]) {
                instructionsDiv.innerHTML = `
                    <div class="instructions-box mb-3">
                        <h6><i class="fas fa-credit-card me-2"></i>Instrucciones para ${method.toUpperCase()}</h6>
                        <div class="mt-2">${paymentData[method]}</div>
                        <div class="alert alert-warning mt-2 mb-0">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Importante:</strong> Usa tu nombre de usuario como referencia
                            </small>
                        </div>
                    </div>
                `;
            } else if (method) {
                instructionsDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Selecciona un método de pago válido
                    </div>
                `;
            } else {
                instructionsDiv.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Selecciona un método de pago para ver las instrucciones
                    </div>
                `;
            }
        }

        // Modal handler
        const markPaidModal = document.getElementById('markPaidModal');
        markPaidModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const transactionId = button.getAttribute('data-transaction-id');
            document.getElementById('modalTransactionId').value = transactionId;
        });

        // Mostrar instrucciones al cargar si hay un método seleccionado
        document.addEventListener('DOMContentLoaded', function() {
            const currentMethod = document.getElementById('paymentMethod').value;
            if (currentMethod) {
                showPaymentInstructions();
            }
        });
    </script>
</body>
</html>