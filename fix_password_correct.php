<?php
/**
 * Script para generar y aplicar el hash correcto
 */

$config = [
    'host' => 'localhost:3306',
    'dbname' => 'fluxa_marketplace', 
    'username' => 'fluxer_user',
    'password' => '44m9j%L9w3'
];

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password']);
    
    // Generar hash CORRECTO
    $password = '7wx#39R0f';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "🔑 Hash generado: " . $hash . "\n\n";
    
    // Actualizar en la base de datos
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hash]);
    
    echo "✅ Contraseña actualizada en la base de datos\n\n";
    
    // Verificar que funciona
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $stored_hash = $stmt->fetchColumn();
    
    $verify = password_verify($password, $stored_hash);
    
    echo "🔍 VERIFICACIÓN:\n";
    echo "   Contraseña: {$password}\n";
    echo "   Hash en BD: " . substr($stored_hash, 0, 30) . "...\n";
    echo "   Resultado: " . ($verify ? '✅ CORRECTA' : '❌ INCORRECTA') . "\n";
    
    if ($verify) {
        echo "\n🎉 ¡Ahora puedes iniciar sesión con:\n";
        echo "   👤 Usuario: admin\n";
        echo "   🔑 Contraseña: 7wx#39R0f\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>