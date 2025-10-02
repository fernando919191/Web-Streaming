<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Insertar vendedores de ejemplo
    $vendors = [
        ['username' => 'dannip', 'email' => 'dannip@fluxa.com', 'store_name' => 'Dannip Premium Accounts', 'description' => 'Especialista en cuentas premium'],
        ['username' => 'kodigox', 'email' => 'kodigox@fluxa.com', 'store_name' => 'Kodigox Shop', 'description' => 'Las mejores cuentas de streaming'],
        ['username' => 'stream_pro', 'email' => 'streampro@fluxa.com', 'store_name' => 'Stream Pro', 'description' => 'Calidad y confianza garantizada']
    ];

    foreach ($vendors as $vendor) {
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role, store_name, description, is_verified) VALUES (?, ?, ?, 'vendor', ?, ?, TRUE)");
        $stmt->execute([
            $vendor['username'],
            $vendor['email'],
            password_hash('password123', PASSWORD_DEFAULT),
            $vendor['store_name'],
            $vendor['description']
        ]);

        $vendor_id = $db->lastInsertId();
        
        // Crear wallet para el vendedor
        $stmt = $db->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
        $stmt->execute([$vendor_id, rand(100, 1000)]);
    }

    // Insertar productos de ejemplo
    $products = [
        ['vendor_id' => 2, 'category_id' => 1, 'title' => 'DISNEY PREMIUM', 'price' => 50.00, 'platform' => 'disney', 'account_type' => 'premium'],
        ['vendor_id' => 2, 'category_id' => 1, 'title' => 'IPTV Premium', 'price' => 40.00, 'platform' => 'other', 'account_type' => 'premium'],
        ['vendor_id' => 3, 'category_id' => 1, 'title' => 'MAX PLATINO', 'price' => 45.00, 'platform' => 'hbo', 'account_type' => 'premium'],
        ['vendor_id' => 3, 'category_id' => 2, 'title' => 'Spotify Family', 'price' => 35.00, 'platform' => 'spotify', 'account_type' => 'family'],
        ['vendor_id' => 4, 'category_id' => 1, 'title' => 'Netflix 4K Premium', 'price' => 60.00, 'platform' => 'netflix', 'account_type' => 'premium']
    ];

    foreach ($products as $product) {
        $stmt = $db->prepare("INSERT INTO products (vendor_id, category_id, title, description, price, stock, platform, account_type, duration_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $product['vendor_id'],
            $product['category_id'],
            $product['title'],
            "Cuenta premium de {$product['platform']} con todas las características incluidas.",
            $product['price'],
            rand(1, 10),
            $product['platform'],
            $product['account_type'],
            30
        ]);
    }

    echo "Datos de ejemplo insertados correctamente!";

} catch(PDOException $exception) {
    echo "Error: " . $exception->getMessage();
}
?>