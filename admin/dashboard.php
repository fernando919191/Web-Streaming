<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $action = $_POST['action'];
    $reason = $_POST['reason'] ?? '';
    
    $database = new Database();
    $db = $database->getConnection();
    
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
            // Aprobar transacción - agregar coins al usuario
            $stmt = $db->prepare("UPDATE transactions SET status = 'completed', admin_notes = ? WHERE id = ?");
            $stmt->execute(["Transacción aprobada por administrador", $transaction_id]);
            
            // Actualizar wallet del usuario
            $stmt = $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$transaction['amount'], $transaction['user_id']]);
            
        } elseif ($action == 'reject') {
            // Rechazar transacción
            $stmt = $db->prepare("UPDATE transactions SET status = 'rejected', admin_notes = ? WHERE id = ?");
            $stmt->execute(["Rechazado: " . $reason, $transaction_id]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Transacción procesada correctamente']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>