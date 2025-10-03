<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener balance actual DESDE WALLETS
$stmt = $db->prepare("
    SELECT COALESCE(balance, 0) as balance 
    FROM wallets 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$current_balance = $stmt->fetchColumn();

// Si no existe el wallet, crearlo
if ($current_balance === false) {
    $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
    $stmt->execute([$user_id]);
    $current_balance = 0.00;
}

// Enviar respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'balance' => floatval($current_balance),
    'previous_balance' => isset($_SESSION['last_balance']) ? floatval($_SESSION['last_balance']) : null
]);

// Guardar balance actual para comparación
$_SESSION['last_balance'] = $current_balance;
?>