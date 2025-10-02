<?php
// Generar y verificar el hash CORRECTO
$password = '7wx#39R0f';

// Generar hash
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "🔑 Hash generado: " . $hash . "\n";

// Verificar que funciona
$verify = password_verify($password, $hash);
echo "✅ Verificación: " . ($verify ? 'CORRECTA' : 'INCORRECTA') . "\n";

// Probar con el hash anterior
$old_hash = '$2y$10$r.L8nL9s9Q8eW7k5pVvJ.OG7sK8a2yY7vL9nQ8eW7k5pVvJ.OG7sK';
$verify_old = password_verify($password, $old_hash);
echo "🔍 Verificación hash anterior: " . ($verify_old ? 'CORRECTA' : 'INCORRECTA') . "\n";
?>