<?php
/**
 * Script de verificación de conexión a la base de datos Fluxa
 * Este script verifica la conexión y el estado de las tablas
 */

// Configuración de la base de datos CON TUS CREDENCIALES
$config = [
    'host' => 'localhost:8443',
    'dbname' => 'fluxa_marketplace',
    'username' => 'fluxer_user',
    'password' => '44m9j%L9w3',
    'charset' => 'utf8mb4'
];

function checkDatabaseConnection($config) {
    echo "🔍 Iniciando verificación de base de datos Fluxa...\n";
    echo "==========================================\n\n";
    
    // Mostrar configuración (ocultando contraseña)
    echo "📋 Configuración:\n";
    echo "   Host: {$config['host']}\n";
    echo "   Base de datos: {$config['dbname']}\n";
    echo "   Usuario: {$config['username']}\n";
    echo "   Contraseña: **********\n\n";
    
    try {
        // Intentar conexión
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✅ CONEXIÓN EXITOSA\n";
        echo "   Conexión establecida correctamente con la base de datos\n\n";
        
        return $pdo;
        
    } catch (PDOException $e) {
        echo "❌ ERROR DE CONEXIÓN\n";
        echo "   Mensaje: " . $e->getMessage() . "\n\n";
        
        echo "💡 SOLUCIONES POSIBLES:\n";
        echo "   1. Verifica que MySQL esté ejecutándose en el puerto 8443\n";
        echo "   2. Confirma que el usuario 'fluxer_user' exista y tenga permisos\n";
        echo "   3. Asegúrate de que la base de datos 'fluxa_marketplace' exista\n";
        echo "   4. Revisa que la contraseña sea correcta\n";
        echo "   5. Verifica el firewall y permisos de red\n";
        
        return false;
    }
}

function checkTables($pdo) {
    echo "📋 VERIFICANDO TABLAS REQUERIDAS:\n";
    echo "------------------------------------------\n";
    
    $required_tables = [
        'users', 'wallets', 'products', 'categories', 
        'transactions', 'orders', 'order_items', 
        'sales_reports', 'reviews', 'conversations', 
        'messages', 'platform_settings'
    ];
    
    $missing_tables = [];
    $existing_tables = [];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->fetch()) {
            echo "   ✅ $table\n";
            $existing_tables[] = $table;
        } else {
            echo "   ❌ $table (NO ENCONTRADA)\n";
            $missing_tables[] = $table;
        }
    }
    
    echo "\n";
    return ['existing' => $existing_tables, 'missing' => $missing_tables];
}

function checkTableStructure($pdo, $table) {
    echo "   🔍 Analizando estructura de: $table\n";
    
    try {
        $stmt = $pdo->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo "      📊 {$column['Field']} - {$column['Type']}";
            if ($column['Key'] == 'PRI') echo " 🔑 PRIMARY";
            if ($column['Null'] == 'NO') echo " NOT NULL";
            echo "\n";
        }
        
        return count($columns);
        
    } catch (PDOException $e) {
        echo "      ❌ Error al analizar tabla: " . $e->getMessage() . "\n";
        return 0;
    }
}

function checkSampleData($pdo) {
    echo "\n📊 VERIFICANDO DATOS DE EJEMPLO:\n";
    echo "------------------------------------------\n";
    
    $tables_to_check = [
        'users' => 'SELECT COUNT(*) as count FROM users',
        'products' => 'SELECT COUNT(*) as count FROM products',
        'categories' => 'SELECT COUNT(*) as count FROM categories',
        'wallets' => 'SELECT COUNT(*) as count FROM wallets'
    ];
    
    foreach ($tables_to_check as $table => $query) {
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   📈 $table: {$result['count']} registros\n";
        } catch (PDOException $e) {
            echo "   ❌ $table: Error - " . $e->getMessage() . "\n";
        }
    }
}

function checkAdminUser($pdo) {
    echo "\n👑 VERIFICANDO USUARIO ADMINISTRADOR:\n";
    echo "------------------------------------------\n";
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, u.email, u.role, u.is_verified, w.balance 
            FROM users u 
            LEFT JOIN wallets w ON u.id = w.user_id 
            WHERE u.role = 'admin'
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($admins) > 0) {
            foreach ($admins as $admin) {
                echo "   ✅ Administrador encontrado:\n";
                echo "      Usuario: {$admin['username']}\n";
                echo "      Email: {$admin['email']}\n";
                echo "      Verificado: " . ($admin['is_verified'] ? 'Sí' : 'No') . "\n";
                echo "      Balance: $" . ($admin['balance'] ?? '0.00') . "\n";
            }
        } else {
            echo "   ⚠️  No se encontraron usuarios administradores\n";
        }
        
    } catch (PDOException $e) {
        echo "   ❌ Error al verificar administradores: " . $e->getMessage() . "\n";
    }
}

