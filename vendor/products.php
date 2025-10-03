<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkVendor();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['toggle_status'])) {
        $product_id = $_POST['product_id'];
        $action = $_POST['action'];
        
        try {
            $new_status = $action == 'activate' ? 1 : 0;
            $stmt = $db->prepare("UPDATE products SET is_active = ? WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$new_status, $product_id, $user_id]);
            
            $success = $action == 'activate' ? "✅ Producto activado correctamente." : "✅ Producto desactivado correctamente.";
        } catch (Exception $e) {
            $error = "❌ Error al cambiar estado: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];
        
        try {
            // Obtener información de imágenes para eliminarlas
            $stmt = $db->prepare("SELECT images FROM products WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$product_id, $user_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $images = json_decode($product['images'], true);
                
                // Eliminar imágenes del servidor
                foreach ($images as $image) {
                    if (file_exists($image['path'])) {
                        unlink($image['path']);
                    }
                }
                
                // Eliminar producto de la base de datos
                $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $user_id]);
                
                $success = "✅ Producto eliminado correctamente.";
            }
        } catch (Exception $e) {
            $error = "❌ Error al eliminar producto: " . $e->getMessage();
        }
    }
}

// Obtener productos del vendedor
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';

$where_conditions = ["vendor_id = ?"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter != 'all') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter == 'active' ? 1 : 0;
}

if ($category_filter != 'all') {
    $where_conditions[] = "category_id = ?";
    $params[] = $category_filter;
}

$where_sql = implode(" AND ", $where_conditions);

