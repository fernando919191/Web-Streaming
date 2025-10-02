<?php
$pdo = new PDO("mysql:host=localhost:3306;dbname=fluxa_marketplace", "fluxer_user", "44m9j%L9w3");

// Ver todos los datos del admin
$stmt = $pdo->query("SELECT * FROM users WHERE username = 'admin'");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

echo "🔍 DATOS DEL ADMIN EN LA BASE DE DATOS:\n";
print_r($admin);

// Probar diferentes contraseñas
$passwords_to_test = [
    '7wx#39R0f',
    '7wx#39R0f ',
    '7wx#39R0F',
    'admin123',
    'password'
];

echo "\n🔐 PROBANDO CONTRASEÑAS:\n";
foreach ($passwords_to_test as $test_pwd) {
    $result = password_verify($test_pwd, $admin['password']);
    echo "   '{$test_pwd}': " . ($result ? '✅ CORRECTA' : '❌ incorrecta') . "\n";
}
?>