function getDatabaseInfo($pdo) {
    echo "\n💾 INFORMACIÓN DEL SERVIDOR MYSQL:\n";
    echo "------------------------------------------\n";
    
    try {
        // Versión de MySQL
        $version = $pdo->query('SELECT VERSION() as version')->fetch(PDO::FETCH_ASSOC);
        echo "   🚀 Versión MySQL: {$version['version']}\n";
        
        // Tamaño de la base de datos
        $size = $pdo->query("
            SELECT 
                table_schema as 'Database',
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'Size_MB'
            FROM information_schema.tables 
            WHERE table_schema = 'fluxa_marketplace'
            GROUP BY table_schema
        ")->fetch(PDO::FETCH_ASSOC);
        
        echo "   📦 Tamaño BD: " . ($size['Size_MB'] ?? '0') . " MB\n";
        
        // Caracteres y collation
        $charset = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch(PDO::FETCH_ASSOC);
        $collation = $pdo->query("SHOW VARIABLES LIKE 'collation_database'")->fetch(PDO::FETCH_ASSOC);
        
        echo "   🔤 Charset: {$charset['Value']}\n";
        echo "   🔠 Collation: {$collation['Value']}\n";
        
    } catch (PDOException $e) {
        echo "   ❌ Error al obtener información: " . $e->getMessage() . "\n";
    }
}

// EJECUTAR VERIFICACIÓN COMPLETA
echo "==========================================\n";
echo "   VERIFICADOR BASE DE DATOS FLUXA\n";
echo "==========================================\n\n";

$pdo = checkDatabaseConnection($config);

if ($pdo) {
    // Verificar tablas
    $tables_status = checkTables($pdo);
    
    // Verificar estructura de tablas existentes
    if (count($tables_status['existing']) > 0) {
        echo "\n🏗️  ESTRUCTURA DE TABLAS EXISTENTES:\n";
        echo "------------------------------------------\n";
        
        foreach ($tables_status['existing'] as $table) {
            $column_count = checkTableStructure($pdo, $table);
            echo "      📎 Total columnas: $column_count\n\n";
        }
    }
    
    // Verificar datos de ejemplo
    checkSampleData($pdo);
    
    // Verificar usuario administrador
    checkAdminUser($pdo);
    
    // Información del servidor
    getDatabaseInfo($pdo);
    
    // Resumen final
    echo "\n==========================================\n";
    echo "   RESUMEN FINAL\n";
    echo "==========================================\n";
    
    $total_tables = count($tables_status['existing']) + count($tables_status['missing']);
    $percentage = round((count($tables_status['existing']) / $total_tables) * 100, 2);
    
    echo "✅ Tablas existentes: " . count($tables_status['existing']) . "/$total_tables ($percentage%)\n";
    
    if (count($tables_status['missing']) > 0) {
        echo "❌ Tablas faltantes: " . count($tables_status['missing']) . "\n";
        echo "   Tablas que necesitan crearse:\n";
        foreach ($tables_status['missing'] as $missing) {
            echo "      - $missing\n";
        }
        
        echo "\n💡 EJECUTA EL ARCHIVO SQL PARA CREAR LAS TABLAS FALTANTES\n";
    } else {
        echo "🎉 ¡Todas las tablas están presentes! La base de datos está lista.\n";
    }
    
} else {
    echo "\n==========================================\n";
    echo "   VERIFICACIÓN FALLIDA\n";
    echo "==========================================\n";
    echo "No se pudo establecer conexión con la base de datos.\n";
    echo "Revisa la configuración y vuelve a intentar.\n";
}

echo "\n";
?>

<!-- Versión HTML para navegador -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador BD - Fluxa</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        .info { color: #4488ff; }
        pre { background: #2a2a2a; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Verificador Base de Datos Fluxa</h1>
    <pre><?php include 'check_database.php'; ?></pre>
</body>
</html>