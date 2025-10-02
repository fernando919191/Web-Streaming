<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkVendor();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Obtener productos del vendedor
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.vendor_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Productos - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .product-card {
            transition: transform 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (mismo que dashboard) -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Mis Productos</h1>
                    <a href="add_product.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Producto
                    </a>
                </div>

                <!-- Product Grid -->
                <div class="row">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card product-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?php echo $product['title']; ?></h5>
                                            <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $product['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo substr($product['description'], 0, 100); ?>...
                                        </p>
                                        
                                        <div class="mb-3">
                                            <strong class="text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                            <span class="text-muted ms-2">• Stock: <?php echo $product['stock']; ?></span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i><?php echo $product['category_name']; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-<?php echo $product['platform']; ?> me-1"></i>
                                                <?php echo ucfirst($product['platform']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100">
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../actions/toggle_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-<?php echo $product['is_active'] ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $product['is_active'] ? 'pause' : 'play'; ?>"></i>
                                            </a>
                                            <a href="../actions/delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-box fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No tienes productos aún</h4>
                            <p class="text-muted">Comienza agregando tu primer producto a la tienda</p>
                            <a href="add_product.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Agregar Primer Producto
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>