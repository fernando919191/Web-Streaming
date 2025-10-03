<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "🔍 VERIFICANDO CONEXIÓN USUARIOS-WALLETS\n";
echo "==========================================\n\n";

// Verificar todos los usuarios y sus wallets
$stmt = $db->query("
    SELECT 
        u.id as user_id,
        u.username,
        u.email,
        u.role,
        w.id as wallet_id,
        w.balance,
        w.created_at as wallet_created
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    ORDER BY u.id
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 ESTADO ACTUAL:\n";
echo "------------------------------------------\n";

$users_with_wallet = 0;
$users_without_wallet = 0;

foreach ($users as $user) {
    if ($user['wallet_id']) {
        echo "✅ USUARIO: {$user['username']} ({$user['email']})\n";
        echo "   👛 WALLET: #{$user['wallet_id']} - Balance: \${$user['balance']}\n";
        $users_with_wallet++;
    } else {
        echo "❌ USUARIO: {$user['username']} ({$user['email']})\n";
        echo "   🚫 NO TIENE WALLET\n";
        $users_without_wallet++;
    }
    echo "   ---\n";
}

echo "\n📈 RESUMEN:\n";
echo "------------------------------------------\n";
echo "👥 Total usuarios: " . count($users) . "\n";
echo "✅ Usuarios CON wallet: {$users_with_wallet}\n";
echo "❌ Usuarios SIN wallet: {$users_without_wallet}\n";

// Verificar si el usuario admin tiene wallet
$stmt = $db->prepare("
    SELECT u.username, w.balance 
    FROM users u 
    LEFT JOIN wallets w ON u.id = w.user_id 
    WHERE u.username = 'admin'
");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n👑 VERIFICANDO ADMIN:\n";
echo "------------------------------------------\n";
if ($admin) {
    echo "Admin: {$admin['username']}\n";
    echo "Balance: " . ($admin['balance'] !== null ? "\${$admin['balance']}" : "NO TIENE WALLET") . "\n";
}
?>