$stmt = $db->prepare("
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as sales_count
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $where_sql
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para el filtro
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(stock) as total_stock,
        SUM((SELECT COUNT(*) FROM order_items WHERE order_items.product_id = products.id)) as total_sales
    FROM products 
    WHERE vendor_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
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
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        .product-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
            border-radius: 10px 10px 0 0;
        }
        .stats-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .badge-platform {
            font-size: 0.7rem;
            padding: 0.4em 0.6em;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: #6c757d;
        }
        .feature-badge {
            font-size: 0.7rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-play-circle me-2"></i>Fluxa</h4>
                        <small>Panel Vendedor</small>
                        <div class="mt-3">
                            <img src="<?php echo $_SESSION['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username']) . '&background=6f42c1&color=ffffff'; ?>" 
                                 class="rounded-circle" width="80" height="80" style="border: 3px solid white;">
                            <h6 class="mt-2"><?php echo $_SESSION['username']; ?></h6>
                            <small><?php echo $_SESSION['email']; ?></small>
                        </div>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
                                <i class="fas fa-box me-2"></i>Mis Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_product.php">
                                <i class="fas fa-plus-circle me-2"></i>Agregar Producto
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Órdenes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="wallet.php">
                                <i class="fas fa-wallet me-2"></i>Mi Wallet
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Alertas -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><i class="fas fa-box me-2"></i>Mis Productos</h1>
                        <p class="text-muted mb-0">Gestiona todos tus productos en un solo lugar</p>
                    </div>
                    <div>
                        <a href="add_product.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Agregar Producto
                        </a>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-primary">
                            <div class="card-body text-center">
                                <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                                <p class="text-muted mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-success">
                            <div class="card-body text-center">
                                <h4 class="text-success"><?php echo $stats['active']; ?></h4>
                                <p class="text-muted mb-0">Activos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-warning">
                            <div class="card-body text-center">
                                <h4 class="text-warning"><?php echo $stats['inactive']; ?></h4>
                                <p class="text-muted mb-0">Inactivos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-3">
                        <div class="card stats-card border-info">
                            <div class="card-body text-center">
                                <h4 class="text-info"><?php echo $stats['total_stock']; ?></h4>
                                <p class="text-muted mb-0">En Stock</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-8 mb-3">
                        <div class="card stats-card border-danger">
                            <div class="card-body text-center">
                                <h4 class="text-danger"><?php echo $stats['total_sales']; ?></h4>
                                <p class="text-muted mb-0">Ventas Totales</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Búsqueda -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Filtros y Búsqueda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Buscar por título o descripción..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Todos</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="category">
                                    <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>Todas las categorías</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Productos -->
                <div class="row">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): 
                            $images = json_decode($product['images'], true);
                            $features = json_decode($product['features'], true);
                            $main_image = !empty($images) ? '../images/products/' . $images[0]['filename'] : 'https://via.placeholder.com/300x200?text=Sin+Imagen';
                        ?>
                            <div class="col-xl-4 col-lg-6 mb-4">
                                <div class="card product-card">
                                    <!-- Imagen del Producto -->
                                    <img src="<?php echo $main_image; ?>" class="card-img-top product-image" 
                                         alt="<?php echo htmlspecialchars($product['title']); ?>"
                                         onerror="this.src='https://via.placeholder.com/300x200?text=Error+Imagen'">
                                    
                                    <div class="card-body">
                                        <!-- Header con título y estado -->
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($product['title']); ?></h5>
                                            <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $product['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Categoría y Plataforma -->
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo $product['category_name']; ?></span>
                                            <span class="badge badge-platform bg-secondary">
                                                <i class="fas fa-<?php echo $product['platform']; ?> me-1"></i>
                                                <?php echo ucfirst($product['platform']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Descripción -->
                                        <p class="card-text text-muted small mb-2">
                                            <?php echo strlen($product['description']) > 100 ? 
                                                substr($product['description'], 0, 100) . '...' : 
                                                $product['description']; ?>
                                        </p>
                                        
                                        <!-- Características -->
                                        <?php if (!empty($features)): ?>
                                            <div class="mb-2">
                                                <?php foreach ($features as $feature): ?>
                                                    <span class="badge feature-badge bg-light text-dark border">
                                                        <i class="fas fa-check text-success me-1"></i>
                                                        <?php 
                                                            $feature_names = [
                                                                '4k' => '4K',
                                                                'multi_device' => 'Múltiples Dispositivos',
                                                                'offline' => 'Descargas',
                                                                'no_ads' => 'Sin Anuncios',
                                                                'hdr' => 'HDR',
                                                                'dolby' => 'Dolby',
                                                                'support' => 'Soporte',
                                                                'warranty' => 'Garantía'
                                                            ];
                                                            echo $feature_names[$feature] ?? ucfirst($feature);
                                                        ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Información de Precio y Stock -->
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <div class="border-end">
                                                    <strong class="text-success">$<?php echo number_format($product['price'], 2); ?></strong>
                                                    <div class="text-muted small">Precio</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border-end">
                                                    <strong class="text-primary"><?php echo $product['stock']; ?></strong>
                                                    <div class="text-muted small">Stock</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div>
                                                    <strong class="text-warning"><?php echo $product['sales_count']; ?></strong>
                                                    <div class="text-muted small">Ventas</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Botones de Acción -->
                                        <div class="d-grid gap-2 action-buttons">
                                            <div class="btn-group w-100">
                                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if ($product['is_active']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <button type="submit" name="toggle_status" 
                                                                class="btn btn-outline-warning"
                                                                onclick="return confirm('¿Desactivar este producto?')">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" name="toggle_status" 
                                                                class="btn btn-outline-success"
                                                                onclick="return confirm('¿Activar este producto?')">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($product['title']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Footer con fecha -->
                                    <div class="card-footer bg-transparent">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Creado: <?php echo date('d/m/Y', strtotime($product['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-box fa-4x mb-3"></i>
                                <h4>No hay productos</h4>
                                <p class="text-muted">
                                    <?php if ($search || $status_filter != 'all' || $category_filter != 'all'): ?>
                                        No se encontraron productos con los filtros aplicados.
                                    <?php else: ?>
                                        Aún no has agregado ningún producto a tu tienda.
                                    <?php endif; ?>
                                </p>
                                <a href="add_product.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Agregar Primer Producto
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginación (si hay muchos productos) -->
                <?php if (count($products) > 0): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Anterior</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal de Confirmación para Eliminar -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¿Estás seguro de eliminar este producto?</strong>
                        </div>
                        <p class="mb-0">
                            Estás a punto de eliminar el producto: 
                            <strong id="deleteProductName"></strong>
                        </p>
                        <p class="text-danger small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Esta acción no se puede deshacer y se eliminarán todas las imágenes asociadas.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_product" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal para eliminar
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const productId = button.getAttribute('data-product-id');
            const productName = button.getAttribute('data-product-name');
            
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
        });

        // Auto-close alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>