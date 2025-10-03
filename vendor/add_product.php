<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkVendor();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Configuración de imágenes
$upload_dir = '../images/products/';
$allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Crear directorio de imágenes si no existe
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $error = "❌ No se pudo crear el directorio de imágenes. Contacta al administrador.";
    }
}

// Obtener categorías para el select
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario de agregar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $platform = $_POST['platform'];
    $account_type = $_POST['account_type'];
    $duration_days = intval($_POST['duration_days']);
    $features = $_POST['features'] ?? [];
    
    // Validaciones básicas
    if (empty($title) || empty($description) || $price <= 0 || $stock <= 0) {
        $error = 'Por favor, completa todos los campos obligatorios correctamente.';
    } else {
        try {
            $db->beginTransaction();
            
            // Procesar imágenes subidas
            $uploaded_images = [];
            
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['name'] as $key => $name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['images']['tmp_name'][$key];
                        $file_size = $_FILES['images']['size'][$key];
                        $file_type = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        
                        // Validar tipo de archivo
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception("El archivo '$name' no es una imagen válida. Formatos permitidos: " . implode(', ', $allowed_types));
                        }
                        
                        // Validar tamaño
                        if ($file_size > $max_size) {
                            throw new Exception("El archivo '$name' es demasiado grande. Máximo 5MB permitido.");
                        }
                        
                        // Generar nombre único
                        $new_filename = uniqid() . '_' . time() . '.' . $file_type;
                        $file_path = $upload_dir . $new_filename;
                        
                        // Mover archivo
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $uploaded_images[] = [
                                'filename' => $new_filename,
                                'original_name' => $name,
                                'path' => $file_path
                            ];
                        } else {
                            throw new Exception("Error al subir el archivo '$name'.");
                        }
                    }
                }
            }
            
            // Preparar datos para JSON
            $features_json = json_encode($features);
            $images_json = json_encode($uploaded_images);
            
            // Insertar producto
            $stmt = $db->prepare("
                INSERT INTO products 
                (vendor_id, category_id, title, description, price, stock, platform, account_type, duration_days, features, images, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $stmt->execute([
                $user_id,
                $category_id,
                $title,
                $description,
                $price,
                $stock,
                $platform,
                $account_type,
                $duration_days,
                $features_json,
                $images_json
            ]);
            
            $product_id = $db->lastInsertId();
            $success = "✅ Producto '$title' agregado exitosamente. ID: #$product_id";
            
            // Limpiar el formulario
            $_POST = [];
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            
            // Eliminar imágenes subidas en caso de error
            foreach ($uploaded_images as $image) {
                if (file_exists($image['path'])) {
                    unlink($image['path']);
                }
            }
            
            $error = "❌ Error al agregar el producto: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Fluxa</title>
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
        .form-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .feature-badge {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .feature-badge.selected {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }
        .platform-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .image-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        .upload-area.dragover {
            background: #007bff;
            border-color: #0056b3;
            color: white;
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
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Mis Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="add_product.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Producto</h1>
                        <p class="text-muted mb-0">Agrega nuevas cuentas de streaming a tu tienda</p>
                    </div>
                    <div>
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver a Productos
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Formulario -->
                <div class="card form-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Información del Producto
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="productForm" enctype="multipart/form-data">
                            <input type="hidden" name="add_product" value="1">
                            
                            <!-- Información Básica -->
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Título del Producto *</label>
                                        <input type="text" class="form-control form-control-lg" name="title" 
                                               value="<?php echo $_POST['title'] ?? ''; ?>" 
                                               placeholder="Ej: Netflix Premium 4K UHD" required>
                                        <div class="form-text">Un título claro y descriptivo para tu producto.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Precio (Coins) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="price" 
                                                   value="<?php echo $_POST['price'] ?? ''; ?>" 
                                                   min="1" step="0.01" placeholder="50.00" required>
                                        </div>
                                        <div class="form-text">Precio en coins (1 Coin = $1.00 USD)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Categoría *</label>
                                        <select class="form-select" name="category_id" required>
                                            <option value="">Seleccionar categoría</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $category['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Stock Disponible *</label>
                                        <input type="number" class="form-control" name="stock" 
                                               value="<?php echo $_POST['stock'] ?? '1'; ?>" 
                                               min="1" max="100" required>
                                        <div class="form-text">Número de cuentas disponibles para este producto.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Descripción del Producto *</label>
                                <textarea class="form-control" name="description" rows="4" 
                                          placeholder="Describe detalladamente tu producto, incluyendo características, beneficios y cualquier información relevante para el comprador..."
                                          required><?php echo $_POST['description'] ?? ''; ?></textarea>
                                <div class="form-text">Sé claro y detallado para generar confianza en los compradores.</div>
                            </div>

                            <!-- Subida de Imágenes -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Imágenes del Producto</label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>Arrastra y suelta imágenes aquí</h5>
                                    <p class="text-muted">o haz clic para seleccionar archivos</p>
                                    <small class="text-muted">Formatos: JPG, PNG, GIF, WEBP - Máx. 5MB por imagen</small>
                                    <input type="file" name="images[]" id="imageInput" multiple 
                                           accept=".jpg,.jpeg,.png,.gif,.webp" style="display: none;">
                                </div>
                                <div class="image-preview-container" id="imagePreviewContainer"></div>
                                <div class="form-text">Puedes subir hasta 5 imágenes. La primera imagen será la principal.</div>
                            </div>

                            <!-- Especificaciones de la Cuenta -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Plataforma *</label>
                                        <select class="form-select" name="platform" id="platformSelect" required>
                                            <option value="">Seleccionar plataforma</option>
                                            <option value="netflix" <?php echo ($_POST['platform'] ?? '') == 'netflix' ? 'selected' : ''; ?>>Netflix</option>
                                            <option value="disney" <?php echo ($_POST['platform'] ?? '') == 'disney' ? 'selected' : ''; ?>>Disney+</option>
                                            <option value="hbo" <?php echo ($_POST['platform'] ?? '') == 'hbo' ? 'selected' : ''; ?>>HBO Max</option>
                                            <option value="spotify" <?php echo ($_POST['platform'] ?? '') == 'spotify' ? 'selected' : ''; ?>>Spotify</option>
                                            <option value="youtube" <?php echo ($_POST['platform'] ?? '') == 'youtube' ? 'selected' : ''; ?>>YouTube Premium</option>
                                            <option value="amazon" <?php echo ($_POST['platform'] ?? '') == 'amazon' ? 'selected' : ''; ?>>Amazon Prime</option>
                                            <option value="apple" <?php echo ($_POST['platform'] ?? '') == 'apple' ? 'selected' : ''; ?>>Apple TV+</option>
                                            <option value="paramount" <?php echo ($_POST['platform'] ?? '') == 'paramount' ? 'selected' : ''; ?>>Paramount+</option>
                                            <option value="star" <?php echo ($_POST['platform'] ?? '') == 'star' ? 'selected' : ''; ?>>Star+</option>
                                            <option value="other" <?php echo ($_POST['platform'] ?? '') == 'other' ? 'selected' : ''; ?>>Otra Plataforma</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tipo de Cuenta *</label>
                                        <select class="form-select" name="account_type" required>
                                            <option value="">Seleccionar tipo</option>
                                            <option value="shared" <?php echo ($_POST['account_type'] ?? '') == 'shared' ? 'selected' : ''; ?>>Compartida</option>
                                            <option value="personal" <?php echo ($_POST['account_type'] ?? '') == 'personal' ? 'selected' : ''; ?>>Personal</option>
                                            <option value="family" <?php echo ($_POST['account_type'] ?? '') == 'family' ? 'selected' : ''; ?>>Familiar</option>
                                            <option value="premium" <?php echo ($_POST['account_type'] ?? '') == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                        </select>
                                        <div class="form-text">Define el tipo de acceso de la cuenta.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Duración (Días) *</label>
                                        <select class="form-select" name="duration_days" required>
                                            <option value="7" <?php echo ($_POST['duration_days'] ?? '') == '7' ? 'selected' : ''; ?>>7 días</option>
                                            <option value="15" <?php echo ($_POST['duration_days'] ?? '') == '15' ? 'selected' : ''; ?>>15 días</option>
                                            <option value="30" <?php echo ($_POST['duration_days'] ?? '30') == '30' ? 'selected' : ''; ?>>30 días</option>
                                            <option value="60" <?php echo ($_POST['duration_days'] ?? '') == '60' ? 'selected' : ''; ?>>60 días</option>
                                            <option value="90" <?php echo ($_POST['duration_days'] ?? '') == '90' ? 'selected' : ''; ?>>90 días</option>
                                            <option value="180" <?php echo ($_POST['duration_days'] ?? '') == '180' ? 'selected' : ''; ?>>180 días</option>
                                            <option value="365" <?php echo ($_POST['duration_days'] ?? '') == '365' ? 'selected' : ''; ?>>365 días</option>
                                        </select>
                                        <div class="form-text">Tiempo de duración garantizado de la cuenta.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Características -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Características Incluidas</label>
                                <div class="row" id="featuresContainer">
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="4k">
                                            <i class="fas fa-tv me-1"></i>Calidad 4K
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="multi_device">
                                            <i class="fas fa-mobile-alt me-1"></i>Múltiples Dispositivos
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="offline">
                                            <i class="fas fa-download me-1"></i>Descargas Offline
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="no_ads">
                                            <i class="fas fa-ban me-1"></i>Sin Anuncios
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="hdr">
                                            <i class="fas fa-sun me-1"></i>HDR
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="dolby">
                                            <i class="fas fa-volume-up me-1"></i>Dolby Audio
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="support">
                                            <i class="fas fa-headset me-1"></i>Soporte 24/7
                                        </span>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-outline-secondary feature-badge p-2 w-100" data-feature="warranty">
                                            <i class="fas fa-shield-alt me-1"></i>Garantía
                                        </span>
                                    </div>
                                </div>
                                <input type="hidden" name="features" id="selectedFeatures" value='<?php echo json_encode($_POST['features'] ?? []); ?>'>
                                <div class="form-text">Selecciona las características que incluye tu producto.</div>
                            </div>

                            <!-- Botones -->
                            <div class="d-flex justify-content-between">
                                <button type="reset" class="btn btn-outline-secondary" id="resetBtn">
                                    <i class="fas fa-undo me-2"></i>Limpiar Formulario
                                </button>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Agregar Producto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información de Ayuda -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>Consejos para Imágenes
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Usa imágenes de alta calidad</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Muestra capturas de pantalla reales</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Incluye el logo de la plataforma</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Muestra las características principales</li>
                                    <li><i class="fas fa-check text-success me-2"></i>La primera imagen será la principal</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Formatos Permitidos
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-image text-primary me-2"></i>JPG/JPEG - Hasta 5MB</li>
                                    <li class="mb-2"><i class="fas fa-image text-primary me-2"></i>PNG - Hasta 5MB</li>
                                    <li class="mb-2"><i class="fas fa-image text-primary me-2"></i>GIF - Hasta 5MB</li>
                                    <li><i class="fas fa-image text-primary me-2"></i>WEBP - Hasta 5MB</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Manejo de características
        document.addEventListener('DOMContentLoaded', function() {
            const featureBadges = document.querySelectorAll('.feature-badge');
            const selectedFeaturesInput = document.getElementById('selectedFeatures');
            let selectedFeatures = JSON.parse(selectedFeaturesInput.value || '[]');

            // Actualizar badges seleccionados
            function updateSelectedFeatures() {
                featureBadges.forEach(badge => {
                    const feature = badge.getAttribute('data-feature');
                    if (selectedFeatures.includes(feature)) {
                        badge.classList.add('selected', 'bg-success', 'text-white');
                        badge.classList.remove('bg-outline-secondary');
                    } else {
                        badge.classList.remove('selected', 'bg-success', 'text-white');
                        badge.classList.add('bg-outline-secondary');
                    }
                });
                selectedFeaturesInput.value = JSON.stringify(selectedFeatures);
            }

            // Click en características
            featureBadges.forEach(badge => {
                badge.addEventListener('click', function() {
                    const feature = this.getAttribute('data-feature');
                    const index = selectedFeatures.indexOf(feature);
                    
                    if (index > -1) {
                        selectedFeatures.splice(index, 1);
                    } else {
                        selectedFeatures.push(feature);
                    }
                    
                    updateSelectedFeatures();
                });
            });

            // Inicializar características
            updateSelectedFeatures();

            // Manejo de imágenes
            const uploadArea = document.getElementById('uploadArea');
            const imageInput = document.getElementById('imageInput');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const maxImages = 5;
            let currentImages = 0;

            // Click en área de upload
            uploadArea.addEventListener('click', function() {
                imageInput.click();
            });

            // Drag and drop
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function() {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });

            // Cambio en input de archivos
            imageInput.addEventListener('change', function() {
                handleFiles(this.files);
            });

            // Función para manejar archivos
            function handleFiles(files) {
                for (let file of files) {
                    if (currentImages >= maxImages) {
                        alert(`Solo puedes subir hasta ${maxImages} imágenes.`);
                        break;
                    }

                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const preview = document.createElement('div');
                            preview.className = 'image-preview';
                            preview.innerHTML = `
                                <img src="${e.target.result}" alt="Preview">
                                <button type="button" class="remove-image" onclick="removeImage(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            `;
                            imagePreviewContainer.appendChild(preview);
                            currentImages++;
                        };
                        
                        reader.readAsDataURL(file);
                    }
                }
                
                // Actualizar input de archivos
                updateFileInput();
            }

            // Función para remover imagen
            window.removeImage = function(button) {
                const preview = button.parentElement;
                preview.remove();
                currentImages--;
                updateFileInput();
            };

            // Actualizar input de archivos
            function updateFileInput() {
                // Esta función mantiene la referencia a los archivos seleccionados
                // En una implementación real, podrías usar FormData para manejar múltiples archivos
            }

            // Validación de precio
            const priceInput = document.querySelector('input[name="price"]');
            priceInput.addEventListener('change', function() {
                if (this.value < 1) {
                    this.value = 1;
                }
            });

            // Validación de stock
            const stockInput = document.querySelector('input[name="stock"]');
            stockInput.addEventListener('change', function() {
                if (this.value < 1) {
                    this.value = 1;
                } else if (this.value > 100) {
                    this.value = 100;
                }
            });

            // Limpiar previsualizaciones al resetear
            document.getElementById('resetBtn').addEventListener('click', function() {
                imagePreviewContainer.innerHTML = '';
                currentImages = 0;
            });
        });

        // Prevenir envío doble del formulario
        document.getElementById('productForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Agregando...';
        });
    </script>
</body>
</html>